<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiReportJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'created_by',
        'email_thread_id',
        'contact_id',
        'scope',
        'status',
        'request_payload_json',
        'context_payload_json',
        'result_payload_json',
        'metadata_json',
        'error_message',
        'requested_at',
        'completed_at',
    ];

    protected $casts = [
        'request_payload_json' => 'array',
        'context_payload_json' => 'array',
        'result_payload_json' => 'array',
        'metadata_json' => 'array',
        'requested_at' => 'datetime',
        'completed_at' => 'datetime',
    ];
}
