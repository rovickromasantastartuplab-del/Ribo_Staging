<?php

use App\Http\Middleware\CheckInstallation;
use App\Http\Middleware\CheckPlanAccess;
use App\Http\Middleware\EnsureOnboardingCompleted;
use App\Models\Account;
use App\Models\EmailThread;
use App\Models\GmailAccount;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use App\Models\Contact;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

function createDraftFixture(): array
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
        'name' => 'Acme Draft Corp',
        'email' => 'draft-acme@example.test',
        'created_by' => $company->id,
    ]);

    Contact::query()->create([
        'name' => 'Alex Prospect',
        'email' => 'alex@example.test',
        'account_id' => $account->id,
        'created_by' => $company->id,
    ]);

    $gmailAccount = GmailAccount::query()->create([
        'user_id' => $company->id,
        'gmail_address' => 'owner@example.test',
        'google_id' => 'google-account-draft-id',
        'access_token' => 'access-token',
        'refresh_token' => 'refresh-token',
        'token_expires_at' => now()->addHour(),
        'scopes' => 'https://www.googleapis.com/auth/gmail.readonly',
        'sync_status' => 'idle',
    ]);

    $thread = EmailThread::query()->create([
        'gmail_account_id' => $gmailAccount->id,
        'gmail_thread_id' => 'gmail-thread-draft-1',
        'subject' => 'Follow-up regarding proposal',
        'snippet' => 'Can we schedule a call this week?',
        'participants' => ['owner@example.test', 'client@example.test'],
        'message_count' => 2,
        'last_message_at' => now(),
        'is_read' => true,
        'labels' => ['INBOX'],
        'created_by' => $company->id,
    ]);

    return [$staff, $thread];
}

function disableNonApiBlockingMiddlewareForDraft(): void
{
    test()->withoutMiddleware([
        CheckInstallation::class,
        CheckPlanAccess::class,
        EnsureOnboardingCompleted::class,
    ]);
}

it('generates ai draft from prompt and tone', function () {
    [$staff, $thread] = createDraftFixture();
    actingAs($staff);
    disableNonApiBlockingMiddlewareForDraft();
    Http::fake([
        'https://api.openai.com/v1/responses' => Http::response([
            'output_text' => json_encode([
                'subject' => 'Re: Follow-up regarding proposal',
                'body' => '<p>Thanks for the update. Are you available for a quick call tomorrow?</p>',
            ]),
            'usage' => [
                'input_tokens' => 12,
                'output_tokens' => 18,
                'total_tokens' => 30,
            ],
        ], 200),
    ]);

    postJson('/ai/draft', [
        'threadId' => $thread->id,
        'prompt' => 'Write a professional follow-up',
        'tone' => 'professional',
    ])->assertOk()
        ->assertJsonPath('data.subject', 'Re: Follow-up regarding proposal')
        ->assertJsonPath('data.body', '<p>Thanks for the update. Are you available for a quick call tomorrow?</p>');
});

it('forbids draft generation for cross-company thread', function () {
    [$companyAStaff] = createDraftFixture();
    [, $companyBThread] = createDraftFixture();

    actingAs($companyAStaff);
    disableNonApiBlockingMiddlewareForDraft();

    postJson('/ai/draft', [
        'threadId' => $companyBThread->id,
        'prompt' => 'Write a professional follow-up',
        'tone' => 'professional',
    ])->assertNotFound();
});
