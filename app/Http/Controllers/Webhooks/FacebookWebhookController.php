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

        // Processing POST request (The Webhook Payload)
        $payload = $request->all();

        // Ensure this is a Page-related event
        if (($payload['object'] ?? '') === 'page' && isset($payload['entry'])) {
            foreach ($payload['entry'] as $entry) {

                // Get the Page ID this message was sent to
                $pageId = strval($entry['id'] ?? '');

                // We must also check the nested messaging array to find the true recipient ID
                if (isset($entry['messaging'])) {
                    foreach ($entry['messaging'] as $messageEvent) {

                        $recipientId = strval($messageEvent['recipient']['id'] ?? $pageId);

                        // Find which CRM Company truly owns this Facebook Page ID
                        $socialAccount = \App\Models\SocialAccount::where('provider', 'facebook')
                            ->where('provider_id', $recipientId)
                            ->first();

                        if ($socialAccount) {
                            $companyId = $socialAccount->user_id;

                            // Instantiate the tracker for this specific company!
                            $tracker = app(\App\Services\Omnichannel\LeadEventTrackerService::class);
                            $tracker->recordInboundEvent([
                                'channel' => 'facebook_messenger',
                                'external_event_id' => strval($messageEvent['message']['mid'] ?? ''),
                                'external_actor_key' => strval($messageEvent['sender']['id'] ?? ''),
                                'summary_text' => strval($messageEvent['message']['text'] ?? 'New inbound Meta message'),
                                'raw_payload' => $messageEvent
                            ], $companyId);

                        } else {
                            // If no company has connected this page yet, we must drop it safely
                            \Illuminate\Support\Facades\Log::warning("Webhook received for unconnected Facebook Page ID: {$recipientId}");
                        }
                    }
                }
            }
        }

        return response()->json(['status' => 'success']);
    }
}
