<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WeddingSupplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'category_id',
        'email',
        'phone',
        'telephone',
        'website',
        'address',
        'facebook',
        'tiktok',
        'available_contact_time',
    ];

    public function category()
    {
        return $this->belongsTo(WeddingSupplierCategory::class, 'category_id');
    }

    public function contacts()
    {
        return $this->hasMany(WeddingSupplierContact::class, 'supplier_id');
    }
}
