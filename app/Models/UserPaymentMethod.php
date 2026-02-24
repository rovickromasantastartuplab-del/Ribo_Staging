<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UserPaymentMethod extends Model
{
    use HasFactory;

    protected $table = 'hitpay_payment_methods';

    protected $fillable = [
        'user_id',
        'payment_method_id',
        'card_brand',
        'last_4',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
