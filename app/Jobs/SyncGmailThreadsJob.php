<?php

namespace App\Jobs;

use App\Models\GmailAccount;
use App\Services\GmailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncGmailThreadsJob implements ShouldQueue
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
     * Create a new job instance.
     */
    public function __construct(
        public int $gmailAccountId
    ) {}

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

            // Perform the sync
            $stats = $service->syncThreads(50);

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
