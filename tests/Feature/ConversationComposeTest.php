<?php

use App\Http\Controllers\ConversationController;
use App\Models\ChannelAccount;
use App\Models\EmailMessage;
use App\Models\EmailThread;
use App\Models\User;
use App\Services\Omnichannel\Drivers\GmailProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;

it('creates a channel-backed thread before sending a composed gmail message', function () {
    Queue::fake();

    $company = User::factory()->create([
        'type' => 'company',
        'email_verified_at' => now(),
    ]);

    $account = ChannelAccount::query()->create([
        'user_id' => $company->id,
        'type' => 'gmail',
        'email_address' => 'owner@example.test',
        'configuration' => [
            'access_token' => 'token',
            'refresh_token' => 'refresh',
            'token_expires_at' => now()->addHour()->toIso8601String(),
        ],
        'sync_status' => 'idle',
    ]);

    $capturedMessage = null;

    app()->instance(GmailProvider::class, new class($capturedMessage) extends GmailProvider {
        public function __construct(private mixed &$capturedMessage)
        {
        }

        public function sendOutgoing(EmailMessage $message): bool
        {
            $this->capturedMessage = $message->fresh('thread.channelAccount');

            return true;
        }
    });

    $this->actingAs($company);

    $request = Request::create('/api/conversations/compose', 'POST', [
        'to' => 'client@example.test',
        'subject' => 'Fresh proposal',
        'body' => '<p>Hello from CRM</p>',
    ]);

    $response = app(ConversationController::class)->compose($request);

    expect($response->getStatusCode())->toBe(200);
    expect($capturedMessage)->toBeInstanceOf(EmailMessage::class);
    expect($capturedMessage->thread)->toBeInstanceOf(EmailThread::class);
    expect($capturedMessage->thread->channel_account_id)->toBe($account->id);
    expect($capturedMessage->thread->channelAccount->email_address)->toBe('owner@example.test');
});
