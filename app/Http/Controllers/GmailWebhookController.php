<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\GmailAccount;
use App\Jobs\SyncGmailThreadsJob;

class GmailWebhookController extends Controller
{
    /**
     * Handle incoming push notifications from Google Cloud Pub/Sub
     */
    public function handle(Request $request)
    {
        // Google Pub/Sub sends data in this specific structure
        $message = $request->input('message');

        if (!$message || !isset($message['data'])) {
            Log::warning('Received invalid Gmail webhook payload', ['payload' => $request->all()]);
            return response()->json(['status' => 'invalid_payload'], 400);
        }

        // The 'data' field is base64 encoded JSON
        $jsonData = base64_decode($message['data']);
        $payload = json_decode($jsonData, true);

        if (!$payload || !isset($payload['emailAddress'])) {
            Log::warning('Failed to decode Gmail webhook data', ['decoded' => $jsonData]);
            // Always return 200 to Google so it doesn't retry invalid payloads indefinitely
            return response()->json(['status' => 'ignored']);
        }

        $emailAddress = $payload['emailAddress'];
        $historyId = $payload['historyId'] ?? null;

        Log::info("Gmail webhook received for: {$emailAddress} (HistoryId: {$historyId})");

        // Find the connected Gmail account for this email
        $gmailAccount = GmailAccount::where('gmail_address', $emailAddress)->first();

        if ($gmailAccount) {
            // Immediately dispatch the sync job to the background queue
            SyncGmailThreadsJob::dispatch($gmailAccount->id);
            Log::info("Dispatched SyncGmailThreadsJob for {$emailAddress} via Webhook");
        } else {
            Log::warning("Gmail webhook received for {$emailAddress} but no matching GmailAccount found in DB.");
        }

        // Return a quick 200 OK so Google knows we received it
        return response()->json(['status' => 'success']);
    }
}
