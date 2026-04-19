<?php

namespace App\Jobs;

use App\Models\ChannelAccount;
use App\Services\Omnichannel\MailboxManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncChannelAccountJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying.
     */
    public int $backoff = 30;

    /**
     * The number of seconds after which the job's unique lock will be released.
     */
    public int $uniqueFor = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $channelAccountId
    ) {}

    /**
     * The unique ID of the job (prevents duplicate jobs for the same account).
     */
    public function uniqueId(): string
    {
        return 'channel_sync_' . $this->channelAccountId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $account = ChannelAccount::find($this->channelAccountId);

        if (!$account) {
            Log::warning('SyncChannelAccountJob: account not found', [
                'channel_account_id' => $this->channelAccountId,
            ]);
            return;
        }

        Log::info('SyncChannelAccountJob: Starting sync', [
            'channel_account_id' => $this->channelAccountId,
            'type' => $account->type,
        ]);

        // Update sync status
        $account->update(['sync_status' => 'syncing']);

        try {
            $driver = MailboxManager::resolve($account);
            $stats = $driver->syncInbound($account);

            // Broadcast completion for real-time UI updates
            // (Maintaining legacy event name for now to avoid breaking frontend)
            if ($account->type === 'gmail') {
                \App\Events\GmailSyncCompleted::dispatch($this->channelAccountId, $account->user->creatorId());
            }

            Log::info('Channel sync completed', [
                'channel_account_id' => $this->channelAccountId,
                'synced' => $stats['synced'] ?? 0,
                'errors' => $stats['errors'] ?? 0,
            ]);
        } catch (\Exception $e) {
            Log::error('Channel sync job failed', [
                'channel_account_id' => $this->channelAccountId,
                'error' => $e->getMessage(),
            ]);

            $account->update([
                'sync_status' => 'error',
                'sync_error' => $e->getMessage(),
            ]);
        }
    }
}
