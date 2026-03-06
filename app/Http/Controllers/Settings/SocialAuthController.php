<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\SocialAccount;
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
                ->scopes(['pages_show_list', 'pages_messaging', 'pages_read_engagement', 'pages_manage_metadata'])
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
            return redirect()->route('settings.company.integrations.index')
                ->with('error', 'Connection request was cancelled or failed.');
        }

        try {
            // Get the user from the provider
            $socialUser = Socialite::driver($provider)->user();

            $companyId = auth()->user()->id; // Assuming logged in as company

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

                    return redirect()->route('settings.company.integrations.index')
                        ->with('success', "Successfully connected to Facebook Page: {$pageName}");
                } else {
                    return redirect()->route('settings.company.integrations.index')
                        ->with('error', "No Facebook Pages found for this account. You must create a Page first.");
                }
            }

            return redirect()->route('settings.company.integrations.index')
                ->with('success', ucfirst($provider) . ' connected successfully!');

        } catch (\Exception $e) {
            Log::error("Exception in {$provider} callback: " . $e->getMessage());
            return redirect()->route('settings.company.integrations.index')
                ->with('error', "Failed to connect to {$provider}: " . $e->getMessage());
        }
    }
}
