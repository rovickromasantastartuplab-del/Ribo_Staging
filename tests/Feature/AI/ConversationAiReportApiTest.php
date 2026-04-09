<?php

use App\Http\Middleware\CheckInstallation;
use App\Http\Middleware\CheckPlanAccess;
use App\Http\Middleware\EnsureOnboardingCompleted;
use App\Jobs\AI\GenerateConversationAiReportJob;
use App\Models\Account;
use App\Models\AiReportJob;
use App\Models\Contact;
use App\Models\EmailThread;
use App\Models\GmailAccount;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

function createReportFixture(): array
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
        'name' => 'Acme Report Corp',
        'email' => 'report-acme@example.test',
        'created_by' => $company->id,
    ]);

    $contact = Contact::query()->create([
        'name' => 'Robin Client',
        'email' => 'robin@example.test',
        'account_id' => $account->id,
        'created_by' => $company->id,
    ]);

    $gmailAccount = GmailAccount::query()->create([
        'user_id' => $company->id,
        'gmail_address' => 'owner@example.test',
        'google_id' => 'google-account-report-id',
        'access_token' => 'access-token',
        'refresh_token' => 'refresh-token',
        'token_expires_at' => now()->addHour(),
        'scopes' => 'https://www.googleapis.com/auth/gmail.readonly',
        'sync_status' => 'idle',
    ]);

    $thread = EmailThread::query()->create([
        'gmail_account_id' => $gmailAccount->id,
        'gmail_thread_id' => 'gmail-thread-report-1',
        'subject' => 'Conversation summary request',
        'snippet' => 'Please provide a full status update',
        'participants' => ['owner@example.test', 'client@example.test'],
        'message_count' => 3,
        'last_message_at' => now(),
        'is_read' => true,
        'labels' => ['INBOX'],
        'created_by' => $company->id,
    ]);

    return [$staff, $company, $thread, $contact];
}

function disableNonApiBlockingMiddlewareForReport(): void
{
    test()->withoutMiddleware([
        CheckInstallation::class,
        CheckPlanAccess::class,
        EnsureOnboardingCompleted::class,
    ]);
}

it('creates report job and returns processing status', function () {
    [$staff, , $thread] = createReportFixture();
    actingAs($staff);
    disableNonApiBlockingMiddlewareForReport();
    Queue::fake();

    postJson('/ai/reports/generate', [
        'threadId' => $thread->id,
        'scope' => 'overall',
        'contactId' => null,
    ])->assertOk()->assertJsonStructure([
        'data' => ['job_id', 'status'],
    ]);

    Queue::assertPushed(GenerateConversationAiReportJob::class);
});

it('returns report job details', function () {
    [$staff, $company, $thread] = createReportFixture();
    actingAs($staff);
    disableNonApiBlockingMiddlewareForReport();

    $job = AiReportJob::query()->create([
        'created_by' => $company->id,
        'email_thread_id' => $thread->id,
        'scope' => 'overall',
        'status' => 'queued',
        'requested_at' => now(),
    ]);

    getJson("/ai/reports/{$job->id}")
        ->assertOk()
        ->assertJsonStructure([
            'data' => ['id', 'status'],
        ]);
});

it('returns not found for cross-company thread during report generation', function () {
    [$companyAStaff] = createReportFixture();
    [, , $companyBThread] = createReportFixture();

    actingAs($companyAStaff);
    disableNonApiBlockingMiddlewareForReport();
    Queue::fake();

    postJson('/ai/reports/generate', [
        'threadId' => $companyBThread->id,
        'scope' => 'overall',
        'contactId' => null,
    ])->assertNotFound();
});

it('returns not found for nonexistent thread during report generation', function () {
    [$staff] = createReportFixture();
    actingAs($staff);
    disableNonApiBlockingMiddlewareForReport();
    Queue::fake();

    postJson('/ai/reports/generate', [
        'threadId' => 999999,
        'scope' => 'overall',
        'contactId' => null,
    ])->assertNotFound();
});

it('returns not found for cross-company contact during report generation', function () {
    [$companyAStaff, , $companyAThread] = createReportFixture();
    [, , , $companyBContact] = createReportFixture();

    actingAs($companyAStaff);
    disableNonApiBlockingMiddlewareForReport();
    Queue::fake();

    postJson('/ai/reports/generate', [
        'threadId' => $companyAThread->id,
        'scope' => 'overall',
        'contactId' => $companyBContact->id,
    ])->assertNotFound();
});

it('returns not found for nonexistent contact during report generation', function () {
    [$staff, , $thread] = createReportFixture();
    actingAs($staff);
    disableNonApiBlockingMiddlewareForReport();
    Queue::fake();

    postJson('/ai/reports/generate', [
        'threadId' => $thread->id,
        'scope' => 'overall',
        'contactId' => 999999,
    ])->assertNotFound();
});

it('forbids report retrieval across companies', function () {
    [$companyAStaff] = createReportFixture();
    [, $companyB, $companyBThread] = createReportFixture();

    $companyBJob = AiReportJob::query()->create([
        'created_by' => $companyB->id,
        'email_thread_id' => $companyBThread->id,
        'scope' => 'overall',
        'status' => 'queued',
        'requested_at' => now(),
    ]);

    actingAs($companyAStaff);
    disableNonApiBlockingMiddlewareForReport();

    getJson("/ai/reports/{$companyBJob->id}")
        ->assertForbidden();
});
