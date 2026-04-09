<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiTriageResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'created_by',
        'email_thread_id',
        'intent',
        'intent_confidence',
        'priority',
        'success_probability',
        'behavioral_pulse',
        'summary',
        'strategic_action_json',
        'model_version',
        'prompt_version',
        'analyzed_at',
    ];

    protected $casts = [
        'strategic_action_json' => 'array',
        'analyzed_at' => 'datetime',
    ];
}
