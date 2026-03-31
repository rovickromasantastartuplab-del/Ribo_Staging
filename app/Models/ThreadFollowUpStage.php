<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ThreadFollowUpStage extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'email_thread_id',
        'stage_number',
        'trigger_type',
        'delay_days',
        'subject',
        'body',
    ];

    /**
     * The email thread this stage belongs to.
     */
    public function emailThread(): BelongsTo
    {
        return $this->belongsTo(EmailThread::class);
    }

    /**
     * Queue items scheduled for this stage.
     */
    public function queueItems(): HasMany
    {
        return $this->hasMany(ThreadFollowUpQueue::class);
    }
}
