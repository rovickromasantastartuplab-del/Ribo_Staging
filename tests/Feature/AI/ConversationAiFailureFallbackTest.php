<?php

use App\Http\Middleware\CheckInstallation;
use App\Http\Middleware\CheckPlanAccess;
use App\Http\Middleware\EnsureOnboardingCompleted;
use App\Models\Account;
use App\Models\Contact;
use App\Models\EmailThread;
use App\Models\GmailAccount;
use App\Models\User;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

function createFallbackFixture(): array
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
        'name' => 'Fallback Corp',
        'email' => 'fallback@example.test',
        'created_by' => $company->id,
    ]);

    Contact::query()->create([
        'name' => 'Fallback Contact',
        'email' => 'fallback-contact@example.test',
        'account_id' => $account->id,
        'created_by' => $company->id,
    ]);

    $gmailAccount = GmailAccount::query()->create([
        'user_id' => $company->id,
        'gmail_address' => 'owner@example.test',
        'google_id' => 'google-fallback-id',
        'access_token' => 'access-token',
        'refresh_token' => 'refresh-token',
        'token_expires_at' => now()->addHour(),
        'scopes' => 'https://www.googleapis.com/auth/gmail.readonly',
        'sync_status' => 'idle',
    ]);

    $thread = EmailThread::query()->create([
        'gmail_account_id' => $gmailAccount->id,
        'gmail_thread_id' => 'gmail-thread-fallback-1',
        'subject' => 'Fallback thread',
        'snippet' => 'Need safe fallback',
        'participants' => ['owner@example.test', 'client@example.test'],
        'message_count' => 2,
        'last_message_at' => now(),
        'is_read' => true,
        'labels' => ['INBOX'],
        'created_by' => $company->id,
    ]);

    return [$staff, $superadmin, $thread];
}

function disableNonApiBlockingMiddlewareForFallback(): void
{
    test()->withoutMiddleware([
        CheckInstallation::class,
        CheckPlanAccess::class,
        EnsureOnboardingCompleted::class,
    ]);
}

it('returns 422 when ai is disabled', function () {
    [$staff, $superadmin, $thread] = createFallbackFixture();
    updateSetting('ai_conversation_enabled', '0', $superadmin->id);

    actingAs($staff);
    disableNonApiBlockingMiddlewareForFallback();

    postJson('/ai/draft', [
        'threadId' => $thread->id,
        'prompt' => 'Write follow-up',
        'tone' => 'professional',
    ])->assertStatus(422)
        ->assertJsonPath('message', 'AI unavailable');
});

it('returns 422 when api key is missing', function () {
    [$staff, $superadmin, $thread] = createFallbackFixture();
    updateSetting('ai_conversation_enabled', '1', $superadmin->id);
    updateSetting('ai_conversation_api_key', '', $superadmin->id);

    actingAs($staff);
    disableNonApiBlockingMiddlewareForFallback();

    postJson('/ai/draft', [
        'threadId' => $thread->id,
        'prompt' => 'Write follow-up',
        'tone' => 'professional',
    ])->assertStatus(422)
        ->assertJsonPath('message', 'AI unavailable');
});

it('returns controlled fallback when provider fails without leaking internals', function () {
    [$staff, $superadmin, $thread] = createFallbackFixture();
    updateSetting('ai_conversation_enabled', '1', $superadmin->id);
    updateSetting('ai_conversation_api_key', 'fail-secret-key', $superadmin->id);

    actingAs($staff);
    disableNonApiBlockingMiddlewareForFallback();

    $response = postJson('/ai/draft', [
        'threadId' => $thread->id,
        'prompt' => 'Write follow-up',
        'tone' => 'professional',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('message', 'AI unavailable');

    $responseBody = json_encode($response->json());
    expect($responseBody)->not->toContain('fail-secret-key');
    expect($responseBody)->not->toContain('RuntimeException');
});
