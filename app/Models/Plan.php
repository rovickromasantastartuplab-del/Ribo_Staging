<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\PlanCurrencyPrice;

class Plan extends Model
{
    protected $fillable = [
        'name',
        'price',
        'yearly_price',
        'duration',
        'description',
        'max_users',
        'max_projects',
        'max_contacts',
        'max_accounts',
        'enable_branding',
        'enable_chatgpt',
        'storage_limit',
        'is_trial',
        'trial_day',
        'is_plan_enable',
        'is_default',
        'module',
    ];

    protected $casts = [
        'themes' => 'array',
        'module' => 'array',
        'is_default' => 'boolean',
        'price' => 'float',
        'yearly_price' => 'float',
    ];

    /**
     * Get the default plan
     *
     * @return Plan|null
     */
    public static function getDefaultPlan()
    {
        return self::where('is_default', true)->first();
    }

    /**
     * Check if the plan is the default plan
     *
     * @return bool
     */
    public function isDefault()
    {
        return (bool) $this->is_default;
    }

    /**
     * Get the price based on billing cycle
     *
     * @param string $cycle 'monthly' or 'yearly'
     * @return float
     */
    public function getPriceForCycle($cycle = 'monthly')
    {
        if ($cycle === 'yearly' && $this->yearly_price) {
            return $this->yearly_price;
        }

        return $this->price;
    }

    /**
     * Get currency-specific price for a given currency code and billing cycle.
     * Falls back to the base price/yearly_price columns if no currency-specific price exists.
     */
    public function getPriceForCurrency(string $currencyCode, string $cycle = 'monthly'): float
    {
        $currencyPrice = $this->currencyPrices
            ->firstWhere('currency_code', strtoupper($currencyCode));

        if ($currencyPrice) {
            if ($cycle === 'yearly' && $currencyPrice->yearly_price !== null) {
                return (float) $currencyPrice->yearly_price;
            }
            return (float) $currencyPrice->monthly_price;
        }

        // Fallback to legacy price columns
        return $this->getPriceForCycle($cycle);
    }

    /**
     * Get currency-specific prices for this plan
     */
    public function currencyPrices()
    {
        return $this->hasMany(PlanCurrencyPrice::class);
    }

    /**
     * Get users subscribed to this plan
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get plan orders for this plan
     */
    public function planOrders()
    {
        return $this->hasMany(PlanOrder::class);
    }
}