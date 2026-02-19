<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WeddingSupplierContact extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_id',
        'name',
        'position',
        'phone',
        'email',
    ];

    public function supplier()
    {
        return $this->belongsTo(WeddingSupplier::class, 'supplier_id');
    }
}
