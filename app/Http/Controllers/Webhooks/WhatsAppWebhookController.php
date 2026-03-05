<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class WhatsAppWebhookController extends Controller
{
    /**
     * Handle incoming WhatsApp Cloud API Webhook logic.
     */
    public function handle(Request $request)
    {
        // Meta Webhook Verification
        if ($request->isMethod('get')) {
            $hubVerifyToken = config('services.whatsapp.verify_token', 'ribo_wa_verify_token');
            if ($request->get('hub_mode') === 'subscribe' && $request->get('hub_verify_token') === $hubVerifyToken) {
                return response($request->get('hub_challenge'));
            }
            return response('Forbidden', 403);
        }

        // Processing POST request
        // Todo: Parse WhatsApp messages payload
        // Todo: Pass payload to LeadEventTrackerService

        return response()->json(['status' => 'success']);
    }
}
