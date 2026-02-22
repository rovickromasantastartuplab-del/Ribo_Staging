<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HitpayWebhookLog extends Model
{
    protected $fillable = [
        'payment_id',
        'status',
        'request_payload',
        'error_message',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'error_message' => 'array',
    ];
}
