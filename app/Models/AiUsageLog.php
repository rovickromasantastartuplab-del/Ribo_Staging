<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiUsageLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'created_by',
        'email_thread_id',
        'feature',
        'model_version',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'estimated_cost',
        'metadata_json',
        'requested_at',
    ];

    protected $casts = [
        'estimated_cost' => 'decimal:6',
        'metadata_json' => 'array',
        'requested_at' => 'datetime',
    ];
}
