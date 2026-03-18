<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class RenewGmailWatch extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:renew-gmail-watch';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Renew the 7-day watch limit for all connected Google Pub/Sub webhooks';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Gmail Pub/Sub watch renewal process...');

        $accounts = \App\Models\GmailAccount::all();
        $successful = 0;
        $failed = 0;

        foreach ($accounts as $account) {
            try {
                $service = new \App\Services\GmailService($account);
                
                // Ensure token is valid before establishing the watch
                if ($service->refreshTokenIfNeeded()) {
                    if ($service->watchInbox()) {
                        $successful++;
                        $this->line("Successfully renewed watch for {$account->gmail_address}");
                    } else {
                        $failed++;
                        $this->error("Failed to renew watch for {$account->gmail_address}");
                    }
                } else {
                    $failed++;
                    $this->error("Could not refresh token for {$account->gmail_address}");
                }
            } catch (\Exception $e) {
                $failed++;
                $this->error("Error processing {$account->gmail_address}: " . $e->getMessage());
            }
        }

        $this->info("Renewal process completed. Successful: {$successful}, Failed: {$failed}");
    }
}
