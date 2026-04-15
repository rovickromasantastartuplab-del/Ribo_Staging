<?php

use App\Http\Middleware\CheckInstallation;
use App\Http\Middleware\CheckPlanAccess;
use App\Http\Middleware\EnsureOnboardingCompleted;
use App\Models\Account;
use App\Models\AiMemorySummary;
use App\Models\AiTask;
use App\Models\Contact;
use App\Models\EmailThread;
use App\Models\GmailAccount;
use App\Models\User;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;

function createMemoryFixture(): array
{
    $superadmin = User::factory()->create([
        'type' => 'superadmin',
    ]);
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
        'name' => 'Acme Corp',
        'email' => 'acme@example.test',
        'created_by' => $company->id,
    ]);

    $contact = Contact::query()->create([
        'name' => 'Jane Contact',
        'email' => 'jane@example.test',
        'account_id' => $account->id,
        'created_by' => $company->id,
    ]);

    $gmailAccount = GmailAccount::query()->create([
        'user_id' => $company->id,
        'gmail_address' => 'owner@example.test',
        'google_id' => 'google-account-id',
        'access_token' => 'access-token',
        'refresh_token' => 'refresh-token',
        'token_expires_at' => now()->addHour(),
        'scopes' => 'https://www.googleapis.com/auth/gmail.readonly',
        'sync_status' => 'idle',
    ]);

    $thread = EmailThread::query()->create([
        'gmail_account_id' => $gmailAccount->id,
        'gmail_thread_id' => 'gmail-thread-memory-1',
        'subject' => 'Memory thread',
        'snippet' => 'Need follow-up',
        'participants' => ['owner@example.test', 'client@example.test'],
        'message_count' => 1,
        'last_message_at' => now(),
        'is_read' => true,
        'labels' => ['INBOX'],
        'created_by' => $company->id,
    ]);

    return [$staff, $company, $contact, $thread];
}

function disableNonApiBlockingMiddlewareForMemory(): void
{
    test()->withoutMiddleware([
        CheckInstallation::class,
        CheckPlanAccess::class,
        EnsureOnboardingCompleted::class,
    ]);
}

it('returns memory summary and tasks for contact', function () {
    [$staff, $company, $contact, $thread] = createMemoryFixture();
    actingAs($staff);
    disableNonApiBlockingMiddlewareForMemory();

    AiMemorySummary::query()->create([
        'created_by' => $company->id,
        'contact_id' => $contact->id,
        'relationship_summary' => 'Long-term customer with active renewal discussion.',
        'relationship_strength' => 'strong',
        'memory_points_json' => ['renewal_due_q3'],
        'summarized_at' => now(),
    ]);

    AiTask::query()->create([
        'created_by' => $company->id,
        'contact_id' => $contact->id,
        'email_thread_id' => $thread->id,
        'title' => 'Send renewal follow-up',
        'priority' => 'high',
        'is_completed' => false,
    ]);

    getJson("/ai/memory/{$contact->id}")
        ->assertOk()
        ->assertJsonStructure([
            'data' => ['relationship_summary', 'tasks'],
        ]);
});

it('updates task completion state', function () {
    [$staff, $company, $contact, $thread] = createMemoryFixture();
    actingAs($staff);
    disableNonApiBlockingMiddlewareForMemory();

    $task = AiTask::query()->create([
        'created_by' => $company->id,
        'contact_id' => $contact->id,
        'email_thread_id' => $thread->id,
        'title' => 'Confirm next meeting date',
        'priority' => 'medium',
        'is_completed' => false,
    ]);

    patchJson("/ai/tasks/{$task->id}", ['is_completed' => true])
        ->assertOk()
        ->assertJsonPath('data.id', $task->id)
        ->assertJsonPath('data.is_completed', true);
});

it('forbids memory access across companies', function () {
    [$companyAStaff] = createMemoryFixture();
    [, , $companyBContact] = createMemoryFixture();

    actingAs($companyAStaff);
    disableNonApiBlockingMiddlewareForMemory();

    getJson("/ai/memory/{$companyBContact->id}")
        ->assertForbidden();
});

it('forbids task updates across companies', function () {
    [$companyAStaff] = createMemoryFixture();
    [, $companyB, $companyBContact, $companyBThread] = createMemoryFixture();

    $companyBTask = AiTask::query()->create([
        'created_by' => $companyB->id,
        'contact_id' => $companyBContact->id,
        'email_thread_id' => $companyBThread->id,
        'title' => 'Cross-company task',
        'is_completed' => false,
    ]);

    actingAs($companyAStaff);
    disableNonApiBlockingMiddlewareForMemory();

    patchJson("/ai/tasks/{$companyBTask->id}", ['is_completed' => true])
        ->assertForbidden();
});
