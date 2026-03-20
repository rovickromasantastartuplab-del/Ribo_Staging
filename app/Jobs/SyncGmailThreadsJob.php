<?php

namespace App\Jobs;

use App\Models\GmailAccount;
use App\Services\GmailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncGmailThreadsJob implements ShouldQueue, ShouldBeUnique
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
        public int $gmailAccountId
    ) {}

    /**
     * The unique ID of the job (prevents duplicate jobs for the same account).
     */
    public function uniqueId(): string
    {
        return 'gmail_sync_' . $this->gmailAccountId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $account = GmailAccount::find($this->gmailAccountId);

        if (!$account) {
            Log::warning('SyncGmailThreadsJob: Gmail account not found', [
                'gmail_account_id' => $this->gmailAccountId,
            ]);
            return;
        }

        Log::info('SyncGmailThreadsJob: Starting sync', [
            'gmail_account_id' => $this->gmailAccountId,
            'is_sync' => $this->isSync ?? 'unknown'
        ]);

        // Update sync status
        $account->update(['sync_status' => 'syncing']);

        try {
            $service = new GmailService($account);

            // Refresh token if needed
            if (!$service->refreshTokenIfNeeded()) {
                $account->update([
                    'sync_status' => 'error',
                    'sync_error' => 'Token refresh failed. Please reconnect your Gmail account.',
                ]);
                return;
            }

            // Try incremental sync first (much faster, fewer API calls)
            // Falls back to full sync if no historyId baseline exists or it expired
            $stats = $service->incrementalSync();

            if ($stats === null) {
                Log::info('No historyId baseline, performing full sync', [
                    'gmail_account_id' => $this->gmailAccountId,
                ]);
                $stats = $service->syncThreads(50);
            }

            // Broadcast completion for real-time UI updates to the Company/Owner channel
            \App\Events\GmailSyncCompleted::dispatch($this->gmailAccountId, $account->user->creatorId());

            Log::info('Gmail sync completed', [
                'gmail_account_id' => $this->gmailAccountId,
                'synced' => $stats['synced'],
                'errors' => $stats['errors'],
            ]);
        } catch (\Exception $e) {
            Log::error('Gmail sync job failed', [
                'gmail_account_id' => $this->gmailAccountId,
                'error' => $e->getMessage(),
            ]);

            $account->update([
                'sync_status' => 'error',
                'sync_error' => $e->getMessage(),
            ]);
        }
    }
}
