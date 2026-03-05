<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class FacebookWebhookController extends Controller
{
    /**
     * Handle incoming Facebook Webhook logic (GET for verification, POST for events).
     */
    public function handle(Request $request)
    {
        // Meta Webhook Verification
        if ($request->isMethod('get')) {
            $hubVerifyToken = config('services.facebook.verify_token', 'ribo_fb_verify_token');
            if ($request->get('hub_mode') === 'subscribe' && $request->get('hub_verify_token') === $hubVerifyToken) {
                return response($request->get('hub_challenge'));
            }
            return response('Forbidden', 403);
        }

        // Processing POST request
        // Todo: Verify X-Hub-Signature using App Secret
        // Todo: Pass payload to LeadEventTrackerService

        return response()->json(['status' => 'success']);
    }
}
