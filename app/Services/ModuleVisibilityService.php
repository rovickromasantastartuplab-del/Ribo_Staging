<?php

namespace App\Services;

class ModuleVisibilityService
{
    /**
     * All CRM modules that can be toggled by the superadmin.
     * Key: used in routes/DB. Value: display label.
     */
    public static function modules(): array
    {
        return [
            'leads'               => 'Leads',
            'opportunities'       => 'Opportunities',
            'contacts'            => 'Contacts',
            'accounts'            => 'Accounts',
            'conversations'       => 'Conversations',
            'campaigns'           => 'Campaigns',
            'projects'            => 'Projects',
            'quotes'              => 'Quotes',
            'sales_orders'        => 'Sales Orders',
            'invoices'            => 'Invoices',
            'delivery_orders'     => 'Delivery Orders',
            'return_orders'       => 'Return Orders',
            'purchase_orders'     => 'Purchase Orders',
            'receipt_orders'      => 'Receipt Orders',
            'products'            => 'Products',
            'documents'           => 'Documents',
            'meetings'            => 'Meetings',
            'calls'               => 'Calls',
            'cases'               => 'Cases',
            'reports'             => 'Reports',
            'calendar'            => 'Calendar',
            'media_library'       => 'Media Library',
            'referral'            => 'Referral Program',
            'notification_templates' => 'Notification Templates',
        ];
    }

    /**
     * Get the globally disabled modules as an array of keys.
     */
    public static function getDisabledModules(): array
    {
        $superAdmin = \App\Models\User::where('type', 'superadmin')->first();
        if (!$superAdmin) {
            return [];
        }

        $json = \App\Models\Setting::where('user_id', $superAdmin->id)
            ->where('key', 'disabled_modules')
            ->value('value');

        return json_decode($json, true) ?? [];
    }

    /**
     * Get the effective disabled modules for a specific user, considering plan overrides.
     */
    public static function getEffectiveDisabledModules(?\App\Models\User $user): array
    {
        $disabled = self::getDisabledModules();

        if (!$user) {
            return $disabled;
        }

        // Resolve the company: if the user is a company, use them directly.
        // If the user is a staff member, use their creator (the company).
        $company = $user->type === 'company'
            ? $user
            : \App\Models\User::find($user->created_by);

        // Apply per-company module overrides from CompanyFeatureFlag
        if ($company) {
            $allModuleKeys = array_keys(self::modules());

            $flags = \App\Models\CompanyFeatureFlag::where('company_id', $company->id)
                ->whereIn('feature_key', $allModuleKeys)
                ->get()
                ->keyBy('feature_key');

            foreach ($allModuleKeys as $key) {
                if ($flags->has($key)) {
                    $isEnabled = $flags->get($key)->is_enabled;
                    if ($isEnabled) {
                        // Remove from disabled if the override enables it
                        $disabled = array_values(array_filter($disabled, fn($k) => $k !== $key));
                    } else {
                        // Add to disabled if the override disables it
                        if (!in_array($key, $disabled)) {
                            $disabled[] = $key;
                        }
                    }
                }
                // No flag row = keep global default as-is
            }
        }

        // E-commerce feature overrides visibility for specific modules
        if ($user->hasFeature('ecommerce')) {
            $ecommerceModules = [
                'delivery_orders',
                'purchase_orders',
                'receipt_orders',
                'return_orders',
            ];
            $disabled = array_values(array_diff($disabled, $ecommerceModules));
        }

        return $disabled;
    }

    /**
     * Persist the disabled modules list globally.
     */
    public static function setDisabledModules(array $disabled): void
    {
        $superAdmin = \App\Models\User::where('type', 'superadmin')->first();
        if (!$superAdmin) {
            return;
        }

        \App\Models\Setting::updateOrCreate(
            ['user_id' => $superAdmin->id, 'key' => 'disabled_modules'],
            ['value' => json_encode(array_values($disabled))]
        );
    }
}
