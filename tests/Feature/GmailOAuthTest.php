<?php

use App\Models\User;
use App\Models\GmailAccount;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use App\Jobs\SyncGmailThreadsJob;

uses(Tests\TestCase::class);

beforeEach(function () {
    // Basic setup - we need a company user to test settings routes
    $this->user = User::factory()->create([
        'type' => 'company',
        'email_verified_at' => now(),
    ]);
});

it('redirects to google oauth url', function () {
    $response = $this->actingAs($this->user)
        ->get('/settings/auth/google/redirect');

    $response->assertRedirect();
    $this->assertStringContainsString('accounts.google.com/o/oauth2/v2/auth', $response->headers->get('Location'));
    $this->assertStringContainsString('access_type=offline', $response->headers->get('Location'));
    $this->assertStringContainsString('prompt=consent', $response->headers->get('Location'));
});

it('handles google oauth callback and creates gmail account', function () {
    Queue::fake();

    $abstractUser = Mockery::mock(SocialiteUser::class);
    $abstractUser->shouldReceive('getId')->andReturn('1234567890');
    $abstractUser->shouldReceive('getEmail')->andReturn('test@example.com');
    $abstractUser->token = 'fake_access_token';
    $abstractUser->refreshToken = 'fake_refresh_token';
    $abstractUser->expiresIn = 3600;

    Socialite::shouldReceive('driver')->with('google')->andReturnSelf();
    Socialite::shouldReceive('user')->andReturn($abstractUser);

    $response = $this->actingAs($this->user)
        ->get('/settings/auth/google/callback');

    $response->assertRedirect('/settings#integrations-settings');
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('gmail_accounts', [
        'user_id' => $this->user->id,
        'gmail_address' => 'test@example.com',
        'google_id' => '1234567890',
        'scopes' => 'https://www.googleapis.com/auth/gmail.readonly',
        'sync_status' => 'idle',
    ]);

    $account = GmailAccount::where('user_id', $this->user->id)->first();
    expect($account->access_token)->toBe('fake_access_token');
    
    Queue::assertPushed(SyncGmailThreadsJob::class, function ($job) use ($account) {
        return $job->gmailAccountId === $account->id;
    });
});

it('disconnects gmail account', function () {
    $account = GmailAccount::factory()->create([
        'user_id' => $this->user->id,
        'gmail_address' => 'test@example.com',
    ]);

    $this->assertDatabaseHas('gmail_accounts', ['id' => $account->id]);

    $response = $this->actingAs($this->user)
        ->post(route('settings.gmail.disconnect'));

    $response->assertRedirect('/settings#integrations-settings');
    $response->assertSessionHas('success');

    $this->assertDatabaseMissing('gmail_accounts', ['id' => $account->id]);
});
