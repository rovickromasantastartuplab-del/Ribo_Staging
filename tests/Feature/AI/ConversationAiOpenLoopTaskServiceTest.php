<?php

use App\Models\Account;
use App\Models\AiMemorySummary;
use App\Models\AiTask;
use App\Models\Contact;
use App\Models\EmailMessage;
use App\Models\EmailThread;
use App\Models\GmailAccount;
use App\Models\User;
use App\Services\AI\ConversationAiMemoryService;
use App\Services\AI\OpenLoopTaskService;

function createOpenLoopFixture(): array
{
    $superadmin = User::factory()->create([
        'type' => 'superadmin',
    ]);

    $company = User::factory()->create([
        'type' => 'company',
        'created_by' => $superadmin->id,
        'email_verified_at' => now(),
    ]);

    $account = Account::query()->create([
        'name' => 'Loop Account',
        'email' => 'loop@example.test',
        'created_by' => $company->id,
    ]);

    $contact = Contact::query()->create([
        'name' => 'Loop Contact',
        'email' => 'contact@example.test',
        'account_id' => $account->id,
        'created_by' => $company->id,
    ]);

    $gmailAccount = GmailAccount::query()->create([
        'user_id' => $company->id,
        'gmail_address' => 'owner@example.test',
        'google_id' => 'google-loop-id',
        'access_token' => 'access-token',
        'refresh_token' => 'refresh-token',
        'token_expires_at' => now()->addHour(),
        'sync_status' => 'idle',
    ]);

    $thread = EmailThread::query()->create([
        'gmail_account_id' => $gmailAccount->id,
        'gmail_thread_id' => 'gmail-thread-loop-1',
        'subject' => 'Proposal thread',
        'snippet' => 'Please send proposal',
        'participants' => ['owner@example.test', 'contact@example.test'],
        'message_count' => 2,
        'last_message_at' => now(),
        'is_read' => true,
        'labels' => ['INBOX'],
        'created_by' => $company->id,
    ]);

    $contact->emailThreads()->attach($thread->id, ['matched_via' => 'manual']);

    return [$company, $contact, $thread];
}

it('creates and reuses open loop tasks from thread messages', function () {
    [$company, $contact, $thread] = createOpenLoopFixture();

    EmailMessage::query()->create([
        'email_thread_id' => $thread->id,
        'gmail_message_id' => 'msg-inbound-1',
        'from_email' => 'contact@example.test',
        'from_name' => 'Contact',
        'to_emails' => ['owner@example.test'],
        'subject' => 'Need proposal',
        'body_preview' => 'Can you send the proposal by Friday?',
        'body_html' => 'Can you send the proposal by Friday?',
        'sent_at' => now()->subMinute(),
        'gmail_labels' => ['INBOX'],
        'created_by' => $company->id,
    ]);

    EmailMessage::query()->create([
        'email_thread_id' => $thread->id,
        'gmail_message_id' => 'msg-outbound-1',
        'from_email' => 'owner@example.test',
        'from_name' => 'Owner',
        'to_emails' => ['contact@example.test'],
        'subject' => 'Re: Need proposal',
        'body_preview' => 'I will send the proposal by Friday.',
        'body_html' => 'I will send the proposal by Friday.',
        'sent_at' => now(),
        'gmail_labels' => ['SENT'],
        'created_by' => $company->id,
    ]);

    app(OpenLoopTaskService::class)->upsertFromThread($thread, $company->id);
    app(OpenLoopTaskService::class)->upsertFromThread($thread, $company->id);

    $tasks = AiTask::query()
        ->where('created_by', $company->id)
        ->where('contact_id', $contact->id)
        ->get();

    expect($tasks)->toHaveCount(1);
    expect($tasks->first()->title)->toContain('proposal');
});

it('reopens a completed task only for strong repeated signal', function () {
    [$company, $contact, $thread] = createOpenLoopFixture();

    $task = AiTask::query()->create([
        'created_by' => $company->id,
        'contact_id' => $contact->id,
        'email_thread_id' => $thread->id,
        'title' => 'send proposal by friday',
        'is_completed' => true,
        'completed_at' => now()->subHour(),
        'metadata_json' => ['loop_key' => 'send proposal by friday'],
    ]);

    EmailMessage::query()->create([
        'email_thread_id' => $thread->id,
        'gmail_message_id' => 'msg-repeat-strong',
        'from_email' => 'contact@example.test',
        'from_name' => 'Contact',
        'to_emails' => ['owner@example.test'],
        'subject' => 'Reminder',
        'body_preview' => 'Please send proposal by Friday.',
        'body_html' => 'Please send proposal by Friday.',
        'sent_at' => now(),
        'gmail_labels' => ['INBOX'],
        'created_by' => $company->id,
    ]);

    app(OpenLoopTaskService::class)->upsertFromThread($thread, $company->id);

    $task->refresh();
    expect($task->is_completed)->toBeFalse();
    expect($task->completed_at)->toBeNull();
    expect((int) data_get($task->metadata_json, 'reopened_count'))->toBe(1);
});

it('does not reopen completed task for weak signal', function () {
    [$company, $contact, $thread] = createOpenLoopFixture();

    $task = AiTask::query()->create([
        'created_by' => $company->id,
        'contact_id' => $contact->id,
        'email_thread_id' => $thread->id,
        'title' => 'follow up on timeline',
        'is_completed' => true,
        'completed_at' => now()->subHour(),
        'metadata_json' => ['loop_key' => 'follow up on timeline'],
    ]);

    EmailMessage::query()->create([
        'email_thread_id' => $thread->id,
        'gmail_message_id' => 'msg-repeat-weak',
        'from_email' => 'contact@example.test',
        'from_name' => 'Contact',
        'to_emails' => ['owner@example.test'],
        'subject' => 'Maybe later',
        'body_preview' => 'Maybe we can follow up on timeline next month.',
        'body_html' => 'Maybe we can follow up on timeline next month.',
        'sent_at' => now(),
        'gmail_labels' => ['INBOX'],
        'created_by' => $company->id,
    ]);

    app(OpenLoopTaskService::class)->upsertFromThread($thread, $company->id);

    $task->refresh();
    expect($task->is_completed)->toBeTrue();
});

it('reconciles missing tasks during memory show', function () {
    [$company, $contact, $thread] = createOpenLoopFixture();

    EmailMessage::query()->create([
        'email_thread_id' => $thread->id,
        'gmail_message_id' => 'msg-memory-reconcile',
        'from_email' => 'contact@example.test',
        'from_name' => 'Contact',
        'to_emails' => ['owner@example.test'],
        'subject' => 'Need quote',
        'body_preview' => 'Can you send the quote today?',
        'body_html' => 'Can you send the quote today?',
        'sent_at' => now(),
        'gmail_labels' => ['INBOX'],
        'created_by' => $company->id,
    ]);

    AiMemorySummary::query()->create([
        'created_by' => $company->id,
        'contact_id' => $contact->id,
        'relationship_summary' => 'Existing summary.',
        'relationship_strength' => 'moderate',
        'memory_points_json' => ['existing'],
        'summarized_at' => now()->addMinute(),
    ]);

    $payload = app(ConversationAiMemoryService::class)->show($contact, $company->id);

    expect($payload['tasks'])->not->toBeEmpty();
});
