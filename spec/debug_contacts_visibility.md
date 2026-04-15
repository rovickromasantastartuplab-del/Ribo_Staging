    # Debug Spec: Contact Visibility Issue (Angelo / RON's Data)
    **Type:** Debugging
    **Created:** 2024-05-23 (Simulated)
    **Status:** Root Cause Found

    ---

    ## 1. Bug Summary

    ### Problem
    Angelo (a staff user with "ADMIN" expectations) cannot see contacts created by "The Company" (containing "RON's data").

    ### Expected Behavior
    Angelo, being an ADMIN with full access, should see all contacts created by the company he belongs to, and potentially across companies if he is a global admin.

    ### Actual Behavior
    Angelo (ID 46) only sees contacts explicitly assigned to him. He does not see the broader company contacts, even though he is considered an admin.

    ### Impact
    - [ ] User-visible issue: YES
    - [x] Data issue: YES (Visibility restriction)
    - [ ] Realtime / async issue
    - [ ] Performance issue
    - [ ] Security issue
    - [ ] Other: [describe]

    ---

    ## 2. Reproduction

    ### Reproduction Steps
    1. Log in as a User with ID 46 (Angelo).
    2. Navigate to the Contacts list.
    3. Observe that "RON's data" (created by Company ID 2) is missing.
    4. Log in as Company ID 2 and observe that "RON's data" is present.

    ### Reproducibility
    - [x] Always
    - [ ] Sometimes
    - [ ] Not yet reproduced reliably

    ### Notes
    Angelo (ID 46) is a `staff` user for Company 31.
    Contacts for "RON" (ID 1 in `wedding_suppliers`) were created by Company 2.

    ---

    ## 3. Trace the Flow

    ### Broken Path
    1. `ContactController@index` fetches contacts.
    2. It checks `auth()->user()->can('manage-contacts')` or `auth()->user()->can('view-contacts')`.
    3. Angelo (ID 46) DOES NOT have these permissions in the database (verified via `check_angelo_perms.php`).
    4. The logic falls into the `else` block: `$q->where('assigned_to', auth()->id())`.
    5. Since RON is not assigned to Angelo, it is hidden.

    ### Working / Expected Path
    1. If Angelo had the permissions, the query would use `$q->where('created_by', createdBy())`.
    2. `createdBy()` for Angelo (staff of 31) returns 31.
    3. However, RON was created by Company 2.
    4. Even with permissions, Angelo (as staff of 31) would NOT see Company 2's data.

    ### Relevant Files / Modules / Services
    - [x] `app/Http/Controllers/ContactController.php` (Scoping logic)
    - [x] `app/Helpers/helper.php` (`createdBy()` logic)
    - [x] `app/Models/User.php` (Roles and Permissions)

    ---

    ## 4. Hypotheses

    ### Hypothesis 1
    - Description: Angelo is missing the required permissions (`manage-contacts` or `view-contacts`).
    - Evidence for: Database check shows he lacks these permissions.
    - Evidence against: None.
    - Result: **Confirmed** (Partially explains why he only sees assigned contacts).

    ### Hypothesis 2
    - Description: Angelo belongs to the wrong company in the database.
    - Evidence for: Angelo belongs to 31, but the target data belongs to 2.
    - Evidence against: We don't know the "intended" company, but the mismatch is real.
    - Result: **Confirmed** (Explains why even with permissions he might not see it).

    ### Hypothesis 3
    - Description: `createdBy()` is too restrictive for ADMIN/SuperAdmin users.
    - Evidence for: It always returns the user ID or the creator ID, never allowing "all" or specific crosses.
    - Evidence against: None.
    - Result: **Confirmed** (Affects global visibility).

    ---

    ## 5. Root Cause

    ### Proven Root Cause
    1. **Missing Permissions**: Angelo (ID 46) does not have `view-contacts` or `manage-contacts` permissions. This forces the controller to only show contacts assigned to him.
    2. **Company Mismatch**: Angelo is a staff of Company 31, while the data ("RON") is owned by Company 2. The `createdBy()` helper restricts him to Company 31's scope.
    3. **Admin Visibility Gap**: There is no logic in the controller to bypass `createdBy()` for "ADMIN" users who should see everything.

    ### Exact Breakpoint
    `ContactController.php:17` (Permission check fails) -> `ContactController.php:20` (Assigned-to restriction applied).

    ### Evidence
    - `check_angelo_perms.php`: Angelo lacks `view-contacts`.
    - `find_all_angelo_ron.php`: Angelo belongs to 31, RON belongs to 2.

    ---

    ## 6. Fix Plan Approval

    ### Smallest Safe Fix
    1. Assign `view-contacts` (and potentially `manage-contacts`) permission to Angelo or his role.
    2. Verify if Angelo should be reassigned to Company 2.
    3. (Optional but recommended) Update `ContactController` to allow `superadmin` or certain ADMIN roles to bypass the `created_by` filter if they have a "view-all" permission.

    ### Affected Files / Modules
    - [x] `app/Http/Controllers/ContactController.php`
    - [x] Database roles/permissions (via seeder or manual update)

    ### Risks
    - [x] Regression risk: Giving too much access to other staff users.
    - [x] Edge cases: Mixed company data might be confusing if not handled carefully.

    ### Approval
    - [ ] User approved fix plan

    ---

    ## 11. Change Log
    - [2024-05-23] Created debug spec and confirmed root cause.
