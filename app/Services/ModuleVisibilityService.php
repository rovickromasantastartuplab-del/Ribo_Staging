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
