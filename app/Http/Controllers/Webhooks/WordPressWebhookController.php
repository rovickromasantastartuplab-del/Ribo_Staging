<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\FieldMapping;
use App\Models\LeadEvent;
use App\Models\Setting;
use App\Services\Omnichannel\LeadEventTrackerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class WordPressWebhookController extends Controller
{
    protected LeadEventTrackerService $tracker;

    public function __construct(LeadEventTrackerService $tracker)
    {
        $this->tracker = $tracker;
    }

    /**
     * Handle incoming WordPress form submissions.
     *
     * Endpoint: POST /api/inbound/wordpress/leads
     * Auth: X-WP-API-Key header (looked up in settings table per company)
     *
     * Contract:
     *  - 201: Lead event created successfully
     *  - 400: Validation error (do not retry)
     *  - 401: Invalid or missing API key (do not retry)
     *  - 409: Duplicate submission_id (already processed, safe to clear)
     *  - 429: Rate limited (retry after delay)
     *  - 500: Transient error (safe to retry)
     */
    public function handle(Request $request)
    {
        // ─── 1. Authenticate: Multi-tenant API key lookup ───
        $apiKey = $request->header('X-WP-API-Key');

        if (!$apiKey) {
            Log::warning('WordPress webhook: missing API key', [
                'ip' => $request->ip(),
            ]);
            return response()->json([
                'status' => 'error',
                'code' => 'AUTH_MISSING',
                'message' => 'API key is required. Pass it in the X-WP-API-Key header.',
            ], 401);
        }

        $setting = Setting::where('key', 'wordpress_api_key')
            ->where('value', $apiKey)
            ->first();

        if (!$setting) {
            Log::warning('WordPress webhook: invalid API key', [
                'ip' => $request->ip(),
                'key_prefix' => substr($apiKey, 0, 8) . '...',
            ]);
            return response()->json([
                'status' => 'error',
                'code' => 'AUTH_INVALID',
                'message' => 'Invalid API key.',
            ], 401);
        }

        $companyId = $setting->user_id;

        // ─── 2. Validate schema ───
        $validator = Validator::make($request->all(), [
            'submission_id' => 'required|uuid',
            'timestamp' => 'required|date',
            'form_id' => 'required|string|max:255',
            'form_name' => 'nullable|string|max:255',
            'payload' => 'required|array|min:1',
            'payload.name' => 'nullable|string|max:255',
            'payload.email' => 'nullable|email|max:255',
            'payload.phone' => 'nullable|string|max:50',
            'payload.message' => 'nullable|string|max:5000',
        ]);

        if ($validator->fails()) {
            Log::info('WordPress webhook: validation failed', [
                'company_id' => $companyId,
                'submission_id' => $request->input('submission_id'),
                'errors' => $validator->errors()->toArray(),
            ]);
            return response()->json([
                'status' => 'error',
                'code' => 'VALIDATION_FAILED',
                'message' => 'Request validation failed.',
                'errors' => $validator->errors(),
            ], 400);
        }

        $validated = $validator->validated();

        // ─── 3. Idempotency check ───
        $existing = LeadEvent::where('external_event_id', $validated['submission_id'])
            ->where('channel', 'wordpress')
            ->first();

        if ($existing) {
            Log::info('WordPress webhook: duplicate submission_id', [
                'company_id' => $companyId,
                'submission_id' => $validated['submission_id'],
                'existing_event_id' => $existing->id,
            ]);
            return response()->json([
                'status' => 'duplicate',
                'lead_event_id' => $existing->id,
                'submission_id' => $validated['submission_id'],
            ], 409);
        }

        // ─── 4. Sanitize payload ───
        $rawPayload = $validated['payload'];
        $sanitized = array_map(function ($value) {
            return is_string($value) ? strip_tags(trim($value)) : $value;
        }, $rawPayload);

        // ─── 5. Apply field mappings (if configured) ───
        $mappedFields = FieldMapping::applyMappings($sanitized, $companyId, 'wordpress');

        // Resolve final field values: mapped > raw payload > null
        $name = $mappedFields['name'] ?? $sanitized['name'] ?? $sanitized['full_name'] ?? $sanitized['your-name'] ?? null;
        $email = $mappedFields['email'] ?? $sanitized['email'] ?? $sanitized['your-email'] ?? null;
        $phone = $mappedFields['phone'] ?? $sanitized['phone'] ?? $sanitized['your-tel'] ?? null;

        // ─── 6. Process through existing pipeline ───
        try {
            $formName = $validated['form_name'] ?? $validated['form_id'];

            $event = $this->tracker->recordInboundEvent([
                'channel' => 'wordpress',
                'external_event_id' => $validated['submission_id'],
                'external_actor_key' => $email ?? $phone ?? 'wp_form_' . $validated['form_id'],
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'company' => $mappedFields['company'] ?? $sanitized['company'] ?? null,
                'summary_text' => "WordPress form submission: {$formName}" . ($name ? " from {$name}" : ''),
                'snippet_text' => $sanitized['message'] ?? $sanitized['your-message'] ?? '',
                'raw_payload' => $sanitized,
            ], $companyId);

            Log::info('WordPress webhook: lead event created', [
                'company_id' => $companyId,
                'submission_id' => $validated['submission_id'],
                'lead_event_id' => $event->id,
            ]);

            return response()->json([
                'status' => 'created',
                'lead_event_id' => $event->id,
                'submission_id' => $validated['submission_id'],
            ], 201);

        } catch (\Exception $e) {
            Log::error('WordPress webhook: processing failed', [
                'company_id' => $companyId,
                'submission_id' => $validated['submission_id'],
                'exception_class' => get_class($e),
                'error' => $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
                'trace' => substr($e->getTraceAsString(), 0, 1000),
            ]);
            return response()->json([
                'status' => 'error',
                'code' => 'PROCESSING_FAILED',
                'message' => 'An internal error occurred. Safe to retry.',
                'debug' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
