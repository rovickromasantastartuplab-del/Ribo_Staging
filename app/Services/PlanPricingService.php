<?php

namespace App\Services;

use App\Models\User;
use App\Models\Plan;
use App\Models\Currency;

class PlanPricingService
{
    /**
     * Get the active global currency configuration
     */
    public static function getGlobalCurrency()
    {
        $superAdmin = User::where('type', 'superadmin')->first();
        $superAdminSettings = $superAdmin ? settings($superAdmin->id) : settings();

        $currencyCode = $superAdminSettings ? ($superAdminSettings['defaultCurrency'] ?? 'USD') : 'USD';

        $currencySymbol = '$';
        if (!empty($currencyCode)) {
            $currencyData = Currency::where('code', $currencyCode)->first();
            $currencySymbol = $currencyData ? $currencyData->symbol : '$';
        }

        return [
            'code' => $currencyCode,
            'symbol' => $currencySymbol
        ];
    }

    /**
     * Fetch all active plans mapped consistently for frontend components.
     * Contains both monthly and yearly logic so modals can toggle dynamically.
     */
    public static function getFormattedPlans($company = null, $specificCycle = null)
    {
        $currencyConfig = self::getGlobalCurrency();
        $currencyCode = $currencyConfig['code'];
        $currencySymbol = $currencyConfig['symbol'];

        $dbPlans = Plan::with('currencyPrices')->where('is_plan_enable', 'on')->get();

        return $dbPlans->map(function ($plan) use ($currencyCode, $currencySymbol, $company, $specificCycle) {
            $monthlyPrice = $plan->getPriceForCurrency($currencyCode, 'monthly');
            $yearlyPrice = $plan->getPriceForCurrency($currencyCode, 'yearly');

            $features = [];
            if ($plan->enable_chatgpt === 'on')
                $features[] = 'AI Integration';
            if ($plan->enable_branding === 'on')
                $features[] = 'Branding';
            if (is_array($plan->module) && in_array('wedding_suppliers_module', $plan->module)) {
                $features[] = 'Wedding Suppliers';
            }

            // Determine if passing a specific cycle (used by /plans page)
            $activePrice = $specificCycle === 'yearly' ? $yearlyPrice : $monthlyPrice;

            return [
                'id' => $plan->id,
                'name' => $plan->name,
                // Primary price mapped to requested cycle or defaults to monthly
                'price' => $activePrice,
                'formatted_price' => $currencySymbol . number_format($activePrice, 2),
                'formattedPrice' => $currencySymbol . number_format($activePrice, 2), // Alias for backward compatibility on plans.index

                // Static monthly & yearly prices included so local React toggles (UpgradePlanModal) can use them
                'monthly_price' => $monthlyPrice,
                'yearly_price' => $yearlyPrice,
                'formatted_monthly_price' => $currencySymbol . number_format($monthlyPrice, 2),
                'formatted_yearly_price' => $currencySymbol . number_format($yearlyPrice, 2),

                'duration' => $specificCycle === 'yearly' ? 'Yearly' : 'Monthly',
                'description' => $plan->description,
                'trial_days' => $plan->trial_day,
                'features' => $features,
                'stats' => [
                    'users' => $plan->max_users,
                    'projects' => $plan->max_projects,
                    'contacts' => $plan->max_contacts,
                    'accounts' => $plan->max_accounts,
                    'storage' => $plan->storage_limit . ' GB'
                ],
                // Legacy fields preserved for backward compatibility in React logic
                'max_users' => $plan->max_users,
                'max_projects' => $plan->max_projects,
                'max_contacts' => $plan->max_contacts,
                'max_accounts' => $plan->max_accounts,
                'storage_limit' => $plan->storage_limit,
                'enable_branding' => $plan->enable_branding,
                'enable_chatgpt' => $plan->enable_chatgpt,
                'module' => is_array($plan->module) ? $plan->module : [],

                // Status
                'is_current' => $company ? ($company->plan_id == $plan->id) : false,
                'is_trial_available' => $company ? ($plan->is_trial === 'on' && !$company->is_trial) : false,
                'is_default' => $plan->is_default,
                'is_trial' => $plan->is_trial,
                'status' => $plan->is_plan_enable === 'on',
                'recommended' => false
            ];
        });
    }

    /**
     * Set mathematical recommendation on the most subscribed plan
     */
    public static function applyRecommendation($formattedPlansCollection)
    {
        $planSubscriberCounts = Plan::withCount('users')->get()->pluck('users_count', 'id');

        if ($planSubscriberCounts->isNotEmpty()) {
            $mostSubscribedPlanId = $planSubscriberCounts->keys()->sortByDesc(function ($planId) use ($planSubscriberCounts) {
                return $planSubscriberCounts[$planId];
            })->first();

            return $formattedPlansCollection->map(function ($plan) use ($mostSubscribedPlanId) {
                if ($plan['id'] == $mostSubscribedPlanId && $plan['price'] != '0') {
                    $plan['recommended'] = true;
                }
                return $plan;
            });
        }

        return $formattedPlansCollection;
    }
}
