<?php

use App\Models\AiTriageResult;
use App\Models\EmailThread;
use App\Models\GmailAccount;
use App\Models\User;
use App\Http\Middleware\CheckInstallation;
use App\Http\Middleware\CheckPlanAccess;
use App\Http\Middleware\EnsureOnboardingCompleted;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

function createCompanyThreadFixture(): array
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
        'gmail_thread_id' => 'gmail-thread-1',
        'subject' => 'Renewal follow-up',
        'snippet' => 'Can we discuss renewal details?',
        'participants' => ['owner@example.test', 'client@example.test'],
        'message_count' => 1,
        'last_message_at' => now(),
        'is_read' => true,
        'labels' => ['INBOX'],
        'created_by' => $company->id,
    ]);

    return [$staff, $thread, $company];
}

function disableNonApiBlockingMiddleware(): void
{
    test()->withoutMiddleware([
        CheckInstallation::class,
        CheckPlanAccess::class,
        EnsureOnboardingCompleted::class,
    ]);
}

it('returns triage payload for thread', function () {
    [$staff, $thread, $company] = createCompanyThreadFixture();
    actingAs($staff);
    disableNonApiBlockingMiddleware();

    AiTriageResult::query()->create([
        'created_by' => $company->id,
        'email_thread_id' => $thread->id,
        'intent' => 'follow_up',
        'priority' => 'medium',
        'summary' => 'Customer is asking for renewal details.',
        'analyzed_at' => now(),
    ]);

    getJson("/ai/triage/{$thread->id}")
        ->assertOk()
        ->assertJsonStructure([
            'data' => ['intent', 'priority', 'summary'],
        ]);
});

it('refreshes triage for thread', function () {
    [$staff, $thread] = createCompanyThreadFixture();
    actingAs($staff);
    disableNonApiBlockingMiddleware();

    postJson("/ai/triage/{$thread->id}/refresh")
        ->assertOk()
        ->assertJsonStructure([
            'data' => ['email_thread_id', 'analyzed_at'],
        ]);
});

it('forbids triage access across companies', function () {
    [$companyAStaff] = createCompanyThreadFixture();
    [, $companyBThread] = createCompanyThreadFixture();

    actingAs($companyAStaff);
    disableNonApiBlockingMiddleware();

    getJson("/ai/triage/{$companyBThread->id}")
        ->assertForbidden();
});
