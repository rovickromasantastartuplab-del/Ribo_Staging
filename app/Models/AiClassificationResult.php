<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class AiClassificationResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'lead_event_id',
        'suggested_stage_label',
        'mapped_stage_id',
        'confidence_score',
        'reasons_json',
        'model_version',
    ];

    protected $casts = [
        'reasons_json' => 'array',
        'confidence_score' => 'integer',
    ];

    public function leadEvent()
    {
        return $this->belongsTo(LeadEvent::class);
    }

    public function mappedStage()
    {
        return $this->belongsTo(OpportunityStage::class, 'mapped_stage_id');
    }
}
