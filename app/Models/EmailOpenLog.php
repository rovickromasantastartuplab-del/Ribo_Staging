<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailOpenLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'gmail_message_id',
        'recipient_email',
        'ip_address',
        'opened_at',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
        ];
    }
}
