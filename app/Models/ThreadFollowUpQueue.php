<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThreadFollowUpQueue extends BaseModel
{
    use HasFactory;

    protected $table = 'thread_follow_up_queue';

    protected $fillable = [
        'thread_follow_up_stage_id',
        'recipient_email',
        'gmail_thread_id',
        'gmail_message_id',
        'status',
        'scheduled_at',
        'sent_at',
        'cancelled_reason',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    /**
     * The follow-up stage this queue item belongs to.
     */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(ThreadFollowUpStage::class, 'thread_follow_up_stage_id');
    }
}
