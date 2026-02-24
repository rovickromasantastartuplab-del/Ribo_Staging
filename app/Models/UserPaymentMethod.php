<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserPaymentMethod extends Model
{
    protected $fillable = [
        'user_id',
        'payment_method_id',
        'card_brand',
        'last_4',
        'status',
    ];

    /**
     * Get the user that owns this payment method.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope: only active payment methods.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
