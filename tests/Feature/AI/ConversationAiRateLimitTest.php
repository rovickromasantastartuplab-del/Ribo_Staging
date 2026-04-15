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

function createRateLimitFixture(): array
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
        'name' => 'RateLimit Corp',
        'email' => 'ratelimit@example.test',
        'created_by' => $company->id,
    ]);

    Contact::query()->create([
        'name' => 'Rate Contact',
        'email' => 'rate-contact@example.test',
        'account_id' => $account->id,
        'created_by' => $company->id,
    ]);

    $gmailAccount = GmailAccount::query()->create([
        'user_id' => $company->id,
        'gmail_address' => 'owner@example.test',
        'google_id' => 'google-rate-id',
        'access_token' => 'access-token',
        'refresh_token' => 'refresh-token',
        'token_expires_at' => now()->addHour(),
        'scopes' => 'https://www.googleapis.com/auth/gmail.readonly',
        'sync_status' => 'idle',
    ]);

    $thread = EmailThread::query()->create([
        'gmail_account_id' => $gmailAccount->id,
        'gmail_thread_id' => 'gmail-thread-rate-1',
        'subject' => 'Rate limit thread',
        'snippet' => 'Check throttle',
        'participants' => ['owner@example.test', 'client@example.test'],
        'message_count' => 2,
        'last_message_at' => now(),
        'is_read' => true,
        'labels' => ['INBOX'],
        'created_by' => $company->id,
    ]);

    return [$staff, $thread];
}

function disableNonApiBlockingMiddlewareForRateLimit(): void
{
    test()->withoutMiddleware([
        CheckInstallation::class,
        CheckPlanAccess::class,
        EnsureOnboardingCompleted::class,
    ]);
}

it('applies rate limiting on ai draft endpoint', function () {
    [$staff, $thread] = createRateLimitFixture();
    actingAs($staff);
    disableNonApiBlockingMiddlewareForRateLimit();

    $lastResponse = null;
    for ($i = 0; $i < 35; $i++) {
        $lastResponse = postJson('/ai/draft', [
            'threadId' => $thread->id,
            'prompt' => 'Generate quick follow-up',
            'tone' => 'professional',
        ]);
    }

    $lastResponse?->assertStatus(429);
});
