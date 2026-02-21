<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\PlanRequest;
use App\Models\Role;
use App\Models\User;
use App\Models\Permission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    /**
     * Step 1: Show company name form.
     */
    public function showCompany(): Response|RedirectResponse
    {
        $user = Auth::user();

        if ($user->hasCompletedOnboarding()) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('onboarding/company', [
            'companyName' => $user->company_name,
            'isLegacy' => $user->isLegacyAccount(),
        ]);
    }

    /**
     * Step 1: Store company name.
     */
    public function storeCompany(Request $request): RedirectResponse
    {
        $request->validate([
            'company_name' => 'required|string|max:255',
        ]);

        $user = Auth::user();

        // Assign default plan if not already assigned
        $defaultPlan = Plan::getDefaultPlan();

        $user->update([
            'company_name' => $request->company_name,
            'type' => 'company',
            'plan_id' => $user->plan_id ?? $defaultPlan?->id,
            'created_by' => $user->id, // Company owner references self
        ]);

        // Create default team roles for this company
        $this->createDefaultRoles($user);

        // Soft Bypass Logic: If this is an existing Legacy Account, they already have plans and staff.
        // Once they provide a Company Name, they are done!
        if ($user->isLegacyAccount()) {
            $user->update([
                'onboarding_completed_at' => now(),
            ]);
            return redirect()->route('dashboard')->with('success', __('Welcome back! Your Company Name has been saved.'));
        }

        // Step 1 → Step 2 (Plan) for entirely new registrations
        return redirect()->route('onboarding.plan');
    }

    /**
     * Step 2: Show plan selection.
     */
    public function showPlan(): Response|RedirectResponse
    {
        $user = Auth::user();

        if ($user->hasCompletedOnboarding()) {
            return redirect()->route('dashboard');
        }

        // Must complete step 1 first
        if (!$user->company_name) {
            return redirect()->route('onboarding.company');
        }

        $plans = Plan::where('is_plan_enable', 'on')->get();
        $defaultPlan = Plan::getDefaultPlan();

        return Inertia::render('onboarding/plan', [
            'plans' => $plans,
            'currentPlanId' => $user->plan_id ?? $defaultPlan?->id,
            'defaultPlanId' => $defaultPlan?->id,
        ]);
    }

    /**
     * Step 2: Store selected plan.
     * Free/default plans are applied immediately.
     * Paid plans create a PlanRequest for superadmin approval.
     */
    public function storePlan(Request $request): RedirectResponse
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
        ]);

        $user = Auth::user();
        $plan = Plan::findOrFail($request->plan_id);
        $defaultPlan = Plan::getDefaultPlan();

        if ($plan->is_default || $plan->price <= 0) {
            // Free / default plan — apply immediately
            $user->update([
                'plan_id' => $plan->id,
                'plan_expire_date' => $plan->duration === 'yearly' ? now()->addYear() : now()->addMonth(),
                'plan_is_active' => 1,
            ]);
        } else {
            // Paid plan — create a request for superadmin approval
            PlanRequest::create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'duration' => $plan->duration ?? 'monthly',
                'status' => 'pending',
                'message' => 'Plan selected during onboarding',
            ]);

            // Keep the user on the default/free plan until approved
            if ($defaultPlan && !$user->plan_id) {
                $user->update([
                    'plan_id' => $defaultPlan->id,
                    'plan_expire_date' => now()->addMonth(),
                    'plan_is_active' => 1,
                ]);
            }
        }

        // Step 2 → Step 3 (Roles)
        return redirect()->route('onboarding.roles');
    }

    /**
     * Step 3: Show roles & permissions form.
     */
    public function showRoles(): Response|RedirectResponse
    {
        $user = Auth::user();

        if ($user->hasCompletedOnboarding()) {
            return redirect()->route('dashboard');
        }

        // Must complete step 1 first
        if (!$user->company_name) {
            return redirect()->route('onboarding.company');
        }

        // Get company-owned roles only (exclude system roles like company/superadmin)
        $roles = Role::where('created_by', $user->id)
            ->with('permissions:id,name,label,module')
            ->get(['id', 'name', 'label', 'description', 'created_by']);

        // Get all available permissions grouped by module
        $permissions = Permission::query()
            ->get(['id', 'name', 'label', 'module'])
            ->groupBy('module');

        // Get team members for role assignment (if any already exist)
        $members = User::where('created_by', $user->id)
            ->where('id', '!=', $user->id)
            ->with('roles:id,name,label')
            ->get(['id', 'name', 'email']);

        return Inertia::render('onboarding/roles', [
            'roles' => $roles,
            'permissions' => $permissions,
            'members' => $members,
            'companyName' => $user->company_name,
        ]);
    }

    /**
     * Step 2: Store roles and proceed to members.
     */
    public function storeRoles(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $request->validate([
            'custom_roles' => 'nullable|array',
            'custom_roles.*.name' => 'required_with:custom_roles|string|max:255',
            'custom_roles.*.permissions' => 'required_with:custom_roles|array',
            'custom_roles.*.permissions.*' => 'integer|exists:permissions,id',
            'member_roles' => 'nullable|array',
            'member_roles.*.user_id' => 'required|integer|exists:users,id',
            'member_roles.*.role_id' => 'required|integer|exists:roles,id',
        ]);

        // Create custom roles
        if ($request->custom_roles) {
            foreach ($request->custom_roles as $roleData) {
                $role = Role::create([
                    'name' => Str::slug($roleData['name']) . '-' . $user->id,
                    'label' => $roleData['name'],
                    'description' => $roleData['description'] ?? null,
                    'guard_name' => 'web',
                    'created_by' => $user->id,
                ]);

                if (!empty($roleData['permissions'])) {
                    $permissions = Permission::whereIn('id', $roleData['permissions'])->get();
                    $role->syncPermissions($permissions);
                }
            }
        }

        // Assign roles to members (if any already exist)
        if ($request->member_roles) {
            foreach ($request->member_roles as $assignment) {
                $member = User::where('id', $assignment['user_id'])
                    ->where('created_by', $user->id)
                    ->first();

                if ($member) {
                    $role = Role::find($assignment['role_id']);
                    if ($role) {
                        $member->syncRoles([$role]);
                    }
                }
            }
        }

        // Step 2 → Step 3 (Members)
        return redirect()->route('onboarding.members');
    }

    /**
     * Step 3: Show add members form.
     */
    public function showMembers(): Response|RedirectResponse
    {
        $user = Auth::user();

        if ($user->hasCompletedOnboarding()) {
            return redirect()->route('dashboard');
        }

        // Must complete step 1 first
        if (!$user->company_name) {
            return redirect()->route('onboarding.company');
        }

        $plan = $user->plan;
        $maxUsers = $plan ? $plan->max_users : 1;

        // Count existing team members (including the owner)
        $existingMembers = User::where('created_by', $user->id)
            ->where('id', '!=', $user->id)
            ->get(['id', 'name', 'email']);

        $currentCount = $existingMembers->count();

        // Get company-owned roles only (exclude system roles)
        $roles = Role::where('created_by', $user->id)
            ->get(['id', 'name', 'label']);

        return Inertia::render('onboarding/members', [
            'maxUsers' => $maxUsers,
            'currentCount' => $currentCount,
            'existingMembers' => $existingMembers,
            'companyName' => $user->company_name,
            'roles' => $roles,
        ]);
    }

    /**
     * Step 3: Store invited members and complete onboarding.
     */
    public function storeMembers(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $plan = $user->plan;
        $maxUsers = $plan ? $plan->max_users : 1;

        // Count current staff members (excluding the company owner)
        $currentCount = User::where('created_by', $user->id)
            ->where('id', '!=', $user->id)
            ->count();

        $request->validate([
            'members' => 'required|array|min:1',
            'members.*.name' => 'required|string|max:255',
            'members.*.email' => 'required|email|max:255|unique:users,email',
            'members.*.role_id' => 'required|integer|exists:roles,id',
        ]);

        $members = $request->members;

        // Check if adding members would exceed the plan limit
        if (($currentCount + count($members)) > $maxUsers) {
            return back()->withErrors([
                'members' => __('You can only add :count more member(s). Your plan allows :max users total.', [
                    'count' => max(0, $maxUsers - $currentCount),
                    'max' => $maxUsers,
                ]),
            ]);
        }

        foreach ($members as $memberData) {
            $token = Str::random(64);

            $newUser = User::create([
                'name' => $memberData['name'],
                'email' => $memberData['email'],
                'password' => Hash::make(Str::random(16)),
                'type' => 'staff',
                'is_active' => 1,
                'is_enable_login' => 0,
                'created_by' => $user->id,
                'plan_is_active' => 0,
                'invitation_token' => $token,
                'invitation_sent_at' => now(),
            ]);

            // Assign role
            if (!empty($memberData['role_id'])) {
                $role = Role::find($memberData['role_id']);
                if ($role) {
                    $newUser->assignRole($role);
                }
            }

            // Send invitation email
            try {
                $invitationUrl = route('invitation.accept', ['token' => $token]);
                $companyName = $user->company_name ?? $user->name;
                Mail::to($newUser->email)->send(new \App\Mail\TeamInvitationMail($newUser, $invitationUrl, $companyName));
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('Failed to send invitation email to ' . $newUser->email . ': ' . $e->getMessage());
            }
        }

        // Mark onboarding as complete after final step
        $user->update([
            'onboarding_completed_at' => now(),
        ]);

        return redirect()->route('dashboard');
    }

    /**
     * Skip the current step and move to the next one.
     * Accepts a 'current_step' parameter to determine where to go.
     */
    public function skip(Request $request): RedirectResponse
    {
        $user = Auth::user();

        // Must have completed step 1 (company name) — step 1 has no skip
        if (!$user->company_name) {
            return redirect()->route('onboarding.company');
        }

        $currentStep = $request->input('current_step', 'plan');

        if ($currentStep === 'plan') {
            // Activate the default plan if they skip it
            $user->update([
                'plan_is_active' => 1,
            ]);

            // Skip plan → keep default plan, go to roles (step 3)
            return redirect()->route('onboarding.roles');
        }

        if ($currentStep === 'roles') {
            // Skip roles → go to members (step 4)
            return redirect()->route('onboarding.members');
        }

        // Skip members → complete onboarding and go to dashboard
        $user->update([
            'onboarding_completed_at' => now(),
        ]);

        return redirect()->route('dashboard');
    }

    /**
     * Create default "Admin" and "Sales Manager" roles for a new company.
     */
    private function createDefaultRoles(User $user): void
    {
        // Skip if roles already exist for this company
        if (Role::where('created_by', $user->id)->exists()) {
            return;
        }

        $allPermissions = Permission::all();

        // Role names must be globally unique in Spatie, so append company ID
        // Labels stay human-readable

        // 1. Admin role — full access to everything
        $adminRole = Role::create([
            'name' => 'admin-' . $user->id,
            'label' => 'Admin',
            'description' => 'Full access to all features and settings',
            'guard_name' => 'web',
            'created_by' => $user->id,
        ]);
        $adminRole->syncPermissions($allPermissions);

        // 2. Sales Manager role — sales-focused permissions
        $salesPermissions = Permission::whereIn('name', [
            // Dashboard
            'manage-dashboard',
            'view-dashboard',
            // Contacts
            'manage-contacts',
            'view-contacts',
            'create-contacts',
            'edit-contacts',
            'delete-contacts',
            'toggle-status-contacts',
            // Leads
            'manage-leads',
            'view-leads',
            'create-leads',
            'edit-leads',
            'delete-leads',
            'toggle-status-leads',
            'convert-leads',
            'export-leads',
            'import-leads',
            'manage-lead-statuses',
            'view-lead-statuses',
            'manage-lead-sources',
            'view-lead-sources',
            // Opportunities
            'manage-opportunities',
            'view-opportunities',
            'create-opportunities',
            'edit-opportunities',
            'delete-opportunities',
            'toggle-status-opportunities',
            'manage-opportunity-stages',
            'view-opportunity-stages',
            'manage-opportunity-sources',
            'view-opportunity-sources',
            // Accounts
            'manage-accounts',
            'view-accounts',
            'create-accounts',
            'edit-accounts',
            'delete-accounts',
            'toggle-status-accounts',
            'manage-account-types',
            'view-account-types',
            'manage-account-industries',
            'view-account-industries',
            // Products
            'manage-products',
            'view-products',
            'create-products',
            'edit-products',
            'manage-categories',
            'view-categories',
            'manage-brands',
            'view-brands',
            // Quotes & Orders
            'manage-quotes',
            'view-quotes',
            'create-quotes',
            'edit-quotes',
            'delete-quotes',
            'toggle-status-quotes',
            'export-quotes',
            'manage-sales-orders',
            'view-sales-orders',
            'create-sales-orders',
            'edit-sales-orders',
            'delete-sales-orders',
            'toggle-status-sales-orders',
            'export-sales-orders',
            'manage-invoices',
            'view-invoices',
            'create-invoices',
            'edit-invoices',
            'delete-invoices',
            'toggle-status-invoices',
            'export-invoices',
            // Activities
            'manage-meetings',
            'view-meetings',
            'create-meetings',
            'edit-meetings',
            'delete-meetings',
            'manage-calls',
            'view-calls',
            'create-calls',
            'edit-calls',
            'delete-calls',
            'manage-calendar',
            'view-calendar',
            // Documents
            'manage-documents',
            'view-documents',
            'create-documents',
            'edit-documents',
            'delete-documents',
            'manage-document-folders',
            'view-document-folders',
            'create-document-folders',
            'manage-document-types',
            'view-document-types',
            // Projects & Tasks
            'manage-projects',
            'view-projects',
            'create-projects',
            'edit-projects',
            'manage-project-tasks',
            'view-project-tasks',
            'create-project-tasks',
            'edit-project-tasks',
            'toggle-status-project-tasks',
            // Reports
            'manage-reports',
            'view-lead-reports',
            'view-sales-reports',
            'view-customer-reports',
            // Media
            'manage-own-media',
            'create-media',
            'view-media',
            'download-media',
        ])->get();

        $salesManagerRole = Role::create([
            'name' => 'sales-manager-' . $user->id,
            'label' => 'Sales Manager',
            'description' => 'Access to sales, contacts, leads, opportunities, and reporting',
            'guard_name' => 'web',
            'created_by' => $user->id,
        ]);
        $salesManagerRole->syncPermissions($salesPermissions);
    }
}
