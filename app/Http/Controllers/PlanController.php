<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use App\Models\Plan;
use App\Models\PlanCurrencyPrice;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PlanController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        // Company users see only active plans
        if ($user->type !== 'superadmin') {
            return $this->companyPlansView($request);
        }

        // Admin view
        $billingCycle = $request->input('billing_cycle', 'monthly');

        $hasDefaultPlan = Plan::where('is_default', true)->count() > 0;
        $settings = settings();

        $currencyConfig = \App\Services\PlanPricingService::getGlobalCurrency();

        $formattedPlans = \App\Services\PlanPricingService::getFormattedPlans(null, $billingCycle, true);
        $plans = \App\Services\PlanPricingService::applyRecommendation($formattedPlans)->toArray();

        return Inertia::render('plans/index', [
            'plans' => $plans,
            'billingCycle' => $billingCycle,
            'hasDefaultPlan' => $hasDefaultPlan,
            'isAdmin' => true,
            'currency' => $currencyConfig['code'],
            'currencySymbol' => $currencyConfig['symbol']
        ]);
    }

    /**
     * Toggle plan status
     */
    public function toggleStatus(Plan $plan)
    {
        $plan->is_plan_enable = $plan->is_plan_enable === 'on' ? 'off' : 'on';
        $plan->save();

        return back();
    }

    /**
     * Show the form for creating a new plan
     */
    public function create()
    {
        $hasDefaultPlan = Plan::where('is_default', true)->exists();

        return Inertia::render('plans/create', [
            'hasDefaultPlan' => $hasDefaultPlan,
            'availableCurrencies' => Currency::orderBy('code')->get(['id', 'code', 'symbol', 'name']),
        ]);
    }

    /**
     * Store a newly created plan
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:plans',
            'price' => 'required|numeric|min:0',
            'yearly_price' => 'nullable|numeric|min:0',
            'duration' => 'required|string',
            'description' => 'nullable|string',
            'max_users' => 'required|integer|min:0',
            'max_projects' => 'required|integer|min:0',
            'max_contacts' => 'required|integer|min:0',
            'max_accounts' => 'required|integer|min:0',
            'storage_limit' => 'required|numeric|min:0',
            'enable_branding' => 'nullable|in:on,off',
            'enable_chatgpt' => 'nullable|in:on,off',
            'enable_ecommerce' => 'nullable|in:on,off',
            'enable_wedding_suppliers' => 'nullable|in:on,off',
            'module' => 'nullable|array',
            'is_trial' => 'nullable|in:on,off',
            'trial_day' => 'nullable|integer|min:0',
            'is_plan_enable' => 'nullable|in:on,off',
            'is_default' => 'nullable|boolean',
            'currency_prices' => 'nullable|array',
            'currency_prices.*.code' => 'required_with:currency_prices|string|max:10',
            'currency_prices.*.monthly' => 'required_with:currency_prices|numeric|min:0',
            'currency_prices.*.yearly' => 'nullable|numeric|min:0',
        ]);

        // Set default values for nullable fields
        $validated['enable_branding'] = $validated['enable_branding'] ?? 'on';
        $validated['enable_chatgpt'] = $validated['enable_chatgpt'] ?? 'off';
        $validated['enable_ecommerce'] = $validated['enable_ecommerce'] ?? 'off';
        $validated['enable_wedding_suppliers'] = $validated['enable_wedding_suppliers'] ?? 'off';
        $validated['is_trial'] = $validated['is_trial'] ?? null;
        $validated['is_plan_enable'] = $validated['is_plan_enable'] ?? 'on';
        $validated['is_default'] = $validated['is_default'] ?? false;

        // If yearly_price is not provided, calculate it as 80% of monthly price * 12
        if (!isset($validated['yearly_price']) || $validated['yearly_price'] === null) {
            $validated['yearly_price'] = $validated['price'] * 12 * 0.8;
        }

        // If this plan is set as default, remove default status from other plans
        if ($validated['is_default']) {
            Plan::where('is_default', true)->update(['is_default' => false]);
        }

        $features = is_array($validated['module'] ?? null) ? $validated['module'] : [];
        if ($validated['enable_branding'] === 'on' && !in_array('branding', $features, true)) {
            $features[] = 'branding';
        }
        if ($validated['enable_chatgpt'] === 'on' && !in_array('ai_integration', $features, true)) {
            $features[] = 'ai_integration';
        }
        if ($validated['enable_ecommerce'] === 'on' && !in_array('ecommerce', $features, true)) {
            $features[] = 'ecommerce';
        }
        if ($validated['enable_wedding_suppliers'] === 'on' && !in_array('wedding_suppliers_module', $features, true)) {
            $features[] = 'wedding_suppliers_module';
        }
        if ($validated['is_trial'] === 'on' && !in_array('trial', $features, true)) {
            $features[] = 'trial';
        }
        $validated['module'] = array_values(array_unique($features));

        // Create the plan
        $currencyPricesData = $request->input('currency_prices', []);
        $plan = Plan::create($validated);

        // Save currency-specific prices
        foreach ($currencyPricesData as $cp) {
            if (($cp['monthly'] ?? 0) > 0) {
                PlanCurrencyPrice::create([
                    'plan_id' => $plan->id,
                    'currency_code' => strtoupper($cp['code']),
                    'monthly_price' => $cp['monthly'],
                    'yearly_price' => $cp['yearly'] ?? null,
                ]);
            }
        }

        return redirect()->route('plans.index')->with('success', __('Plan created successfully.'));
    }

    /**
     * Show the form for editing a plan
     */
    public function edit(Plan $plan)
    {
        $otherDefaultPlanExists = Plan::where('is_default', true)
            ->where('id', '!=', $plan->id)
            ->exists();

        // Build a plan array with the derived enable_wedding_suppliers field
        // so the form toggle correctly reflects the stored module state
        $module = is_array($plan->module) ? $plan->module : [];
        $planData = array_merge($plan->toArray(), [
            'enable_ecommerce' => in_array('ecommerce', $module) ? 'on' : 'off',
            'enable_wedding_suppliers' => in_array('wedding_suppliers_module', $module) ? 'on' : 'off',
        ]);

        return Inertia::render('plans/edit', [
            'plan' => $planData,
            'otherDefaultPlanExists' => $otherDefaultPlanExists,
            'availableCurrencies' => Currency::orderBy('code')->get(['id', 'code', 'symbol', 'name']),
            'currencyPrices' => $plan->currencyPrices->map(fn($cp) => [
                'code' => $cp->currency_code,
                'monthly' => $cp->monthly_price,
                'yearly' => $cp->yearly_price,
            ]),
        ]);
    }

    /**
     * Update a plan
     */
    public function update(Request $request, Plan $plan)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:plans,name,' . $plan->id,
            'price' => 'required|numeric|min:0',
            'yearly_price' => 'nullable|numeric|min:0',
            'duration' => 'required|string',
            'description' => 'nullable|string',
            'max_users' => 'required|integer|min:0',
            'max_projects' => 'required|integer|min:0',
            'max_contacts' => 'required|integer|min:0',
            'max_accounts' => 'required|integer|min:0',
            'storage_limit' => 'required|numeric|min:0',
            'enable_branding' => 'nullable|in:on,off',
            'enable_chatgpt' => 'nullable|in:on,off',
            'enable_ecommerce' => 'nullable|in:on,off',
            'enable_wedding_suppliers' => 'nullable|in:on,off',
            'module' => 'nullable|array',
            'is_trial' => 'nullable|in:on,off',
            'trial_day' => 'nullable|integer|min:0',
            'is_plan_enable' => 'nullable|in:on,off',
            'is_default' => 'nullable|boolean',
            'currency_prices' => 'nullable|array',
            'currency_prices.*.code' => 'required_with:currency_prices|string|max:10',
            'currency_prices.*.monthly' => 'required_with:currency_prices|numeric|min:0',
            'currency_prices.*.yearly' => 'nullable|numeric|min:0',
        ]);

        // Set default values for nullable fields
        $validated['enable_branding'] = $validated['enable_branding'] ?? 'on';
        $validated['enable_chatgpt'] = $validated['enable_chatgpt'] ?? 'off';
        $validated['enable_ecommerce'] = $validated['enable_ecommerce'] ?? 'off';
        $validated['enable_wedding_suppliers'] = $validated['enable_wedding_suppliers'] ?? 'off';
        $validated['is_trial'] = $validated['is_trial'] ?? null;
        $validated['is_plan_enable'] = $validated['is_plan_enable'] ?? 'on';
        $validated['is_default'] = $validated['is_default'] ?? false;

        // If yearly_price is not provided, calculate it as 80% of monthly price * 12
        if (!isset($validated['yearly_price']) || $validated['yearly_price'] === null) {
            $validated['yearly_price'] = $validated['price'] * 12 * 0.8;
        }

        // If this plan is set as default, remove default status from other plans
        if ($validated['is_default'] && !$plan->is_default) {
            Plan::where('is_default', true)->update(['is_default' => false]);
        }

        $features = is_array($validated['module'] ?? null) ? $validated['module'] : [];
        if ($validated['enable_branding'] === 'on' && !in_array('branding', $features, true)) {
            $features[] = 'branding';
        }
        if ($validated['enable_chatgpt'] === 'on' && !in_array('ai_integration', $features, true)) {
            $features[] = 'ai_integration';
        }
        if ($validated['enable_ecommerce'] === 'on' && !in_array('ecommerce', $features, true)) {
            $features[] = 'ecommerce';
        }
        if ($validated['enable_wedding_suppliers'] === 'on' && !in_array('wedding_suppliers_module', $features, true)) {
            $features[] = 'wedding_suppliers_module';
        }
        if ($validated['is_trial'] === 'on' && !in_array('trial', $features, true)) {
            $features[] = 'trial';
        }
        $validated['module'] = array_values(array_unique($features));

        // Update the plan
        $currencyPricesData = $request->input('currency_prices', []);
        $plan->update($validated);

        // Upsert currency-specific prices
        foreach ($currencyPricesData as $cp) {
            $code = strtoupper($cp['code']);
            if (($cp['monthly'] ?? 0) > 0) {
                PlanCurrencyPrice::updateOrCreate(
                    ['plan_id' => $plan->id, 'currency_code' => $code],
                    ['monthly_price' => $cp['monthly'], 'yearly_price' => $cp['yearly'] ?? null]
                );
            } else {
                PlanCurrencyPrice::where('plan_id', $plan->id)
                    ->where('currency_code', $code)
                    ->delete();
            }
        }

        return redirect()->route('plans.index')->with('success', __('Plan updated successfully.'));
    }

    /**
     * Delete a plan
     */
    public function destroy(Plan $plan)
    {
        // Don't allow deleting the default plan
        if ($plan->is_default) {
            return back()->with('error', __('Cannot delete the default plan.'));
        }

        // Don't allow deleting plans assigned to users
        if ($plan->users()->count() > 0) {
            return back()->with('error', __('The company has subscribed to this plan, so it cannot be deleted.'));
        }

        $plan->delete();

        return redirect()->route('plans.index')->with('success', __('Plan deleted successfully.'));
    }

    private function companyPlansView(Request $request)
    {
        $user = auth()->user();
        $billingCycle = $request->input('billing_cycle', 'monthly');

        $currencyConfig = \App\Services\PlanPricingService::getGlobalCurrency();

        $formattedPlans = \App\Services\PlanPricingService::getFormattedPlans($user, $billingCycle);
        $plans = \App\Services\PlanPricingService::applyRecommendation($formattedPlans)->toArray();

        return Inertia::render('plans/index', [
            'plans' => $plans,
            'billingCycle' => $billingCycle,
            'currentPlan' => $user->plan,
            'userTrialUsed' => $user->is_trial,
            'currency' => $currencyConfig['code'],
            'currencySymbol' => $currencyConfig['symbol']
        ]);
    }

    public function requestPlan(Request $request)
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'billing_cycle' => 'required|in:monthly,yearly'
        ]);

        $user = auth()->user();

        // Check if user already has a pending request
        $existingRequest = \App\Models\PlanRequest::where('user_id', $user->id)
            ->where('status', 'pending')
            ->first();

        if ($existingRequest) {
            return back()->with('error', __('You already sent request to another plan'));
        }

        $plan = Plan::findOrFail($request->plan_id);

        \App\Models\PlanRequest::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'duration' => $request['billing_cycle'],
            'status' => 'pending'
        ]);

        return back()->with('success', __('Plan request submitted successfully'));
    }

    public function cancelRequest(Request $request)
    {
        $request->validate([
            'request_id' => 'required|exists:plan_requests,id'
        ]);

        $planRequest = \App\Models\PlanRequest::findOrFail($request->request_id);

        $planRequest->delete();

        return back()->with('success', __('Plan request cancelled successfully'));
    }

    public function startTrial(Request $request)
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id'
        ]);

        $user = auth()->user();
        $plan = Plan::findOrFail($request->plan_id);

        if ($user->is_trial || $plan->is_trial !== 'on') {
            return back()->withErrors(['error' => 'Trial not available']);
        }

        $user->update([
            'plan_id' => $plan->id,
            'is_trial' => 1,
            'trial_day' => $plan->trial_day,
            'trial_expire_date' => now()->addDays($plan->trial_day)
        ]);

        return back()->with('success', __('Trial started successfully'));
    }

    public function subscribe(Request $request)
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'billing_cycle' => 'required|in:monthly,yearly'
        ]);

        $user = auth()->user();
        $plan = Plan::findOrFail($request->plan_id);
        $price = $request->billing_cycle === 'yearly' ? $plan->yearly_price : $plan->price;

        $superAdmin = User::where('type', 'superadmin')->first();
        $superAdminSettings = settings($superAdmin?->id);
        $currencyCode = $superAdminSettings['defaultCurrency'] ?? 'USD';

        \App\Models\PlanOrder::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'original_price' => $price,
            'final_price' => $price,
            'currency_code' => $currencyCode,
            'status' => 'pending'
        ]);

    }

    public function freeCheckout(Request $request)
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'billing_cycle' => 'required|in:monthly,yearly',
            'coupon_code' => 'nullable|string'
        ]);

        $plan = Plan::findOrFail($request->plan_id);
        $pricing = calculatePlanPricing($plan, $request->coupon_code, $request->billing_cycle);

        if ($pricing['final_price'] > 0) {
            return response()->json(['success' => false, 'error' => __('This transaction is not free.')]);
        }

        // Generate a synthetic payment ID for free orders to track them
        $paymentId = 'free_' . $plan->id . '_' . time() . '_' . uniqid();

        // Create the free plan order and immediately process it using the helper
        processPaymentSuccess([
            'user_id' => auth()->id(),
            'plan_id' => $plan->id,
            'billing_cycle' => $request->billing_cycle,
            'payment_method' => 'free',
            'payment_id' => $paymentId,
            'coupon_code' => $request->coupon_code,
            'original_price' => $pricing['original_price'],
            'final_price' => $pricing['final_price'],
        ]);

        return response()->json(['success' => true]);
    }
}
