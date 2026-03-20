<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use App\Services\StorageConfigService;

class EmailMessage extends BaseModel implements HasMedia
{
    use HasFactory, InteractsWithMedia;

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

    /**
     * Register media collections for email attachments.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('attachments')
            ->useDisk(StorageConfigService::getActiveDisk());
    }

    /**
     * Register media conversions (thumbnails for images).
     */
    public function registerMediaConversions(Media $media = null): void
    {
        if ($media && str_starts_with($media->mime_type ?? '', 'image/')) {
            $this->addMediaConversion('thumb')
                ->width(300)
                ->height(300)
                ->sharpen(10)
                ->performOnCollections('attachments')
                ->nonQueued();
        }
    }
}
