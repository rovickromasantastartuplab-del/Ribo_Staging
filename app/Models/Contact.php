<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    protected $fillable = [
        'name',
        'email',
        'phone',
        'position',
        'address',
        'status',
        'account_id',
        'created_by',
        'assigned_to',
    ];

    /**
     * The Account this contact belongs to.
     */
    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * The User this contact is assigned to.
     */
    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * The User who created this contact.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Cases linked to this contact.
     */
    public function cases()
    {
        return $this->hasMany(CaseModel::class, 'contact_id');
    }

    /**
     * Quotes where this contact is the billing contact.
     */
    public function quotes()
    {
        return $this->hasMany(Quote::class, 'billing_contact_id');
    }
}
