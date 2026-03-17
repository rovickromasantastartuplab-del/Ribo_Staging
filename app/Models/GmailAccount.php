<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GmailAccount extends BaseModel
{
    use HasFactory;

    /**
     * Override BaseModel's permission scope to use user_id (this table has no created_by column).
     */
    public function scopeWithPermissionCheck($query)
    {
        if (!auth()->check()) {
            return $query;
        }

        $user = auth()->user();

        if ($user->hasRole(['superadmin'])) {
            return $query;
        }

        return $query->where('user_id', $user->creatorId());
    }

    protected $fillable = [
        'user_id',
        'gmail_address',
        'google_id',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'scopes',
        'last_sync_at',
        'last_history_id',
        'sync_status',
        'sync_error',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'last_sync_at' => 'datetime',
        ];
    }

    /**
     * The company owner that owns this Gmail connection.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Email threads synced from this Gmail account.
     */
    public function threads(): HasMany
    {
        return $this->hasMany(EmailThread::class);
    }

    /**
     * Check if the access token has expired.
     */
    public function isTokenExpired(): bool
    {
        if (!$this->token_expires_at) {
            return true;
        }

        return $this->token_expires_at->isPast();
    }

    /**
     * Check if the account needs reconnection (no refresh token or persistent error).
     */
    public function needsReconnect(): bool
    {
        return empty($this->refresh_token) || $this->sync_status === 'error';
    }
}
