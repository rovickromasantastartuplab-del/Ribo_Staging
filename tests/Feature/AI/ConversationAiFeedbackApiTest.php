<?php

use App\Http\Middleware\CheckInstallation;
use App\Http\Middleware\CheckPlanAccess;
use App\Http\Middleware\EnsureOnboardingCompleted;
use App\Models\Account;
use App\Models\AiTask;
use App\Models\AiTriageResult;
use App\Models\Contact;
use App\Models\EmailThread;
use App\Models\GmailAccount;
use App\Models\User;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

function createFeedbackFixture(): array
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
        'name' => 'Acme Feedback Corp',
        'email' => 'feedback-acme@example.test',
        'created_by' => $company->id,
    ]);

    $contact = Contact::query()->create([
        'name' => 'Jordan Client',
        'email' => 'jordan@example.test',
        'account_id' => $account->id,
        'created_by' => $company->id,
    ]);

    $gmailAccount = GmailAccount::query()->create([
        'user_id' => $company->id,
        'gmail_address' => 'owner@example.test',
        'google_id' => 'google-account-feedback-id',
        'access_token' => 'access-token',
        'refresh_token' => 'refresh-token',
        'token_expires_at' => now()->addHour(),
        'scopes' => 'https://www.googleapis.com/auth/gmail.readonly',
        'sync_status' => 'idle',
    ]);

    $thread = EmailThread::query()->create([
        'gmail_account_id' => $gmailAccount->id,
        'gmail_thread_id' => 'gmail-thread-feedback-1',
        'subject' => 'Feedback thread',
        'snippet' => 'AI suggestion was off',
        'participants' => ['owner@example.test', 'client@example.test'],
        'message_count' => 3,
        'last_message_at' => now(),
        'is_read' => true,
        'labels' => ['INBOX'],
        'created_by' => $company->id,
    ]);

    $task = AiTask::query()->create([
        'created_by' => $company->id,
        'contact_id' => $contact->id,
        'email_thread_id' => $thread->id,
        'title' => 'Follow up',
        'is_completed' => false,
    ]);

    $triage = AiTriageResult::query()->create([
        'created_by' => $company->id,
        'email_thread_id' => $thread->id,
        'intent' => 'follow_up',
        'priority' => 'high',
        'summary' => 'Urgent renewal concern.',
        'analyzed_at' => now(),
    ]);

    return [$staff, $company, $thread, $contact, $task, $triage];
}

function disableNonApiBlockingMiddlewareForFeedback(): void
{
    test()->withoutMiddleware([
        CheckInstallation::class,
        CheckPlanAccess::class,
        EnsureOnboardingCompleted::class,
    ]);
}

it('stores ai feedback event', function () {
    [$staff, $company, $thread, $contact, $task, $triage] = createFeedbackFixture();
    actingAs($staff);
    disableNonApiBlockingMiddlewareForFeedback();

    postJson('/ai/feedback', [
        'threadId' => $thread->id,
        'triageResultId' => $triage->id,
        'contactId' => $contact->id,
        'taskId' => $task->id,
        'error_type' => 'misclassification',
        'notes' => 'Priority should be medium, not urgent',
    ])->assertOk()->assertJsonStructure([
        'data' => ['id', 'error_type', 'logged_at'],
    ]);

    $this->assertDatabaseHas('ai_feedback_logs', [
        'created_by' => $company->id,
        'email_thread_id' => $thread->id,
        'ai_triage_result_id' => $triage->id,
        'error_type' => 'misclassification',
    ]);
});

it('returns not found when feedback references cross-company thread', function () {
    [$companyAStaff] = createFeedbackFixture();
    [, , $companyBThread] = createFeedbackFixture();

    actingAs($companyAStaff);
    disableNonApiBlockingMiddlewareForFeedback();

    postJson('/ai/feedback', [
        'threadId' => $companyBThread->id,
        'error_type' => 'misclassification',
    ])->assertNotFound();
});

it('returns not found when feedback references cross-company contact or task', function () {
    [$companyAStaff, , $companyAThread] = createFeedbackFixture();
    [, , , $companyBContact, $companyBTask] = createFeedbackFixture();

    actingAs($companyAStaff);
    disableNonApiBlockingMiddlewareForFeedback();

    postJson('/ai/feedback', [
        'threadId' => $companyAThread->id,
        'contactId' => $companyBContact->id,
        'taskId' => $companyBTask->id,
        'error_type' => 'incorrect_action',
    ])->assertNotFound();
});
