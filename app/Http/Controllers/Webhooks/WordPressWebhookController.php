<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class WordPressWebhookController extends Controller
{
    /**
     * Handle incoming WordPress form submissions.
     */
    public function handle(Request $request)
    {
        // Very basic structural POST implementation
        if (!$request->isMethod('post')) {
            return response()->json(['message' => 'Method not allowed'], 405);
        }

        // Validate custom API key passed in headers by the WP plugin
        $apiKey = $request->header('X-WP-API-Key') ?? $request->input('api_key');
        $validKey = config('services.wordpress.api_key', 'ribo_wp_secret_key'); // Ideally from DB/Config

        if (!$apiKey || $apiKey !== $validKey) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Processing POST request
        // Todo: Convert forms into LeadEvent payload
        // Todo: Pass payload to LeadEventTrackerService

        return response()->json(['status' => 'success']);
    }
}
