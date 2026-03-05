<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class LeadEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'lead_id',
        'contact_id',
        'channel',
        'external_event_id',
        'external_actor_key',
        'received_at',
        'summary_text',
        'snippet_text',
        'has_media',
        'media_type',
        'payload_min_json',
    ];

    protected $casts = [
        'received_at' => 'datetime',
        'has_media' => 'boolean',
        'payload_min_json' => 'array',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function aiClassificationResult()
    {
        return $this->hasOne(AiClassificationResult::class);
    }
}
