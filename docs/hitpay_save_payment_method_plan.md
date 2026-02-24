# HitPay Free Trial (Save Bank Account) Implementation Plan

## Objective
To allow users to sign up for a **Free Trial / $0 Plan** by securely connecting their bank account or credit card via HitPay, without charging them the full subscription price upfront.

## HitPay API Specifications
This workflow uses HitPay's **"Save Payment Method"** (Recurring Billing API).
**Endpoint:** `POST https://api.hit-pay.com/v1/recurring-billing`

### The Minimum Authorization Requirement
HitPay technically requires a minimum authorization charge (e.g., $1.00 USD or 1.00 PHP) to trigger the `save_payment_method` flow safely. HitPay uses this nominal amount to verify that the linked bank account or card is real, active, and has sufficient funds to eventually cover the subscription when the trial ends.

## Phase 1: Database Updates
To support deferred payments, we must store the unique token HitPay provides when a user successfully connects their bank.

### **[NEW MIGRATION]** `create_hitpay_payment_methods_table.php`
- Add a new table called `user_payment_methods` (or modify `users` to include `hitpay_payment_method_id`).
- Expected fields:
  - `user_id` (Foreign Key)
  - `payment_method_id` (The HitPay token)
  - `card_brand` (e.g., "Visa", "Mastercard", "Giro")
  - `last_4` (Last 4 digits of the card for UI display)
  - `status` (active, revoked)

## Phase 2: Backend Logic Modification
### **[MODIFY]** `HitPayPaymentController.php`

#### 1. Update `processPayment()`
When a user selects a plan, check if the plan has the Free Trial toggle enabled (`$plan->is_trial === 'on'`).
If it is a trial plan, bypass the standard `/v1/payment-requests` endpoint which instantly executes a charge.
Instead, execute an API call to HitPay's `/v1/recurring-billing` API to authorize the account for future billing using the plan's `trial_day` property. We will include **all possible metadata parameters**:
```json
{
  "name": "Subscription to Free Trial Plan",
  "description": "Free Trial Authorization Hold",
  "customer_email": "user@example.com",
  "customer_name": "John Doe",
  "amount": 1.00,
  "currency": "PHP",
  "save_payment_method": true,
  "reference": "hp_trial_1_1712345678_abcdef",
  "redirect_url": "https://ribo.com.ph/payments/hitpay/success?payment_id=hp_trial_1_1712345678_abcdef",
  "webhook": "https://ribo.com.ph/payments/hitpay/webhook",
  "payment_methods": ["card", "shopee_pay", "grabpay_direct"]
}
```

#### 2. Update Webhook Handling (`callback()`)
HitPay will broadcast a new webhook event to our `webhook` URL when the user finishes attaching their card: `recurring_billing.method_attached`.
When this specific webhook fires:
- Extract the `id` (recurring billing reference ID).
- Extract payment details (`payment_provider.charge.details`), such as `last4` and `brand`.
- Save the `payment_method_id` to the database payload created in Phase 1.
- Use the `reference` parameter from the webhook to find the user's pending `PlanOrder` and immediately activate the Free Trial Subscription.

## Phase 3: Frontend UI Adjustment
### **[MODIFY]** `UpgradePlanModal.tsx`
- Ensure the React UI detects when the selected subscription plan is a Trial plan (`plan.is_trial === 'on'`).
- Swap the call-to-action button from "Pay Now" to **"Connect Bank Account ({plan.trial_day} Day Free Trial)"**.
- Add a tiny localized disclaimer: *"A temporary hold of 1.00 [Currency] may be applied to verify your account. You will not be charged the full amount until your trial ends."*

## Next Steps
Once verified, you will be able to use HitPay's **Charge API** `POST /v1/charge/recurring-billing/{payment_method_id}` to programmatically bill users automatically when their `trial_expire_date` on the `users` table passes.

## Files Affected by This Implementation
### Backend (PHP/Laravel)
*   `[NEW]` `database/migrations/xxxx_xx_xx_xxxxxx_create_user_payment_methods_table.php` (To store the tokenized `payment_method_id` from HitPay)
*   `[NEW]` `app/Models/UserPaymentMethod.php` (Eloquent model representing the stored tokens)
*   `[MODIFY]` `app/Http/Controllers/HitPayPaymentController.php` (To implement the `/v1/recurring-billing` logic instead of `/v1/payment-requests` when a plan has `is_trial === 'on'`, and handle the webhook)
*   `[MODIFY]` `app/Models/User.php` (To add the `paymentMethods()` model relationship)

### Frontend (React/Inertia)
*   `[MODIFY]` `resources/js/components/UpgradePlanModal.tsx` (To detect trial plans (`plan.is_trial === 'on'`) and change the checkout button action, UI copy, and disclaimer)
*   `[MODIFY]` `resources/js/pages/plans/index.tsx` (To ensure the `$plan->is_trial` setting passes correctly to the Modal components on the internal dashboard)
*   `[MODIFY]` `resources/js/pages/landing-page/components/PlansSection.tsx` (To surface trial information, e.g., "14 Day Free Trial", on the public landing page when enabled)
