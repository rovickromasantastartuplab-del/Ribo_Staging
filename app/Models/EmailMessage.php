<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailMessage extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'email_thread_id',
        'gmail_message_id',
        'from_email',
        'from_name',
        'to_emails',
        'cc_emails',
        'subject',
        'body_preview',
        'body_html',
        'sent_at',
        'gmail_labels',
        'message_id_header',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'to_emails' => 'array',
            'cc_emails' => 'array',
            'gmail_labels' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    /**
     * The thread this message belongs to.
     */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(EmailThread::class, 'email_thread_id');
    }
}
