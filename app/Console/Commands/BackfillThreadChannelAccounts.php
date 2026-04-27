<?php

namespace App\Console\Commands;

use App\Models\ChannelAccount;
use App\Models\EmailThread;
use Illuminate\Console\Command;

class BackfillThreadChannelAccounts extends Command
{
    protected $signature = 'conversations:backfill-thread-mailboxes {--dry-run : Show matches without saving}';

    protected $description = 'Backfill missing email thread channel_account_id values from mailbox participants.';

    public function handle(): int
    {
        $backfilled = 0;
        $dryRun = (bool) $this->option('dry-run');

        EmailThread::query()
            ->whereNull('channel_account_id')
            ->with('messages')
            ->orderBy('id')
            ->chunkById(100, function ($threads) use (&$backfilled, $dryRun) {
                foreach ($threads as $thread) {
                    $account = $this->matchAccount($thread);

                    if (!$account) {
                        continue;
                    }

                    $backfilled++;

                    if (!$dryRun) {
                        $thread->update([
                            'channel_account_id' => $account->id,
                            'channel_type' => $account->type,
                        ]);
                    }
                }
            });

        $suffix = $dryRun ? ' candidate(s).' : ' thread mailbox link(s).';
        $this->info("Backfilled {$backfilled}{$suffix}");

        return self::SUCCESS;
    }

    private function matchAccount(EmailThread $thread): ?ChannelAccount
    {
        $emails = collect($thread->participants ?? [])
            ->merge($thread->messages->pluck('from_email'))
            ->merge($thread->messages->flatMap(fn ($message) => $message->to_emails ?? []))
            ->merge($thread->messages->flatMap(fn ($message) => $message->cc_emails ?? []))
            ->merge($thread->messages->flatMap(fn ($message) => $message->bcc_emails ?? []))
            ->filter()
            ->map(fn ($email) => strtolower(trim($email)))
            ->unique()
            ->values();

        if ($emails->isEmpty()) {
            return null;
        }

        return ChannelAccount::query()
            ->where('user_id', $thread->created_by)
            ->whereIn('email_address', $emails)
            ->orderByRaw("CASE WHEN sync_status = 'active' THEN 0 ELSE 1 END")
            ->first();
    }
}
