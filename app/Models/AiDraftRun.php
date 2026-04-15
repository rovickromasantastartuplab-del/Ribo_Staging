<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiDraftRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'created_by',
        'email_thread_id',
        'contact_id',
        'prompt',
        'tone',
        'subject',
        'body',
        'status',
        'model_version',
        'prompt_version',
        'generated_at',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
    ];
}
