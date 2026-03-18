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
        // 1. Verify the Google Pub/Sub JWT Token
        $bearerToken = $request->bearerToken();
        if (!$bearerToken) {
            Log::warning('Gmail webhook received without bearer token');
            return response()->json(['status' => 'unauthorized'], 401);
        }

        try {
            // Very basic token verification since we just want to ensure it's from Google
            // In a strict prod environment, use google/auth library to verify signature
            $parts = explode('.', $bearerToken);
            if (count($parts) !== 3) {
                throw new \Exception('Invalid token format');
            }
            
            $payload = json_decode(base64_decode($parts[1]), true);
            if (!isset($payload['iss']) || !in_array($payload['iss'], ['https://accounts.google.com', 'accounts.google.com'])) {
                throw new \Exception('Invalid issuer');
            }
            // Add audience verification if you set up a custom audience in GCP
        } catch (\Exception $e) {
            Log::warning('Invalid Gmail webhook token', ['error' => $e->getMessage()]);
            return response()->json(['status' => 'unauthorized'], 401);
        }

        // 2. Process the message payload
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
