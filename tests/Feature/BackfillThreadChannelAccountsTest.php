<?php

use App\Models\ChannelAccount;
use App\Models\EmailMessage;
use App\Models\EmailThread;
use App\Models\User;

it('backfills orphaned threads by matching company mailbox email participants', function () {
    $company = User::factory()->create(['type' => 'company']);

    $account = ChannelAccount::query()->create([
        'user_id' => $company->id,
        'type' => 'smtp_imap',
        'email_address' => 'support@example.test',
        'configuration' => [],
        'sync_status' => 'active',
    ]);

    $thread = EmailThread::query()->create([
        'channel_account_id' => null,
        'channel_type' => null,
        'external_thread_id' => 'legacy-1',
        'subject' => 'Legacy support',
        'participants' => ['support@example.test', 'customer@example.test'],
        'status' => 'Open',
        'created_by' => $company->id,
    ]);

    EmailMessage::query()->create([
        'email_thread_id' => $thread->id,
        'from_email' => 'customer@example.test',
        'to_emails' => ['support@example.test'],
        'subject' => 'Legacy support',
        'body_html' => '<p>Hello</p>',
        'sent_at' => now(),
        'created_by' => $company->id,
    ]);

    $this->artisan('conversations:backfill-thread-mailboxes')
        ->expectsOutput('Backfilled 1 thread mailbox link(s).')
        ->assertExitCode(0);

    expect($thread->fresh()->channel_account_id)->toBe($account->id);
    expect($thread->fresh()->channel_type)->toBe('smtp_imap');
});
