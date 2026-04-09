<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiFeedbackLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'created_by',
        'email_thread_id',
        'ai_triage_result_id',
        'error_type',
        'notes',
        'payload_json',
        'logged_at',
    ];

    protected $casts = [
        'payload_json' => 'array',
        'logged_at' => 'datetime',
    ];
}
