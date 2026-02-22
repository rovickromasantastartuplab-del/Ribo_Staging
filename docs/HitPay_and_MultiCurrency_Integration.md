# HitPay & Multi-Currency Pricing Integration

## Overview
This document summarizes the architectural changes implemented to support multi-currency pricing plans and the integration of the HitPay payment gateway across the CRM platform.

---

## 1. Multi-Currency Pricing
Instead of live currency conversions, administrators can manually define explicit prices (Monthly/Yearly) for every enabled currency per plan via the CRM UI.

### Database & Models
- **Migration**: Added `plan_currency_prices` table to store explicit prices (`currency_code`, `monthly_price`, `yearly_price`) referenced by a `plan_id`.
- **`Plan.php` Model**: Added a `currencyPrices()` hasMany relationship. 
- **`getPriceForCurrency()`**: A model function added to return the custom currency price if it exists, otherwise safely falling back to the base `$this->price` legacy attribute.

### Controllers
- **`PlanController.php`**: The `index()`, `store()`, `edit()`, `update()`, and `companyPlansView()` endpoints were upgraded to serialize the active Global Currency from settings and pass parsed pricing down to the frontend properly using `$plan->getPriceForCurrency()`.

### Plan Creation / Editing Frontend (`resources/js/pages/plans`)
- Instead of static price fields, a dynamic table displays existing system currencies allowing an admin to define independent prices smoothly inside `form.tsx`.
- Form payloads now map the array of `currency_prices` to the controller efficiently.

---

## 2. HitPay Payment Integration
We incorporated the HitPay gateway alongside the 34 existing supported platforms dynamically cleanly mirroring the gateway interface logic.

### Settings UI (`payment-settings.tsx`)
- Appended a HitPay card containing fields for the `API Key`, webhook `Salt`, and a Live/Sandbox operational environment toggle.
- Handled properly via filtering in `PaymentSettingController.php` and storage serialization in the `PaymentSettings` model. (Including encryption support locally).

### Secure Webhooks & Controllers
- **`HitPayPaymentController.php`**: Created to parse plan intents, validate amounts, ping HitPay API (with SSL dev fallbacks locally), receive server-side encrypted webhooks, and execute `processPaymentSuccess`.
- **`VerifyCsrfToken.php`**: Exempted the HitPay webhook callback URL from CSRF checking securely.

### Frontend Checkout (`hitpay-payment-form.tsx`)
- Constructed an intuitive loading form component inside the subscription modal that generates a HitPay checkout request upon confirmation and bounces the user to HitPay hosted pages.

---

## 3. Global Checkout Price Resolution Fix
To ensure the multi-currency integration functioned securely inside checkout servers:

### `calculatePlanPricing()` Update (`app/Helpers/helper.php`)
- **Crucial Update**: The helper inherently responsible for converting prices securely for **all 34 backend payment gateways** (Stripe, Razorpay, HitPay, etc.) only processed base tier prices (`$plan->getPriceForCycle()`).
- We updated this function to fetch `Setting::getUserSettings($superAdminId)['defaultCurrency']` and process the final price actively through `$plan->getPriceForCurrency()`. 
- Doing so universally corrected the amount requested on all gateways without manually opening and editing 34 unique individual payment controllers.

### TypeScript Interfaces (`utils/payment.ts`)
- Repaired broken generic indices returning TS failures inside `plans/index.tsx` Subscription modals by firmly typing the `<Record<string, string>>` object dictionary maps natively inside our `getPaymentMethodIcon` function.
- Expanded the Inertia JS `Plan` interface payload globally to inherently comprehend the passed `paymentMethods` structure flawlessly.
