<?php

use App\Http\Middleware\CheckInstallation;
use App\Http\Middleware\CheckPlanAccess;
use App\Http\Middleware\EnsureOnboardingCompleted;
use App\Models\Account;
use App\Models\AiTask;
use App\Models\Contact;
use App\Models\EmailThread;
use App\Models\GmailAccount;
use App\Models\User;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;

function createRolloutFixture(): array
{
    $superadmin = User::factory()->create(['type' => 'superadmin']);
    updateSetting('ai_conversation_enabled', '1', $superadmin->id);
    updateSetting('ai_conversation_api_key', 'test-key', $superadmin->id);
    updateSetting('ai_conversation_model', 'gpt-5.4-mini', $superadmin->id);

    $company = User::factory()->create([
        'type' => 'company',
        'created_by' => $superadmin->id,
        'email_verified_at' => now(),
    ]);

    $staff = User::factory()->create([
        'type' => 'staff',
        'created_by' => $company->id,
        'email_verified_at' => now(),
    ]);

    $account = Account::query()->create([
        'name' => 'Rollout Account',
        'email' => 'rollout@example.test',
        'created_by' => $company->id,
    ]);

    $contact = Contact::query()->create([
        'name' => 'Rollout Contact',
        'email' => 'rollout-contact@example.test',
        'account_id' => $account->id,
        'created_by' => $company->id,
    ]);

    $gmailAccount = GmailAccount::query()->create([
        'user_id' => $company->id,
        'gmail_address' => 'owner@example.test',
        'google_id' => 'google-rollout-id',
        'access_token' => 'access-token',
        'refresh_token' => 'refresh-token',
        'token_expires_at' => now()->addHour(),
        'scopes' => 'https://www.googleapis.com/auth/gmail.readonly',
        'sync_status' => 'idle',
    ]);

    $thread = EmailThread::query()->create([
        'gmail_account_id' => $gmailAccount->id,
        'gmail_thread_id' => 'gmail-thread-rollout-1',
        'subject' => 'Rollout thread',
        'snippet' => 'Need rollout safeguard check',
        'participants' => ['owner@example.test', 'client@example.test'],
        'message_count' => 2,
        'last_message_at' => now(),
        'is_read' => true,
        'labels' => ['INBOX'],
        'created_by' => $company->id,
    ]);

    $task = AiTask::query()->create([
        'created_by' => $company->id,
        'contact_id' => $contact->id,
        'email_thread_id' => $thread->id,
        'title' => 'Rollout AI task',
        'priority' => 'medium',
        'is_completed' => false,
    ]);

    return [$superadmin, $staff, $thread, $task];
}

function disableRolloutBlockingMiddleware(): void
{
    test()->withoutMiddleware([
        CheckInstallation::class,
        CheckPlanAccess::class,
        EnsureOnboardingCompleted::class,
    ]);
}

it('returns 422 when conversation ai is disabled by superadmin', function () {
    [$superadmin, $staff, $thread] = createRolloutFixture();
    updateSetting('ai_conversation_enabled', '0', $superadmin->id);

    actingAs($staff);
    disableRolloutBlockingMiddleware();

    postJson("/ai/triage/{$thread->id}/refresh")
        ->assertStatus(422)
        ->assertJsonPath('message', 'AI unavailable');
});

it('returns 422 for ai task updates when conversation ai is disabled', function () {
    [$superadmin, $staff, , $task] = createRolloutFixture();
    updateSetting('ai_conversation_enabled', '0', $superadmin->id);

    actingAs($staff);
    disableRolloutBlockingMiddleware();

    patchJson("/ai/tasks/{$task->id}", ['is_completed' => true])
        ->assertStatus(422)
        ->assertJsonPath('message', 'AI unavailable');
});

it('returns 422 for ai task updates when api key is missing', function () {
    [$superadmin, $staff, , $task] = createRolloutFixture();
    updateSetting('ai_conversation_enabled', '1', $superadmin->id);
    updateSetting('ai_conversation_api_key', '', $superadmin->id);

    actingAs($staff);
    disableRolloutBlockingMiddleware();

    patchJson("/ai/tasks/{$task->id}", ['is_completed' => true])
        ->assertStatus(422)
        ->assertJsonPath('message', 'AI unavailable');
});
