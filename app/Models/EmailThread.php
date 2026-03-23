<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailThread extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'gmail_account_id',
        'gmail_thread_id',
        'subject',
        'snippet',
        'participants',
        'message_count',
        'last_message_at',
        'is_read',
        'labels',
        'created_by',
        'status',
        'priority',
        'follow_up_at',
    ];

    protected function casts(): array
    {
        return [
            'participants' => 'array',
            'labels' => 'array',
            'last_message_at' => 'datetime',
            'is_read' => 'boolean',
            'follow_up_at' => 'datetime',
        ];
    }

    /**
     * The Gmail account this thread belongs to.
     */
    public function gmailAccount(): BelongsTo
    {
        return $this->belongsTo(GmailAccount::class);
    }

    /**
     * Messages within this thread.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(EmailMessage::class)->orderBy('sent_at', 'asc');
    }

    /**
     * Users assigned to this thread.
     */
    public function assignments()
    {
        return $this->belongsToMany(User::class, 'email_thread_assignments', 'email_thread_id', 'user_id')
            ->withTimestamps();
    }

    /**
     * Check if a user is assigned to this thread.
     */
    public function isAssignedTo(User $user): bool
    {
        return $this->assignments()->where('user_id', $user->id)->exists();
    }

    /**
     * The latest message in this thread.
     */
    public function latestMessage()
    {
        return $this->hasOne(EmailMessage::class)->latestOfMany('sent_at');
    }

    /**
     * Leads linked to this email thread.
     */
    public function leads()
    {
        return $this->morphedByMany(Lead::class, 'email_threadable', 'email_threadables')
            ->withPivot('matched_via')
            ->withTimestamps();
    }

    /**
     * Contacts linked to this email thread.
     */
    public function contacts()
    {
        return $this->morphedByMany(Contact::class, 'email_threadable', 'email_threadables')
            ->withPivot('matched_via')
            ->withTimestamps();
    }
}
