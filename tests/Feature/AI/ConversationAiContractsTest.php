<?php

use App\Http\Middleware\CheckInstallation;
use App\Http\Middleware\CheckPlanAccess;
use App\Http\Middleware\EnsureOnboardingCompleted;
use App\Models\Account;
use App\Models\AiMemorySummary;
use App\Models\AiTask;
use App\Models\AiTriageResult;
use App\Models\Contact;
use App\Models\EmailThread;
use App\Models\GmailAccount;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;

function createConversationAiContractsFixture(): array
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
        'name' => 'Contract Test Account',
        'email' => 'contracts@example.test',
        'created_by' => $company->id,
    ]);

    $contact = Contact::query()->create([
        'name' => 'Contract Contact',
        'email' => 'contact@example.test',
        'account_id' => $account->id,
        'created_by' => $company->id,
    ]);

    $gmailAccount = GmailAccount::query()->create([
        'user_id' => $company->id,
        'gmail_address' => 'owner@example.test',
        'google_id' => 'google-contract-id',
        'access_token' => 'access-token',
        'refresh_token' => 'refresh-token',
        'token_expires_at' => now()->addHour(),
        'scopes' => 'https://www.googleapis.com/auth/gmail.readonly',
        'sync_status' => 'idle',
    ]);

    $thread = EmailThread::query()->create([
        'gmail_account_id' => $gmailAccount->id,
        'gmail_thread_id' => 'gmail-thread-contract-1',
        'subject' => 'Contract fixture subject',
        'snippet' => 'Contract fixture snippet',
        'participants' => ['owner@example.test', 'client@example.test'],
        'message_count' => 2,
        'last_message_at' => now(),
        'is_read' => true,
        'labels' => ['INBOX'],
        'created_by' => $company->id,
    ]);

    return [$superadmin, $staff, $company, $contact, $thread];
}

function disableConversationAiContractsMiddleware(): void
{
    test()->withoutMiddleware([
        CheckInstallation::class,
        CheckPlanAccess::class,
        EnsureOnboardingCompleted::class,
    ]);
}

it('returns stable triage contract for conversation ui', function () {
    [, $staff, $company, , $thread] = createConversationAiContractsFixture();
    actingAs($staff);
    disableConversationAiContractsMiddleware();

    AiTriageResult::query()->create([
        'created_by' => $company->id,
        'email_thread_id' => $thread->id,
        'intent' => 'follow_up',
        'intent_confidence' => 88,
        'priority' => 'high',
        'thread_state' => 'active',
        'relationship_health' => 'neutral',
        'actionability' => 'act_now',
        'success_probability' => 72,
        'behavioral_pulse' => 'heating_up',
        'summary' => 'Customer is asking for final proposal details.',
        'strategic_action_json' => [
            'goal' => 'close_deal',
            'reason' => 'high_buying_intent',
            'recommendation' => 'offer implementation timeline in next reply',
        ],
        'analyzed_at' => now(),
    ]);

    getJson("/ai/triage/{$thread->id}")
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'summary',
                'intent',
                'intent_confidence',
                'priority',
                'thread_state',
                'relationship_health',
                'actionability',
                'suggested_status',
                'success_probability',
                'behavioral_pulse',
                'strategic_action' => ['goal', 'reason', 'recommendation'],
            ],
        ]);
});

it('returns stable memory contract for conversation ui', function () {
    [, $staff, $company, $contact, $thread] = createConversationAiContractsFixture();
    actingAs($staff);
    disableConversationAiContractsMiddleware();

    AiMemorySummary::query()->create([
        'created_by' => $company->id,
        'contact_id' => $contact->id,
        'relationship_summary' => 'Long-running partnership with weekly check-ins.',
        'relationship_strength' => 'strong',
        'memory_points_json' => ['decision-maker-identified', 'proposal-in-review'],
        'summarized_at' => now(),
    ]);

    AiTask::query()->create([
        'created_by' => $company->id,
        'contact_id' => $contact->id,
        'email_thread_id' => $thread->id,
        'title' => 'Send updated implementation plan',
        'description' => 'Include milestones and SLA details.',
        'priority' => 'high',
        'is_completed' => false,
    ]);

    getJson("/ai/memory/{$contact->id}")
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'contact_id',
                'relationship_summary',
                'relationship_strength',
                'memory_points',
                'tasks' => [
                    '*' => [
                        'id',
                        'title',
                        'description',
                        'priority',
                        'is_completed',
                        'due_at',
                        'completed_at',
                    ],
                ],
            ],
        ]);
});

it('returns stable draft contract for conversation ui', function () {
    [, $staff, , , $thread] = createConversationAiContractsFixture();
    actingAs($staff);
    disableConversationAiContractsMiddleware();

    postJson('/ai/draft', [
        'threadId' => $thread->id,
        'prompt' => 'Write a concise and professional follow-up.',
        'tone' => 'professional',
    ])->assertOk()->assertJsonStructure([
        'data' => ['id', 'subject', 'body', 'generated_at'],
    ]);
});

it('returns stable report generation contract for conversation ui', function () {
    [, $staff, , , $thread] = createConversationAiContractsFixture();
    actingAs($staff);
    disableConversationAiContractsMiddleware();
    Queue::fake();

    postJson('/ai/reports/generate', [
        'threadId' => $thread->id,
        'scope' => 'overall',
        'contactId' => null,
    ])->assertOk()->assertJsonStructure([
        'data' => ['job_id', 'status'],
    ]);
});

it('returns stable task update contract for conversation ui', function () {
    [, $staff, $company, $contact, $thread] = createConversationAiContractsFixture();
    actingAs($staff);
    disableConversationAiContractsMiddleware();

    $task = AiTask::query()->create([
        'created_by' => $company->id,
        'contact_id' => $contact->id,
        'email_thread_id' => $thread->id,
        'title' => 'Confirm budget owner',
        'priority' => 'medium',
        'is_completed' => false,
    ]);

    patchJson("/ai/tasks/{$task->id}", ['is_completed' => true])
        ->assertOk()
        ->assertJsonStructure([
            'data' => ['id', 'is_completed', 'completed_at'],
        ]);
});

it('returns 422 fallback contract when ai is unavailable', function () {
    [$superadmin, $staff, , , $thread] = createConversationAiContractsFixture();
    updateSetting('ai_conversation_enabled', '0', $superadmin->id);

    actingAs($staff);
    disableConversationAiContractsMiddleware();

    getJson("/ai/triage/{$thread->id}")
        ->assertStatus(422)
        ->assertJsonPath('message', 'AI unavailable');
});
