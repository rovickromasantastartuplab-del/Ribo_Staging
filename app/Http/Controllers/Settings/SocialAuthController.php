<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\ChannelAccount;
use App\Models\SocialAccount;
use App\Jobs\SyncChannelAccountJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    /**
     * Redirect the user to the provider's OAuth page.
     */
    public function redirect($provider)
    {
        $user = auth()->user();

        // RESTRICT: Only Company Owners can connect social/gmail accounts
        if (!$user->hasRole('company')) {
            return redirect()->back()->with('error', 'Only Company Owners are authorized to manage integrations.');
        }

        // Dynamically configure the provider
        $this->configureProvider($provider);

        // For Facebook, we specifically ask for Pages access
        if ($provider === 'facebook') {
            return Socialite::driver('facebook')
                ->scopes(['pages_show_list', 'pages_messaging', 'pages_read_engagement', 'pages_manage_metadata', 'leads_retrieve'])
                ->redirect();
        }

        // For Google/Gmail
        if ($provider === 'google') {
            return Socialite::driver('google')
                ->scopes([
                    'https://www.googleapis.com/auth/gmail.readonly',
                    'https://www.googleapis.com/auth/gmail.send',
                    'openid',
                    'email',
                    'profile',
                ])
                ->with([
                    'access_type' => 'offline',
                    'prompt' => 'consent',
                ])
                ->redirect();
        }

        return redirect()->back()->with('error', 'Unsupported provider');
    }

    /**
     * Handle the callback completely from the provider.
     */
    public function callback($provider, Request $request)
    {
        if ($request->has('error')) {
            Log::error("OAuth Error from {$provider}: " . $request->get('error_description'));
            return redirect()->route('settings', ['#integrations-settings'])
                ->with('error', 'Connection request was cancelled or failed.');
        }

        // Reconfigure provider for the callback
        $this->configureProvider($provider);

        try {
            // Get the user from the provider
            $socialUser = Socialite::driver($provider)->user();

            if (!auth()->check()) {
                Log::error("OAuth Callback: No authenticated user session found for {$provider}.");
                return redirect()->route('login')
                    ->with('error', 'Your session expired. Please log in again to connect your account.');
            }

            $companyId = auth()->user()->creatorId();

            if ($provider === 'facebook') {
                // To get all pages this user owns, we have to hit the Graph API
                // Currently, we just store the generic User Token and Page IDs can be refreshed. 
                // But for a true multi-tenant webhook, we need the actual Page ID.
                // Normally you fetch accounts: 
                $response = \Illuminate\Support\Facades\Http::get("https://graph.facebook.com/v19.0/me/accounts?access_token={$socialUser->token}");
                $pages = $response->json()['data'] ?? [];

                if (count($pages) > 0) {
                    // For MVP simplicity, we auto-connect the FIRST page they manage
                    $page = $pages[0];
                    $pageId = $page['id'];
                    $pageToken = $page['access_token'];
                    $pageName = $page['name'];

                    SocialAccount::updateOrCreate(
                        [
                            'user_id' => $companyId,
                            'provider' => 'facebook',
                            'provider_id' => $pageId
                        ],
                        [
                            'provider_name' => $pageName,
                            'access_token' => $pageToken,
                            'refresh_token' => $socialUser->refreshToken,
                            'expires_at' => now()->addDays(60) // Long-lived token roughly
                        ]
                    );

                    return redirect('/settings#integrations-settings')
                        ->with('success', "Successfully connected to Facebook Page: {$pageName}");
                } else {
                    return redirect('/settings#integrations-settings')
                        ->with('error', "No Facebook Pages found for this account. You must create a Page first.");
                }
            }

            // Handle Google/Gmail OAuth callback
            if ($provider === 'google') {
                $user = auth()->user();
                $companyId = $user->creatorId();

                // RESTRICT: Only Company Owners can connect Gmail
                if ($user->type !== 'company') {
                    Log::warning("OAuth Callback: Non-company user (ID: {$user->id}, Type: {$user->type}) attempted to connect Gmail.");
                    return redirect()->route('settings', ['#integrations-settings'])
                        ->with('error', 'Only Company Owners are authorized to connect the company Gmail account.');
                }

                $email = $socialUser->getEmail();

                // RESTRICT: Ensure the Gmail address is unique system-wide (One account per company)
                $existingAccount = ChannelAccount::where('email_address', $email)
                    ->where('type', 'gmail')
                    ->first();

                if ($existingAccount && $existingAccount->user_id !== $companyId) {
                    Log::warning("OAuth Callback: Gmail address {$email} is already linked to another company (Owner ID: {$existingAccount->user_id}).");
                    return redirect()->route('settings', ['#integrations-settings'])
                        ->with('error', "The Gmail account ({$email}) is already connected to another company in the system.");
                }

                $channelAccount = ChannelAccount::updateOrCreate(
                    [
                        'user_id' => $companyId,
                        'email_address' => $email,
                        'type' => 'gmail',
                    ],
                    [
                        'configuration' => [
                            'google_id' => $socialUser->getId(),
                            'access_token' => $socialUser->token,
                            'refresh_token' => $socialUser->refreshToken ?? null,
                            'token_expires_at' => $socialUser->expiresIn
                                ? now()->addSeconds($socialUser->expiresIn)->toIso8601String()
                                : null,
                            'scopes' => 'https://www.googleapis.com/auth/gmail.readonly',
                        ],
                        'sync_status' => 'idle',
                        'sync_error' => null,
                    ]
                );

                // Initiate real-time Pub/Sub Webhooks
                try {
                    $gmailService = new \App\Services\GmailService($channelAccount);
                    $gmailService->watchInbox();
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('Failed to initiate watch inbox on connect', ['error' => $e->getMessage()]);
                }

                // Dispatch initial sync in the background
                SyncChannelAccountJob::dispatchSync($channelAccount->id);

                return redirect()->route('settings', ['#integrations-settings'])
                    ->with('success', "Gmail connected successfully: {$socialUser->getEmail()}");
            }

            return redirect()->route('settings', ['#integrations-settings'])
                ->with('success', ucfirst($provider) . ' connected successfully!');

        } catch (\Exception $e) {
            Log::error("Exception in {$provider} callback: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            return redirect()->route('settings', ['#integrations-settings'])
                ->with('error', "Failed to connect to {$provider}: " . $e->getMessage());
        }
    }
    /**
     * Dynamically configure Socialite drivers to ensure consistency.
     */
    private function configureProvider(string $provider): void
    {
        $config = [];
        
        // Google has special override logic from DB settings
        if ($provider === 'google') {
            $superadmin = \App\Models\User::where('type', 'superadmin')->first();
            $superadminId = $superadmin?->id;

            $clientId = ($superadminId ? getSetting('google_client_id', null, $superadminId) : null)
                ?: config('services.google.client_id');

            $clientSecret = ($superadminId ? getSetting('google_client_secret', null, $superadminId) : null)
                ?: config('services.google.client_secret');

            $config = [
                'services.google.client_id' => $clientId,
                'services.google.client_secret' => $clientSecret,
                'services.google.redirect' => route('social.callback', ['provider' => 'google']),
            ];
        } else {
            // For other providers like Facebook, just ensure the redirect URI matches our route
            $config = [
                "services.{$provider}.redirect" => route('social.callback', ['provider' => $provider]),
            ];
        }

        config($config);
    }
}
