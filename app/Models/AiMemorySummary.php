<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiMemorySummary extends Model
{
    use HasFactory;

    protected $fillable = [
        'created_by',
        'contact_id',
        'relationship_summary',
        'relationship_strength',
        'memory_points_json',
        'model_version',
        'prompt_version',
        'summarized_at',
    ];

    protected $casts = [
        'memory_points_json' => 'array',
        'summarized_at' => 'datetime',
    ];
}
