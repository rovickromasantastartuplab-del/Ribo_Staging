<?php

use App\Http\Middleware\CheckInstallation;
use App\Http\Middleware\CheckPlanAccess;
use App\Http\Middleware\EnsureOnboardingCompleted;
use App\Models\AiReportVersion;
use App\Models\AiReportJob;
use App\Models\Account;
use App\Models\Contact;
use App\Models\EmailThread;
use App\Models\GmailAccount;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

function createDownloadHistoryReportFixture(): array
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

    Contact::query()->create([
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

    return [$staff, $company, $thread];
}

function disableNonApiBlockingMiddlewareForReportHistory(): void
{
    test()->withoutMiddleware([
        CheckInstallation::class,
        CheckPlanAccess::class,
        EnsureOnboardingCompleted::class,
    ]);
}

it('creates one ai report version row on first successful download', function () {
    [$staff, $company, $thread] = createDownloadHistoryReportFixture();
    actingAs($staff);
    disableNonApiBlockingMiddlewareForReportHistory();

    $response = postJson('/ai/reports/generate', [
        'threadId' => $thread->id,
        'scope' => 'overall',
        'contactId' => null,
    ])->assertOk();

    $jobId = (int) $response->json('data.job_id');

    get("/ai/reports/{$jobId}/download")->assertOk();

    $this->assertDatabaseHas('ai_report_versions', [
        'created_by' => $company->id,
        'email_thread_id' => $thread->id,
        'ai_report_job_id' => $jobId,
        'download_count' => 1,
    ]);
});

it('increments download_count when same report job is downloaded again', function () {
    [$staff, , $thread] = createDownloadHistoryReportFixture();
    actingAs($staff);
    disableNonApiBlockingMiddlewareForReportHistory();

    $jobId = (int) postJson('/ai/reports/generate', [
        'threadId' => $thread->id,
        'scope' => 'overall',
        'contactId' => null,
    ])->assertOk()->json('data.job_id');

    get("/ai/reports/{$jobId}/download")->assertOk();
    get("/ai/reports/{$jobId}/download")->assertOk();

    $this->assertDatabaseHas('ai_report_versions', [
        'ai_report_job_id' => $jobId,
        'download_count' => 2,
    ]);

    expect(AiReportVersion::query()->where('ai_report_job_id', $jobId)->count())->toBe(1);
});

it('returns report download history rows for a thread', function () {
    [$staff, , $thread] = createDownloadHistoryReportFixture();
    actingAs($staff);
    disableNonApiBlockingMiddlewareForReportHistory();

    $jobId = (int) postJson('/ai/reports/generate', [
        'threadId' => $thread->id,
        'scope' => 'overall',
        'contactId' => null,
    ])->assertOk()->json('data.job_id');

    get("/ai/reports/{$jobId}/download")->assertOk();

    getJson("/ai/reports/history/{$thread->id}")
        ->assertOk()
        ->assertJsonPath('data.0.ai_report_job_id', $jobId)
        ->assertJsonPath('data.0.download_count', 1);
});

it('re-downloads by version id and increments counter', function () {
    [$staff, , $thread] = createDownloadHistoryReportFixture();
    actingAs($staff);
    disableNonApiBlockingMiddlewareForReportHistory();

    $jobId = (int) postJson('/ai/reports/generate', [
        'threadId' => $thread->id,
        'scope' => 'overall',
        'contactId' => null,
    ])->assertOk()->json('data.job_id');

    get("/ai/reports/{$jobId}/download")->assertOk();

    $versionId = (int) AiReportVersion::query()
        ->where('ai_report_job_id', $jobId)
        ->value('id');

    get("/ai/reports/version/{$versionId}/download")
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    $this->assertDatabaseHas('ai_report_versions', [
        'id' => $versionId,
        'download_count' => 2,
    ]);
});

it('forbids history access across companies', function () {
    [$staffA] = createDownloadHistoryReportFixture();
    [, , $threadB] = createDownloadHistoryReportFixture();

    actingAs($staffA);
    disableNonApiBlockingMiddlewareForReportHistory();

    getJson("/ai/reports/history/{$threadB->id}")->assertForbidden();
});

it('regenerates pdf from snapshot when stored file is missing during version re-download', function () {
    [$staff, , $thread] = createDownloadHistoryReportFixture();
    actingAs($staff);
    disableNonApiBlockingMiddlewareForReportHistory();

    $jobId = (int) postJson('/ai/reports/generate', [
        'threadId' => $thread->id,
        'scope' => 'overall',
        'contactId' => null,
    ])->assertOk()->json('data.job_id');

    get("/ai/reports/{$jobId}/download")->assertOk();

    $version = AiReportVersion::query()
        ->where('ai_report_job_id', $jobId)
        ->firstOrFail();

    Storage::disk('local')->delete((string) $version->pdf_path);

    AiReportJob::query()
        ->whereKey($jobId)
        ->update([
            'result_payload_json' => null,
            'context_payload_json' => null,
        ]);

    get("/ai/reports/version/{$version->id}/download")
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    expect(Storage::disk('local')->exists((string) $version->fresh()->pdf_path))->toBeTrue();
});
