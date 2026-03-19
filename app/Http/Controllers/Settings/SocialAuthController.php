<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\SocialAccount;
use App\Models\GmailAccount;
use App\Jobs\SyncGmailThreadsJob;
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
        // For Facebook, we specifically ask for Pages access
        if ($provider === 'facebook') {
            return Socialite::driver('facebook')
                ->scopes(['pages_show_list', 'pages_messaging', 'pages_read_engagement', 'pages_manage_metadata', 'leads_retrieve'])
                ->redirect();
        }

        // For Google/Gmail, build config dynamically from settings table
        if ($provider === 'google') {
            $this->configureGoogleSocialite();

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

        // Reconfigure Socialite for Google before processing the callback
        if ($provider === 'google') {
            $this->configureGoogleSocialite();
        }

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
                $companyId = auth()->user()->creatorId();
                $gmailAccount = GmailAccount::updateOrCreate(
                    [
                        'user_id' => $companyId,
                        'gmail_address' => $socialUser->getEmail(),
                    ],
                    [
                        'google_id' => $socialUser->getId(),
                        'access_token' => $socialUser->token,
                        'refresh_token' => $socialUser->refreshToken ?? null,
                        'token_expires_at' => $socialUser->expiresIn
                            ? now()->addSeconds($socialUser->expiresIn)
                            : null,
                        'scopes' => 'https://www.googleapis.com/auth/gmail.readonly',
                        'sync_status' => 'idle',
                        'sync_error' => null,
                    ]
                );

                // Initiate real-time Pub/Sub Webhooks
                try {
                    $gmailService = new \App\Services\GmailService($gmailAccount);
                    $gmailService->watchInbox();
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('Failed to initiate watch inbox on connect', ['error' => $e->getMessage()]);
                }

                // Dispatch initial sync in the background
                SyncGmailThreadsJob::dispatch($gmailAccount->id);

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
     * Dynamically configure the Socialite Google driver using credentials
     * stored in the settings table by the superadmin.
     * Falls back to config/services.php if DB settings are empty.
     */
    private function configureGoogleSocialite(): void
    {
        // The superadmin is always user_id = 1 or first superadmin
        $superadmin = \App\Models\User::where('type', 'superadmin')->first();
        $superadminId = $superadmin?->id;

        $clientId = ($superadminId ? getSetting('google_client_id', null, $superadminId) : null)
            ?: config('services.google.client_id');

        $clientSecret = ($superadminId ? getSetting('google_client_secret', null, $superadminId) : null)
            ?: config('services.google.client_secret');

        $redirectUri = ($superadminId ? getSetting('google_redirect_uri', null, $superadminId) : null)
            ?: config('services.google.redirect');

        // Dynamically update the Socialite Google config at runtime
        config([
            'services.google.client_id' => $clientId,
            'services.google.client_secret' => $clientSecret,
            'services.google.redirect' => $redirectUri,
        ]);
    }
}
