<?php

use Barryvdh\DomPDF\Facade\Pdf;
use App\Http\Middleware\CheckInstallation;
use App\Http\Middleware\CheckPlanAccess;
use App\Http\Middleware\EnsureOnboardingCompleted;
use App\Jobs\AI\GenerateConversationAiReportJob;
use App\Models\Account;
use App\Models\AiReportJob;
use App\Models\Contact;
use App\Models\EmailThread;
use App\Models\GmailAccount;
use App\Models\Opportunity;
use App\Models\OpportunitySource;
use App\Models\OpportunityStage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
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

it('creates report job and returns processing status in sync mode without queue dispatch', function () {
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

    Queue::assertNotPushed(GenerateConversationAiReportJob::class);
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

it('returns report scope options including specific opportunities', function () {
    [$staff, $company, $thread, $contact] = createReportFixture();
    actingAs($staff);
    disableNonApiBlockingMiddlewareForReport();

    $stage = OpportunityStage::query()->create([
        'name' => 'Discovery',
        'created_by' => $company->id,
    ]);
    $source = OpportunitySource::query()->create([
        'name' => 'Inbound',
        'created_by' => $company->id,
    ]);

    $opportunity = Opportunity::query()->create([
        'name' => 'Enterprise Expansion',
        'amount' => 18000,
        'account_id' => $contact->account_id,
        'contact_id' => $contact->id,
        'opportunity_stage_id' => $stage->id,
        'opportunity_source_id' => $source->id,
        'created_by' => $company->id,
    ]);

    DB::table('email_threadables')->insert([
        'email_thread_id' => $thread->id,
        'email_threadable_type' => Contact::class,
        'email_threadable_id' => $contact->id,
        'matched_via' => 'manual',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    getJson("/ai/reports/options/{$thread->id}")
        ->assertOk()
        ->assertJsonPath('data.opportunities.0.id', $opportunity->id)
        ->assertJsonPath('data.opportunities.0.name', 'Enterprise Expansion');
});

it('stores report context payload when generating report', function () {
    [$staff, , $thread] = createReportFixture();
    actingAs($staff);
    disableNonApiBlockingMiddlewareForReport();

    $response = postJson('/ai/reports/generate', [
        'threadId' => $thread->id,
        'scope' => 'overall',
        'contactId' => null,
    ])->assertOk();

    $jobId = (int) $response->json('data.job_id');
    $job = AiReportJob::query()->findOrFail($jobId);

    expect($job->context_payload_json)->toBeArray();
    expect($job->context_payload_json)->toHaveKey('crm');
    expect($job->context_payload_json)->toHaveKey('threads');
});

it('streams generated report as a pdf file', function () {
    [$staff, , $thread] = createReportFixture();
    actingAs($staff);
    disableNonApiBlockingMiddlewareForReport();

    $response = postJson('/ai/reports/generate', [
        'threadId' => $thread->id,
        'scope' => 'overall',
        'contactId' => null,
    ])->assertOk();

    $jobId = (int) $response->json('data.job_id');

    get("/ai/reports/{$jobId}/download")
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('requires opportunity id when scope is specific opportunity', function () {
    [$staff, , $thread] = createReportFixture();
    actingAs($staff);
    disableNonApiBlockingMiddlewareForReport();

    postJson('/ai/reports/generate', [
        'threadId' => $thread->id,
        'scope' => 'specific-opportunity',
        'contactId' => null,
    ])->assertStatus(422)
      ->assertJsonValidationErrors(['opportunityId']);
});

it('rejects specific opportunity selection when not linked to thread context', function () {
    [$staff, $company, $thread] = createReportFixture();
    actingAs($staff);
    disableNonApiBlockingMiddlewareForReport();

    $otherAccount = Account::query()->create([
        'name' => 'Unrelated Account',
        'email' => 'unrelated@example.test',
        'created_by' => $company->id,
    ]);

    $stage = OpportunityStage::query()->create([
        'name' => 'Validation',
        'created_by' => $company->id,
    ]);
    $source = OpportunitySource::query()->create([
        'name' => 'Outbound',
        'created_by' => $company->id,
    ]);

    $unrelatedOpportunity = Opportunity::query()->create([
        'name' => 'Unrelated Deal',
        'amount' => 22000,
        'account_id' => $otherAccount->id,
        'contact_id' => null,
        'opportunity_stage_id' => $stage->id,
        'opportunity_source_id' => $source->id,
        'created_by' => $company->id,
    ]);

    postJson('/ai/reports/generate', [
        'threadId' => $thread->id,
        'scope' => 'specific-opportunity',
        'contactId' => null,
        'opportunityId' => $unrelatedOpportunity->id,
    ])->assertStatus(422)
      ->assertJsonPath('message', 'Selected opportunity is not linked to this report context.');
});

it('rejects contact selection when contact is not linked to thread context', function () {
    [$staff, $company, $thread, $linkedContact] = createReportFixture();
    actingAs($staff);
    disableNonApiBlockingMiddlewareForReport();

    DB::table('email_threadables')->insert([
        'email_thread_id' => $thread->id,
        'email_threadable_type' => Contact::class,
        'email_threadable_id' => $linkedContact->id,
        'matched_via' => 'manual',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $otherAccount = Account::query()->create([
        'name' => 'Unrelated Contact Account',
        'email' => 'other-account@example.test',
        'created_by' => $company->id,
    ]);

    $unrelatedContact = Contact::query()->create([
        'name' => 'Unrelated Contact',
        'email' => 'unrelated-contact@example.test',
        'account_id' => $otherAccount->id,
        'created_by' => $company->id,
    ]);

    postJson('/ai/reports/generate', [
        'threadId' => $thread->id,
        'scope' => 'overall',
        'contactId' => $unrelatedContact->id,
    ])->assertStatus(422)
      ->assertJsonPath('message', 'Selected contact is not linked to this report context.');
});

it('sanitizes download errors without exposing internal exception details', function () {
    [$staff, $company, $thread] = createReportFixture();
    actingAs($staff);
    disableNonApiBlockingMiddlewareForReport();

    $job = AiReportJob::query()->create([
        'created_by' => $company->id,
        'email_thread_id' => $thread->id,
        'scope' => 'overall',
        'status' => 'completed',
        'result_payload_json' => ['summary' => 'ready'],
        'context_payload_json' => ['crm' => []],
        'requested_at' => now(),
        'completed_at' => now(),
    ]);

    Pdf::shouldReceive('loadView')
        ->once()
        ->andThrow(new \RuntimeException('Sensitive internal failure details'));

    getJson("/ai/reports/{$job->id}/download")
        ->assertStatus(500)
        ->assertJsonPath('message', 'Failed to generate summary report. Please try again.')
        ->assertJsonMissingPath('error');
});
