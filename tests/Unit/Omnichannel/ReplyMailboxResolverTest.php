<?php

use App\Models\ChannelAccount;
use App\Models\EmailThread;
use App\Models\User;
use App\Services\Omnichannel\ReplyMailboxResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('resolves the channel account attached to the thread', function () {
    $company = User::factory()->create(['type' => 'company']);

    $account = ChannelAccount::query()->create([
        'user_id' => $company->id,
        'type' => 'smtp_imap',
        'email_address' => 'support@example.test',
        'configuration' => [],
        'sync_status' => 'active',
    ]);

    $thread = EmailThread::query()->create([
        'channel_account_id' => $account->id,
        'channel_type' => 'smtp_imap',
        'external_thread_id' => 'thread-1',
        'subject' => 'Need help',
        'status' => 'Open',
        'created_by' => $company->id,
    ]);

    $resolved = app(ReplyMailboxResolver::class)->resolve($thread);

    expect($resolved->id)->toBe($account->id);
    expect($resolved->email_address)->toBe('support@example.test');
});

it('rejects orphaned threads instead of choosing a random mailbox', function () {
    $company = User::factory()->create(['type' => 'company']);

    ChannelAccount::query()->create([
        'user_id' => $company->id,
        'type' => 'gmail',
        'email_address' => 'owner@example.test',
        'configuration' => [],
        'sync_status' => 'active',
    ]);

    $thread = EmailThread::query()->create([
        'channel_account_id' => null,
        'channel_type' => null,
        'external_thread_id' => 'legacy-thread',
        'subject' => 'Legacy',
        'status' => 'Open',
        'created_by' => $company->id,
    ]);

    app(ReplyMailboxResolver::class)->resolve($thread);
})->throws(ValidationException::class, 'This conversation is not linked to a mailbox.');

it('rejects inactive mailbox accounts for replies', function () {
    $company = User::factory()->create(['type' => 'company']);

    $account = ChannelAccount::query()->create([
        'user_id' => $company->id,
        'type' => 'smtp_imap',
        'email_address' => 'support@example.test',
        'configuration' => [],
        'sync_status' => 'error',
        'sync_error' => 'IMAP login failed',
    ]);

    $thread = EmailThread::query()->create([
        'channel_account_id' => $account->id,
        'channel_type' => 'smtp_imap',
        'external_thread_id' => 'thread-2',
        'subject' => 'Need help',
        'status' => 'Open',
        'created_by' => $company->id,
    ]);

    app(ReplyMailboxResolver::class)->resolve($thread);
})->throws(ValidationException::class, 'The mailbox for this conversation is not active.');
