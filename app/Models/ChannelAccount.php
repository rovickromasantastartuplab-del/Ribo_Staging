<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChannelAccount extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'email_address',
        'configuration',
        'sync_status',
        'sync_error',
        'last_sync_at',
    ];

    protected function casts(): array
    {
        return [
            'configuration' => 'encrypted:json',
            'last_sync_at' => 'datetime',
        ];
    }

    /**
     * The company owner that owns this channel account.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Email threads synced from this channel.
     */
    public function threads(): HasMany
    {
        return $this->hasMany(EmailThread::class, 'channel_account_id');
    }

    /**
     * Get a specific configuration key.
     */
    public function getConfig(string $key, $default = null)
    {
        return $this->configuration[$key] ?? $default;
    }
}
