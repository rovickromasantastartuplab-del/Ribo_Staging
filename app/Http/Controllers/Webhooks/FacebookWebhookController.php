<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\FieldMapping;
use App\Models\SocialAccount;
use App\Services\Omnichannel\FacebookLeadAdsService;
use App\Services\Omnichannel\LeadEventTrackerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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

                // Get the Page ID this event belongs to
                $pageId = strval($entry['id'] ?? '');

                // ─── BRANCH 1: Facebook Lead Ads (leadgen events) ───
                if (isset($entry['changes'])) {
                    foreach ($entry['changes'] as $change) {
                        if (($change['field'] ?? '') === 'leadgen') {
                            $this->handleLeadgenEvent($change['value'] ?? [], $pageId);
                        }
                    }
                }

                // ─── BRANCH 2: Messenger inbound messages ───
                if (isset($entry['messaging'])) {
                    foreach ($entry['messaging'] as $messageEvent) {

                        $recipientId = strval($messageEvent['recipient']['id'] ?? $pageId);

                        $socialAccount = SocialAccount::where('provider', 'facebook')
                            ->where('provider_id', $recipientId)
                            ->first();

                        if ($socialAccount) {
                            $companyId = $socialAccount->user_id;

                            $tracker = app(LeadEventTrackerService::class);
                            $tracker->recordInboundEvent([
                                'channel' => 'facebook_messenger',
                                'external_event_id' => strval($messageEvent['message']['mid'] ?? ''),
                                'external_actor_key' => strval($messageEvent['sender']['id'] ?? ''),
                                'facebook_psid' => strval($messageEvent['sender']['id'] ?? ''),
                                'summary_text' => strval($messageEvent['message']['text'] ?? 'New inbound Meta message'),
                                'raw_payload' => $messageEvent
                            ], $companyId);

                        } else {
                            Log::warning("Webhook received for unconnected Facebook Page ID: {$recipientId}");
                        }
                    }
                }
            }
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * Handle a Facebook Lead Ads (leadgen) webhook event.
     *
     * Facebook sends: { "leadgen_id": "...", "page_id": "...", "form_id": "...", ... }
     * We use the page_id to find the owning company, then call the Graph API
     * to fetch the actual form field values (name, email, phone).
     */
    private function handleLeadgenEvent(array $value, string $fallbackPageId): void
    {
        $leadgenId = strval($value['leadgen_id'] ?? '');
        $pageId = strval($value['page_id'] ?? $fallbackPageId);
        $formId = strval($value['form_id'] ?? '');

        if (!$leadgenId || !$pageId) {
            Log::warning('Leadgen webhook missing leadgen_id or page_id', $value);
            return;
        }

        // Find which CRM Company owns this Facebook Page
        $socialAccount = SocialAccount::where('provider', 'facebook')
            ->where('provider_id', $pageId)
            ->first();

        if (!$socialAccount) {
            Log::warning("Leadgen webhook for unconnected Facebook Page ID: {$pageId}");
            return;
        }

        $companyId = $socialAccount->user_id;
        $accessToken = $socialAccount->access_token;

        // Call the Facebook Graph API to fetch the real lead data
        $leadAdsService = app(FacebookLeadAdsService::class);
        $leadData = $leadAdsService->fetchLeadData($leadgenId, $accessToken);

        // Apply company-specific field mappings (if configured)
        $mappedFields = FieldMapping::applyMappings($leadData['raw_fields'], $companyId, 'facebook');

        // Use mapped values if available, otherwise fall back to auto-detected values
        $name = $mappedFields['name'] ?? $leadData['name'];
        $email = $mappedFields['email'] ?? $leadData['email'];
        $phone = $mappedFields['phone'] ?? $leadData['phone'];

        // Feed the fetched data into the existing Lead Event pipeline
        $tracker = app(LeadEventTrackerService::class);
        $tracker->recordInboundEvent([
            'channel' => 'facebook_lead_ads',
            'external_event_id' => $leadgenId,
            'external_actor_key' => 'fb_lead_ad_' . $formId,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'company' => $mappedFields['company'] ?? null,
            'summary_text' => 'New Facebook Lead Ad submission' . ($name ? ' from ' . $name : ''),
            'raw_payload' => $leadData['raw_fields'],
        ], $companyId);

        Log::info("Processed Facebook Lead Ad {$leadgenId} for company {$companyId}");
    }
}
