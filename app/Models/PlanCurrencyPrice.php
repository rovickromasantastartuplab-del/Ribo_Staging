<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanCurrencyPrice extends Model
{
    protected $fillable = [
        'plan_id',
        'currency_code',
        'monthly_price',
        'yearly_price',
    ];

    protected $casts = [
        'monthly_price' => 'float',
        'yearly_price' => 'float',
    ];

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }
}
