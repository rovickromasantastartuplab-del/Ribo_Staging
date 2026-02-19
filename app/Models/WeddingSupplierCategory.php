<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WeddingSupplierCategory extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function suppliers()
    {
        return $this->hasMany(WeddingSupplier::class, 'category_id');
    }
}
