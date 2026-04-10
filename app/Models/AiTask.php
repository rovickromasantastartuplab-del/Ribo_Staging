<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'created_by',
        'contact_id',
        'lead_id',
        'email_thread_id',
        'title',
        'description',
        'priority',
        'is_completed',
        'due_at',
        'completed_at',
        'source',
        'metadata_json',
    ];

    protected $casts = [
        'is_completed' => 'boolean',
        'due_at' => 'datetime',
        'completed_at' => 'datetime',
        'metadata_json' => 'array',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }
}
