<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadCaptureSubmission extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'lead_capture_form_id',
        'created_by',
        'lead_id',
        'outcome',
        'payload',
        'ip_address',
        'user_agent',
        'submitted_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'submitted_at' => 'datetime',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(LeadCaptureForm::class, 'lead_capture_form_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
