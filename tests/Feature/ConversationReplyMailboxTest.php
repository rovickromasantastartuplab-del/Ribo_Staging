<?php

use App\Contracts\Omnichannel\MailboxProvider;
use App\Http\Controllers\ConversationController;
use App\Models\ChannelAccount;
use App\Models\EmailMessage;
use App\Models\EmailThread;
use App\Models\User;
use App\Services\Omnichannel\Drivers\GmailProvider;
use App\Services\Omnichannel\Drivers\SmtpImapProvider;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;

function makeReplyThread(User $company, ChannelAccount $account): EmailThread
{
    $thread = EmailThread::query()->create([
        'channel_account_id' => $account->id,
        'channel_type' => $account->type,
        'external_thread_id' => 'provider-thread-' . $account->id,
        'subject' => 'Customer question',
        'participants' => [$account->email_address, 'customer@example.test'],
        'status' => 'Open',
        'created_by' => $company->id,
        'last_message_at' => now(),
    ]);

    EmailMessage::query()->create([
        'email_thread_id' => $thread->id,
        'external_message_id' => 'incoming-' . $account->id,
        'message_id_header' => '<incoming-' . $account->id . '@example.test>',
        'from_email' => 'customer@example.test',
        'to_emails' => [$account->email_address],
        'subject' => 'Customer question',
        'body_html' => '<p>Question</p>',
        'sent_at' => now(),
        'created_by' => $company->id,
        'created_at' => now(),
    ]);

    return $thread->fresh('channelAccount');
}

it('replies to gmail threads through the gmail provider', function () {
    Queue::fake();

    $company = User::factory()->create(['type' => 'company', 'email_verified_at' => now()]);
    $gmail = ChannelAccount::query()->create([
        'user_id' => $company->id,
        'type' => 'gmail',
        'email_address' => 'gmail@example.test',
        'configuration' => [],
        'sync_status' => 'active',
    ]);
    $thread = makeReplyThread($company, $gmail);

    $provider = new class extends GmailProvider {
        public ?EmailMessage $captured = null;

        public function sendOutgoing(EmailMessage $message): bool
        {
            $this->captured = $message->fresh('thread.channelAccount');

            return true;
        }
    };

    app()->instance(GmailProvider::class, $provider);

    $this->actingAs($company);

    $request = Request::create('/api/conversations/threads/' . $thread->id . '/reply', 'POST', [
        'body' => '<p>Gmail reply</p>',
    ]);

    $response = app()->call([app(ConversationController::class), 'reply'], [
        'request' => $request,
        'thread' => $thread,
    ]);

    expect($response->getStatusCode())->toBe(200);
    expect($provider->captured->thread->channelAccount->type)->toBe('gmail');
    expect($provider->captured->from_email)->toBe('gmail@example.test');
});

it('replies to private mailbox threads through smtp imap provider', function () {
    Queue::fake();

    $company = User::factory()->create(['type' => 'company', 'email_verified_at' => now()]);
    $private = ChannelAccount::query()->create([
        'user_id' => $company->id,
        'type' => 'smtp_imap',
        'email_address' => 'support@example.test',
        'configuration' => [
            'smtp_host' => 'smtp.example.test',
            'smtp_port' => 465,
            'smtp_encryption' => 'ssl',
            'smtp_username' => 'support@example.test',
            'smtp_password' => 'secret',
        ],
        'sync_status' => 'active',
    ]);
    $thread = makeReplyThread($company, $private);

    $provider = new class implements MailboxProvider {
        public ?EmailMessage $captured = null;

        public function syncInbound(ChannelAccount $account): array
        {
            return ['status' => 'success', 'synced' => 0, 'errors' => 0];
        }

        public function sendOutgoing(EmailMessage $message): bool
        {
            $this->captured = $message->fresh('thread.channelAccount');

            return true;
        }

        public function downloadAttachment(EmailMessage $message, string $attachmentId): string
        {
            return '';
        }

        public function updateThreadStatus(EmailThread $thread, string $status): bool
        {
            return true;
        }

        public function getLiveAttachments(EmailMessage $message): array
        {
            return [];
        }
    };

    app()->instance(SmtpImapProvider::class, $provider);

    $this->actingAs($company);

    $request = Request::create('/api/conversations/threads/' . $thread->id . '/reply', 'POST', [
        'body' => '<p>Private reply</p>',
    ]);

    $response = app()->call([app(ConversationController::class), 'reply'], [
        'request' => $request,
        'thread' => $thread,
    ]);

    expect($response->getStatusCode())->toBe(200);
    expect($provider->captured->thread->channelAccount->type)->toBe('smtp_imap');
    expect($provider->captured->from_email)->toBe('support@example.test');
});

it('rejects replies on orphaned threads with provider neutral message', function () {
    $company = User::factory()->create(['type' => 'company', 'email_verified_at' => now()]);

    $thread = EmailThread::query()->create([
        'channel_account_id' => null,
        'channel_type' => null,
        'external_thread_id' => 'legacy-thread',
        'subject' => 'Legacy',
        'participants' => ['customer@example.test'],
        'status' => 'Open',
        'created_by' => $company->id,
    ]);

    $this->actingAs($company);

    $request = Request::create('/api/conversations/threads/' . $thread->id . '/reply', 'POST', [
        'body' => '<p>Reply</p>',
    ]);

    $response = app()->call([app(ConversationController::class), 'reply'], [
        'request' => $request,
        'thread' => $thread,
    ]);

    expect($response->getStatusCode())->toBe(422);
    expect(json_decode($response->getContent(), true)['error'])->toContain('not linked to a mailbox');
});

it('stores reply target header separately for provider threading', function () {
    Queue::fake();

    $company = User::factory()->create(['type' => 'company', 'email_verified_at' => now()]);
    $private = ChannelAccount::query()->create([
        'user_id' => $company->id,
        'type' => 'smtp_imap',
        'email_address' => 'support@example.test',
        'configuration' => [],
        'sync_status' => 'active',
    ]);
    $thread = makeReplyThread($company, $private);
    $incoming = $thread->messages()->first();

    $provider = new class implements MailboxProvider {
        public ?EmailMessage $captured = null;

        public function syncInbound(ChannelAccount $account): array
        {
            return ['status' => 'success', 'synced' => 0, 'errors' => 0];
        }

        public function sendOutgoing(EmailMessage $message): bool
        {
            $this->captured = $message->fresh();

            return true;
        }

        public function downloadAttachment(EmailMessage $message, string $attachmentId): string
        {
            return '';
        }

        public function updateThreadStatus(EmailThread $thread, string $status): bool
        {
            return true;
        }

        public function getLiveAttachments(EmailMessage $message): array
        {
            return [];
        }
    };

    app()->instance(SmtpImapProvider::class, $provider);

    $this->actingAs($company);

    $request = Request::create('/api/conversations/threads/' . $thread->id . '/reply', 'POST', [
        'body' => '<p>Threaded reply</p>',
        'reply_to_message_id' => $incoming->id,
    ]);

    app()->call([app(ConversationController::class), 'reply'], [
        'request' => $request,
        'thread' => $thread,
    ]);

    expect($provider->captured->metadata['reply_to_message_id_header'])->toBe($incoming->message_id_header);
    expect($provider->captured->message_id_header)->not->toBe($incoming->message_id_header);
});

it('stores uploaded reply attachments for private smtp provider', function () {
    Queue::fake();

    $company = User::factory()->create(['type' => 'company', 'email_verified_at' => now()]);
    $private = ChannelAccount::query()->create([
        'user_id' => $company->id,
        'type' => 'smtp_imap',
        'email_address' => 'support@example.test',
        'configuration' => [],
        'sync_status' => 'active',
    ]);
    $thread = makeReplyThread($company, $private);

    $provider = new class implements MailboxProvider {
        public ?EmailMessage $captured = null;

        public function syncInbound(ChannelAccount $account): array
        {
            return ['status' => 'success', 'synced' => 0, 'errors' => 0];
        }

        public function sendOutgoing(EmailMessage $message): bool
        {
            $this->captured = $message->fresh();

            return true;
        }

        public function downloadAttachment(EmailMessage $message, string $attachmentId): string
        {
            return '';
        }

        public function updateThreadStatus(EmailThread $thread, string $status): bool
        {
            return true;
        }

        public function getLiveAttachments(EmailMessage $message): array
        {
            return [];
        }
    };

    app()->instance(SmtpImapProvider::class, $provider);

    $this->actingAs($company);

    $file = UploadedFile::fake()->create('quote.pdf', 12, 'application/pdf');

    $request = Request::create('/api/conversations/threads/' . $thread->id . '/reply', 'POST', [
        'body' => '<p>See attached</p>',
    ], [], [
        'attachments' => [$file],
    ]);

    app()->call([app(ConversationController::class), 'reply'], [
        'request' => $request,
        'thread' => $thread,
    ]);

    expect($provider->captured->metadata['outgoing_attachments'][0]['name'])->toBe('quote.pdf');
    expect($provider->captured->metadata['outgoing_attachments'][0]['mime'])->toBe('application/pdf');
});

it('returns reply mailbox metadata when showing a thread', function () {
    $company = User::factory()->create(['type' => 'company', 'email_verified_at' => now()]);
    $private = ChannelAccount::query()->create([
        'user_id' => $company->id,
        'type' => 'smtp_imap',
        'email_address' => 'support@example.test',
        'configuration' => [],
        'sync_status' => 'active',
    ]);
    $thread = makeReplyThread($company, $private);

    $this->actingAs($company);

    $request = Request::create('/api/conversations/threads/' . $thread->id, 'GET');

    $response = app()->call([app(ConversationController::class), 'show'], [
        'request' => $request,
        'thread' => $thread,
    ]);

    expect($response->getStatusCode())->toBe(200);

    $payload = json_decode($response->getContent(), true);

    expect($payload['reply_mailbox']['email'])->toBe('support@example.test');
    expect($payload['reply_mailbox']['type'])->toBe('smtp_imap');
    expect($payload['reply_mailbox']['can_reply'])->toBeTrue();
});
