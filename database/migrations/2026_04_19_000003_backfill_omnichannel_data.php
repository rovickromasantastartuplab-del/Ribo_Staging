<?php
/**
 * Migration created at 2026-04-19 12:10:00 to backfill existing Gmail data into ChannelAccount structure.
 */
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $gmailAccounts = DB::table('gmail_accounts')->get();

        foreach ($gmailAccounts as $account) {
            // 1. Create ChannelAccount
            $channelAccountId = DB::table('channel_accounts')->insertGetId([
                'user_id' => $account->user_id,
                'type' => 'gmail',
                'email_address' => $account->gmail_address,
                'configuration' => json_encode([
                    'access_token' => $account->access_token,
                    'refresh_token' => $account->refresh_token,
                    'token_expires_at' => $account->token_expires_at,
                    'google_id' => $account->google_id,
                    'last_history_id' => $account->last_history_id,
                    'scopes' => $account->scopes ?? null,
                ]),
                'sync_status' => $account->sync_status,
                'sync_error' => $account->sync_error,
                'last_sync_at' => $account->last_sync_at,
                'created_at' => $account->created_at,
                'updated_at' => $account->updated_at,
            ]);

            // 2. Update existing threads
            DB::table('email_threads')
                ->where('gmail_account_id', $account->id)
                ->update([
                    'channel_account_id' => $channelAccountId,
                    'channel_type' => 'gmail',
                ]);

            // 3. Update existing messages (optional, mainly for identification)
            // We use thread_id for messages, so we just need to ensure thread is linked.
            // But we can update the external identifiers if they weren't matched.
            DB::table('email_messages')
                ->join('email_threads', 'email_messages.email_thread_id', '=', 'email_threads.id')
                ->where('email_threads.channel_account_id', $channelAccountId)
                ->update([
                    'email_messages.external_message_id' => DB::raw('email_messages.gmail_message_id')
                ]);
        }

        if ($gmailAccounts->count() > 0) {
            Log::info("Backfilled {$gmailAccounts->count()} Gmail accounts into channel_accounts.");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // To reverse, we'd need to clear the channel_accounts table and reset thread/message columns.
        // However, since we're additive and didn't delete the old tables, we don't necessarily need to wipe them.
        DB::table('channel_accounts')->where('type', 'gmail')->delete();
        DB::table('email_threads')->update(['channel_account_id' => null, 'channel_type' => null]);
        DB::table('email_messages')->update(['external_message_id' => null]);
    }
};
