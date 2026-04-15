<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailClickLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'gmail_message_id',
        'recipient_email',
        'url',
        'ip_address',
        'clicked_at',
    ];

    protected function casts(): array
    {
        return [
            'clicked_at' => 'datetime',
        ];
    }
}
