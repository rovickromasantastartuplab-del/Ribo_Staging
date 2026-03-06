# HitPay Payment Gateway Integration

This document outlines how the **HitPay Payment Gateway** is integrated into the system, specifically detailing how the Superadmin and Company roles configure it, and how sensitive credentials (API Key and Salt) are securely stored.

---

## 1. Overview
The system allows both **Superadmins** and **Companies** to set up their own independent HitPay payment gateways. This means a Company can receive payments directly into their own HitPay account, separate from the Superadmin's account or other companies.

---

## 2. How the Configuration Works

Both the Superadmin and Company configurations share the exact same logic and database table, but are separated by the user ID.

### For Superadmins:
- **Location:** Superadmins configure HitPay in their main **Payment Settings** interface.
- **Controller:** Handled by `PaymentSettingController`.
- **Action:** When saved, the system takes the authenticated Superadmin's ID (`auth()->id()`) and stores the settings specifically for them.

### For Companies:
- **Location:** Companies configure HitPay in their **Company Settings** interface.
- **Controller:** Handled by `CompanyPaymentSettingController`.
- **Action:** The controller reuses the exact same saving logic (`PaymentSettingController::store()`). It takes the authenticated Company's ID and stores the credentials, associating them only with that specific Company.

---

## 3. How API Keys and Salts are Stored (Security)

To prevent security breaches, the system **does not** store the HitPay API Key or Salt in plain text.

### A. Encryption at Rest (Saving to Database)
1. **Sensitive Keys List:** The `PaymentSetting` database model maintains a strict list of `$sensitiveKeys`, which explicitly includes `hitpay_api_key` and `hitpay_salt`.
2. **Automatic Automatic Encryption:** When coordinates are saved to the database, a Laravel mutator (`setValueAttribute`) automatically intercepts the data. If the key matches `hitpay_api_key` or `hitpay_salt`, it encrypts the value using Laravel's robust `Crypt::encryptString()` method.
3. **Result:** Anyone looking directly at the `payment_settings` database table will only see unreadable, scrambled cipher text.

### B. Decryption (Using the Keys)
1. **Automatic Decryption:** When the application needs to use the API key (e.g., to process a payment), the `PaymentSetting` model reads the encrypted string from the database.
2. **Interceptor:** An accessor (`getValueAttribute`) intercepts this read operation and automatically decrypts it back to plain text using `Crypt::decryptString()`.
3. **Result:** The application code always receives the usable plain text key, completely unaware that it was encrypted in the database.

### C. Frontend Security
When configuration data is loaded back to the frontend (so the user can see their settings are saved), the system uses a `filterSensitiveData` method to ensure only safe settings are passed. Additionally, the system may present the raw cipher text to the frontend form so the real API key is never exposed in the browser's Network tab.

---

## 4. Payment Flow in Action

1. **Retrieving Configuration:** When a customer makes a purchase, the system uses the `getPaymentMethodConfig('hitpay', $userId)` helper function to retrieve the correct, decrypted HitPay credentials for that specific Company or Superadmin.
2. **Creating the Checkout:** The `HitPayPaymentController` uses these credentials to generate a secure checkout link via the HitPay API.
3. **Webhook Verification (Salt Validation):** Once the user pays, HitPay sends a secret "Webhook" behind the scenes to confirm the payment. The system checks the `x-hitpay-signature` provided in the webhook header. It hashes the payload against the decrypted `hitpay_salt`—if they match, the payment is securely marked as Paid.
