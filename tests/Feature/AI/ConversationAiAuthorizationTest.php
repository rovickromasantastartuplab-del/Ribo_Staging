<?php

use App\Http\Middleware\CheckInstallation;
use App\Http\Middleware\CheckPlanAccess;
use App\Http\Middleware\EnsureOnboardingCompleted;
use App\Models\Account;
use App\Models\AiReportJob;
use App\Models\Contact;
use App\Models\EmailThread;
use App\Models\GmailAccount;
use App\Models\User;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

function createAuthorizationFixture(): array
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
        'name' => 'Auth Corp',
        'email' => 'auth@example.test',
        'created_by' => $company->id,
    ]);

    Contact::query()->create([
        'name' => 'Auth Contact',
        'email' => 'auth-contact@example.test',
        'account_id' => $account->id,
        'created_by' => $company->id,
    ]);

    $gmailAccount = GmailAccount::query()->create([
        'user_id' => $company->id,
        'gmail_address' => 'owner@example.test',
        'google_id' => 'google-auth-id',
        'access_token' => 'access-token',
        'refresh_token' => 'refresh-token',
        'token_expires_at' => now()->addHour(),
        'scopes' => 'https://www.googleapis.com/auth/gmail.readonly',
        'sync_status' => 'idle',
    ]);

    $thread = EmailThread::query()->create([
        'gmail_account_id' => $gmailAccount->id,
        'gmail_thread_id' => 'gmail-thread-auth-1',
        'subject' => 'Auth thread',
        'snippet' => 'Cross tenant check',
        'participants' => ['owner@example.test', 'client@example.test'],
        'message_count' => 1,
        'last_message_at' => now(),
        'is_read' => true,
        'labels' => ['INBOX'],
        'created_by' => $company->id,
    ]);

    return [$staff, $company, $thread];
}

function disableNonApiBlockingMiddlewareForAuthorization(): void
{
    test()->withoutMiddleware([
        CheckInstallation::class,
        CheckPlanAccess::class,
        EnsureOnboardingCompleted::class,
    ]);
}

it('forbids cross-company thread access on triage endpoint', function () {
    [$companyAStaff] = createAuthorizationFixture();
    [, , $companyBThread] = createAuthorizationFixture();

    actingAs($companyAStaff);
    disableNonApiBlockingMiddlewareForAuthorization();

    getJson("/ai/triage/{$companyBThread->id}")
        ->assertForbidden();
});

it('forbids cross-company report retrieval', function () {
    [$companyAStaff] = createAuthorizationFixture();
    [, $companyB, $companyBThread] = createAuthorizationFixture();

    $companyBJob = AiReportJob::query()->create([
        'created_by' => $companyB->id,
        'email_thread_id' => $companyBThread->id,
        'scope' => 'overall',
        'status' => 'queued',
        'requested_at' => now(),
    ]);

    actingAs($companyAStaff);
    disableNonApiBlockingMiddlewareForAuthorization();

    getJson("/ai/reports/{$companyBJob->id}")
        ->assertForbidden();
});

it('returns not found for cross-company draft generation', function () {
    [$companyAStaff] = createAuthorizationFixture();
    [, , $companyBThread] = createAuthorizationFixture();

    actingAs($companyAStaff);
    disableNonApiBlockingMiddlewareForAuthorization();

    postJson('/ai/draft', [
        'threadId' => $companyBThread->id,
        'prompt' => 'Draft a follow-up',
        'tone' => 'professional',
    ])->assertNotFound();
});

it('returns not found for nonexistent draft thread', function () {
    [$staff] = createAuthorizationFixture();

    actingAs($staff);
    disableNonApiBlockingMiddlewareForAuthorization();

    postJson('/ai/draft', [
        'threadId' => 999999,
        'prompt' => 'Draft a follow-up',
        'tone' => 'professional',
    ])->assertNotFound();
});
