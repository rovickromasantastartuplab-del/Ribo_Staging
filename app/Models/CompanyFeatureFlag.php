<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanyFeatureFlag extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'feature_key',
        'is_enabled',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(User::class, 'company_id');
    }
}
