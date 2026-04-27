# Mailbox Provider Restriction (One Per Type)

## Problem
Currently, users can connect multiple Gmail accounts because the unique identification logic in `SocialAuthController` includes the `email_address`. This leads to a cluttered interface and violates the requirement of having only one "official" mailbox per provider type.

## Proposed Solution
Standardize the account identification logic to use only the `user_id` and the provider `type` ('gmail' or 'smtp_imap') as the search key. This enforces a strict "One Account Per Provider Type" rule while allowing users to easily switch or update accounts by simply connecting a new one (Overwrite behavior).

## Proposed Changes

### 1. SocialAuthController (Gmail)
Modify the `callback` method to identify the account solely by `user_id` and `type => 'gmail'`.
- Move `email_address` from the search criteria to the update attributes.
- This ensures that if a user connects a different Gmail address, the existing Gmail record for that company is updated rather than creating a duplicate.

### 2. ChannelAccountController (SMTP/IMAP)
Modify the `store` method to use `updateOrCreate` instead of `create`.
- Use `['user_id' => $companyId, 'type' => 'smtp_imap']` as the search key.
- All configuration and email address data will be passed as update attributes.

## Data Flow
1. **User Action:** Initiates a connection (OAuth for Gmail, Form for SMTP).
2. **Identification:** The controller identifies the company ID and the provider type.
3. **Database Check:** 
   - If a record exists for that `user_id` and `type`, it is updated with the new credentials and email address.
   - If no record exists, a new one is created.
4. **Post-Connection:** The system triggers an initial sync for the updated/new record.

## Verification Plan
- **Manual Test (Gmail):** Connect Gmail A. Verify 1 record. Connect Gmail B. Verify only 1 record exists and it now reflects Gmail B.
- **Manual Test (SMTP):** Connect SMTP A. Verify 1 record. Connect SMTP B. Verify only 1 record exists and it now reflects SMTP B.
- **Cross-Type Test:** Verify that having a Gmail account does NOT prevent adding an SMTP account (total of two allowed).s
