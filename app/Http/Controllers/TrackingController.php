<?php

namespace App\Http\Controllers;

use App\Models\EmailOpenLog;
use Illuminate\Http\Request;

class TrackingController extends Controller
{
    /**
     * Log an email open event and return a 1x1 transparent GIF.
     *
     * Route: GET /t/{messageId}?e={email}
     */
    public function pixel(string $messageId, Request $request)
    {
        $email = $request->query('e');

        if ($messageId && $email) {
            EmailOpenLog::firstOrCreate(
                [
                    'gmail_message_id' => $messageId,
                    'recipient_email' => $email,
                ],
                [
                    'ip_address' => $request->ip(),
                    'opened_at' => now(),
                ]
            );
        }

        // 1x1 transparent GIF (43 bytes)
        $gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

        return response($gif, 200, [
            'Content-Type' => 'image/gif',
            'Content-Length' => strlen($gif),
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => 'Thu, 01 Jan 1970 00:00:00 GMT',
        ]);
    }
}
