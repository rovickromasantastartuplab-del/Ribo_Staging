# HitPay Payment Gateway — Integration Documentation

> **Last updated:** 2026-02-22  
> **Ported from:** `startuplab-business-ticketing` Node.js backend  
> **Gateway docs:** https://hit-pay.com/docs

---

## Table of Contents

1. [Overview](#overview)
2. [How It Works (Flow)](#how-it-works-flow)
3. [Files](#files)
4. [Configuration (Admin)](#configuration-admin)
5. [Environment & Keys](#environment--keys)
6. [Backend Reference](#backend-reference)
7. [Frontend Reference](#frontend-reference)
8. [Webhook Verification](#webhook-verification)
9. [Sandbox Testing](#sandbox-testing)
10. [Troubleshooting](#troubleshooting)

---

## Overview

HitPay is a redirect-based payment gateway popular in Southeast Asia (Philippines, Singapore, Malaysia). The customer is redirected to a hosted HitPay checkout page to complete payment, then brought back to the CRM. HitPay notifies the backend of the result via a signed webhook.

This integration supports **subscription plan purchases** (not invoice payments).

---

## How It Works (Flow)

```
Customer clicks "Pay with HitPay"
  │
  ▼
POST /payments/hitpay                    ← processPayment()
  CRM creates a pending PlanOrder
  CRM calls HitPay API → /v1/payment-requests
  HitPay returns a checkout URL
  │
  ▼
Customer is redirected to HitPay checkout (card, e-wallet, QR, etc.)
  │
  ├─── Payment succeeds ─────────────────────
  │     HitPay POSTs to /payments/hitpay/webhook ← callback()
  │     CRM verifies HMAC-SHA256 signature
  │     CRM marks PlanOrder "approved"
  │     CRM activates plan for the user
  │     Customer lands on /payments/hitpay/success ← success()
  │
  └─── Payment fails/cancelled ──────────────
        HitPay POSTs to webhook with status FAILED/CANCELLED
        CRM marks PlanOrder "rejected"
```

---

## Files

### New / Created

| File | Description |
|---|---|
| `app/Http/Controllers/HitPayPaymentController.php` | Main controller with 3 methods |
| `resources/js/components/payment/hitpay-payment-form.tsx` | Checkout form component |

### Modified

| File | What changed |
|---|---|
| `app/Models/PaymentSetting.php` | Added `hitpay_api_key`, `hitpay_salt` to `$sensitiveKeys` (encrypted at rest) and to `$booleanKeys` |
| `app/Http/Controllers/Settings/PaymentSettingController.php` | Validation rules, `preparePaymentSettings()`, `filterSensitiveData()` |
| `app/Helpers/helper.php` | `getPaymentMethodConfig('hitpay')` case + added `'hitpay'` to `getEnabledPaymentMethods()` |
| `routes/web.php` | 3 new routes (see Routes section) |
| `app/Http/Middleware/VerifyCsrfToken.php` | `payments/hitpay/webhook` excluded from CSRF |
| `resources/js/pages/settings/components/payment-settings.tsx` | HitPay accordion card in admin Settings UI |
| `resources/js/utils/payment.ts` | `HITPAY` constant, label, help URL, validation, icon |
| `resources/js/components/payment/payment-processor.tsx` | `case 'hitpay'` in `renderPaymentForm()` switch |

---

## Configuration (Admin)

1. Log in as **Superadmin**
2. Go to **Settings → Payment**
3. Scroll down to the **HitPay** card
4. Toggle **Enable**
5. Select **Mode**: `Sandbox` (for testing) or `Live` (for production)
6. Paste your **API Key** from the HitPay dashboard
7. Paste your **Salt** (Webhook Secret) from the HitPay dashboard
8. Click **Save Changes**

> **Important:** After saving, the API Key and Salt are AES-256 encrypted in the database. The plain-text values are never stored.

---

## Environment & Keys

You do **not** need to add any `.env` variables. The keys are stored in the `payment_settings` database table, loaded dynamically per superadmin.

| Setting key | Description |
|---|---|
| `hitpay_api_key` | X-BUSINESS-API-KEY header sent to HitPay API |
| `hitpay_salt` | Used to verify webhook HMAC-SHA256 signatures |
| `hitpay_mode` | `sandbox` or `live` |
| `is_hitpay_enabled` | Boolean toggle |

**API Base URLs:**

| Mode | URL |
|---|---|
| Sandbox | `https://api.sandbox.hit-pay.com` |
| Live | `https://api.hit-pay.com` |

---

## Backend Reference

### `HitPayPaymentController.php`

#### `processPayment(Request $request)`
- **Route:** `POST /payments/hitpay` (auth-protected)
- **Body:** `plan_id`, `billing_cycle`, `coupon_code`
- **What it does:**
  1. Loads HitPay settings via `getPaymentMethodConfig('hitpay')`
  2. Calculates plan pricing (including coupon discount)
  3. Creates a `pending` `PlanOrder`
  4. Calls `POST {BASE_URL}/v1/payment-requests` with:
     - `amount`, `currency`, `reference_number` (= payment ID)
     - `redirect_url` → `/payments/hitpay/success?payment_id=...`
     - `webhook` → `/payments/hitpay/webhook`
  5. Returns `{ success: true, checkoutUrl: "https://..." }`

#### `callback(Request $request)`
- **Route:** `POST /payments/hitpay/webhook` (public, CSRF-exempt)
- **What it does:**
  1. Verifies HMAC-SHA256 signature (see below)
  2. Looks up `PlanOrder` by `reference_number`
  3. On `COMPLETED`/`SUCCEEDED`: calls `processPaymentSuccess()` → activates plan
  4. On `FAILED`/`CANCELLED`/`EXPIRED`: marks order `rejected`
  5. Returns HTTP 200 `OK`

#### `success(Request $request)`
- **Route:** `GET /payments/hitpay/success` (public)
- **What it does:** Redirects to `/plans` with a success/pending flash message

---

## Frontend Reference

### `hitpay-payment-form.tsx`

A simple redirect-based form. No customer details are needed — HitPay collects them on their hosted page.

**Props:**

| Prop | Type | Description |
|---|---|---|
| `planId` | `number` | Plan being purchased |
| `billingCycle` | `'monthly' \| 'yearly'` | Billing frequency |
| `couponCode` | `string` | Applied coupon code |
| `planPrice` | `number` | Final amount after discount |
| `currency` | `string` | ISO currency code (e.g. `PHP`) |
| `onSuccess` | `() => void` | Callback after redirect initiation |
| `onCancel` | `() => void` | Callback when user cancels |

**Behaviour:** On submit, POSTs to `route('hitpay.payment')`, then does `window.location.href = checkoutUrl` to redirect to HitPay.

---

## Webhook Verification

HitPay sends a `hmac` field in the POST body. We verify it using **HMAC-SHA256**:

```php
// Build the message: sort all non-hmac fields alphabetically,
// concatenate as key+value (no separator), hash with SHA256
ksort($signatureData);
$message = implode('', array_map(fn($k, $v) => $k . $v, array_keys($signatureData), $signatureData));
$computed = hash_hmac('sha256', $message, $hitpay_salt);
hash_equals($computed, $received_hmac); // true = valid
```

The Salt used here is the **Webhook Secret** from your HitPay dashboard (Settings → Payment → HitPay → Salt field).

---

## Sandbox Testing

1. Register at [HitPay Sandbox](https://dashboard.sandbox.hit-pay.com)
2. From the dashboard, get your **API Key** and **Webhook Salt**
3. Configure Settings → Payment → HitPay (mode = Sandbox)
4. Register your webhook URL in HitPay dashboard:
   - `https://your-domain.com/payments/hitpay/webhook`
   - For local testing, use [ngrok](https://ngrok.com): `ngrok http 8000` and set the forwarding URL

### Test Card Numbers (HitPay Sandbox)
| Card | Number | CVC | Expiry |
|---|---|---|---|
| Visa (success) | `4242 4242 4242 4242` | Any 3 digits | Any future date |
| Mastercard (success) | `5555 5555 5555 4444` | Any 3 digits | Any future date |
| Declined | `4000 0000 0000 0002` | Any | Any |

---

## Troubleshooting

### "HitPay not configured" error
- Check that **API Key** and **Salt** are saved in Settings → Payment → HitPay
- Make sure the HitPay toggle is enabled and **Save** was clicked

### Webhook signature mismatch (401)
- Verify the **Salt** in Settings matches the **Webhook Secret** in your HitPay dashboard
- Make sure `payments/hitpay/webhook` is registered in HitPay's webhook list
- For local testing, use ngrok (HitPay cannot reach `localhost` directly)

### Plan not activating after payment
- Check Laravel logs: `storage/logs/laravel.log`
- Confirm the webhook URL is reachable from HitPay's servers
- Confirm `processPaymentSuccess()` helper in `helper.php` is executing correctly

### Encrypted keys look wrong in DB
- This is expected! The `payment_settings` table stores AES-256 ciphertext (a long base64 string). The decryption is transparent — the controller always receives plain text via the model accessor.
- **⚠️ Warning:** Never change `APP_KEY` in `.env` after saving settings, or you will not be able to decrypt the stored keys.

---

## Related Files (for Invoice Payment — not yet implemented)

If you want to add HitPay for **invoice payments** (not just plan subscriptions), you would follow the same pattern as existing `Invoice*PaymentController.php` files (e.g., `InvoicePayfastPaymentController.php`).
