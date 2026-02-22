<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\PlanOrder;
use App\Models\HitpayWebhookLog;
use App\Models\User;
use App\Models\Setting;
use Illuminate\Http\Request;

class HitPayPaymentController extends Controller
{
    /**
     * Create a HitPay checkout session and return the checkout URL.
     */
    public function processPayment(Request $request)
    {
        $validated = $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'billing_cycle' => 'required|in:monthly,yearly',
            'coupon_code' => 'nullable|string',
        ]);

        try {
            $superAdminId = User::where('type', 'superadmin')->first()?->id;
            $settings = getPaymentMethodConfig('hitpay', $superAdminId);

            if (empty($settings['api_key']) || empty($settings['salt'])) {
                return response()->json(['success' => false, 'error' => __('HitPay not configured')]);
            }

            $plan = Plan::findOrFail($validated['plan_id']);
            $pricing = calculatePlanPricing($plan, $validated['coupon_code'] ?? null, $validated['billing_cycle']);

            if ($pricing['final_price'] <= 0) {
                return response()->json(['success' => false, 'error' => __('Free plans do not require payment')]);
            }

            $paymentId = 'hp_' . $plan->id . '_' . time() . '_' . uniqid();

            // Create a pending plan order
            createPlanOrder([
                'user_id' => auth()->id(),
                'plan_id' => $validated['plan_id'],
                'billing_cycle' => $validated['billing_cycle'],
                'payment_method' => 'hitpay',
                'coupon_code' => $validated['coupon_code'] ?? null,
                'payment_id' => $paymentId,
                'status' => 'pending',
            ]);

            // Determine base URL based on mode
            $isLive = ($settings['mode'] ?? 'sandbox') === 'live';
            $baseUrl = $isLive
                ? 'https://api.hit-pay.com'
                : 'https://api.sandbox.hit-pay.com';

            // Get currency
            $generalSettings = Setting::getUserSettings($superAdminId);
            $currency = $generalSettings['defaultCurrency'] ?? 'PHP';

            // Build HitPay payment request payload
            $payload = [
                'amount' => number_format($pricing['final_price'], 2, '.', ''),
                'currency' => strtoupper($currency),
                'reference_number' => $paymentId,
                'redirect_url' => secure_url('payments/hitpay/success') . '?payment_id=' . $paymentId,
                'webhook' => secure_url('payments/hitpay/webhook'),
                'purpose' => 'Subscription to ' . $plan->name . ' plan - ' . ucfirst($validated['billing_cycle']),
                'email' => auth()->user()->email,
                'name' => auth()->user()->name,
            ];

            // Call HitPay API
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $baseUrl . '/v1/payment-requests',
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($payload),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/x-www-form-urlencoded',
                    'X-BUSINESS-API-KEY: ' . $settings['api_key'],
                ],
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            $data = $response ? json_decode($response, true) : null;

            if ($httpCode !== 200 && $httpCode !== 201) {
                \Log::error('HitPay checkout creation failed', [
                    'httpCode' => $httpCode,
                    'curlError' => $curlError,
                    'response' => $data,
                ]);
                return response()->json([
                    'success' => false,
                    'error' => $data['message'] ?? $data['error'] ?? __('Failed to create HitPay payment request. Endpoint unreachable.'),
                ]);
            }

            $checkoutUrl = $data['url'] ?? $data['payment_url'] ?? $data['checkout_url'] ?? null;

            if (!$checkoutUrl) {
                \Log::error('HitPay checkout URL missing', ['response' => $data]);
                return response()->json(['success' => false, 'error' => __('Checkout URL missing from HitPay response')]);
            }

            return response()->json([
                'success' => true,
                'checkoutUrl' => $checkoutUrl,
            ]);

        } catch (\Throwable $e) {
            \Log::error('HitPay processPayment error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => __('Payment failed with internal error')]);
        }
    }

    /**
     * Webhook callback from HitPay after payment completion.
     * Verifies HMAC signature and activates the plan.
     */
    public function callback(Request $request)
    {
        $payload = $request->all();
        $rawPayload = $request->getContent();

        // HitPay sends form-urlencoded POST data. Laravel may consume php://input 
        // before we read it, leaving getContent() empty. Reconstruct it from $request->all()
        // just like the Node.js project's express.raw() + URLSearchParams approach.
        if (empty($rawPayload) && !empty($payload)) {
            $rawPayload = http_build_query($payload);
        }

        // Always try to log the incoming webhook first
        $webhookLog = HitpayWebhookLog::create([
            'request_payload' => $payload,
            'status' => 'received'
        ]);

        \Log::info('HitPay webhook triggered', [
            'has_header_signature' => !empty($request->header('x-hitpay-signature') ?: $request->header('hitpay-signature')),
            'log_id' => $webhookLog->id,
            'content_type' => $request->header('content-type'),
            'raw_body_length' => strlen($rawPayload),
            'raw_body_source' => $request->getContent() ? 'php_input' : 'reconstructed',
        ]);

        try {
            $superAdminId = User::where('type', 'superadmin')->first()?->id;
            $settings = getPaymentMethodConfig('hitpay', $superAdminId);

            if (empty($settings['salt'])) {
                $webhookLog->update(['status' => 'error', 'error_message' => ['error' => 'Salt not configured']]);
                \Log::error('HitPay webhook: salt not configured');
                return response('Configuration error', 500);
            }

            $headerSignature = $request->header('x-hitpay-signature')
                ?: $request->header('hitpay-signature')
                ?: $request->header('x-signature');

            $debugData = [];
            // Verify HMAC signature
            if (!$this->verifyHmac($payload, $settings['salt'], $rawPayload, $headerSignature, $debugData)) {
                $webhookLog->update([
                    'status' => 'hmac_failed',
                    'error_message' => [
                        'error' => 'Invalid HMAC signature',
                        'debug_trace' => $debugData
                    ]
                ]);
                \Log::error('HitPay webhook: invalid HMAC signature', [
                    'payload' => $payload,
                    'header_signature' => $headerSignature,
                    'debug_trace' => $debugData
                ]);
                return response('Invalid signature', 401);
            }

            // HitPay v2 webhooks nest data inside 'data' or 'data.payment_request'
            $eventData = $payload['data']['payment_request'] ?? $payload['data'] ?? $payload['payment_request'] ?? $payload;

            $paymentId = $eventData['reference_number'] ?? $payload['reference_number'] ?? $eventData['id'] ?? null;
            $status = strtoupper($eventData['status'] ?? $payload['status'] ?? '');

            // Update log with parsed fields
            $webhookLog->update([
                'payment_id' => $paymentId,
                'status' => 'parsed_' . strtolower($status)
            ]);

            if (!$paymentId) {
                $webhookLog->update(['error_message' => ['error' => 'Missing reference_number']]);
                \Log::error('HitPay webhook: Missing reference_number in payload', ['payload' => $payload]);
                return response('Missing reference_number', 400);
            }

            $planOrder = PlanOrder::where('payment_id', $paymentId)->first();

            if (!$planOrder) {
                $webhookLog->update(['error_message' => ['error' => 'Order not found']]);
                \Log::error('HitPay webhook: order not found', ['payment_id' => $paymentId]);
                return response('Order not found', 404);
            }

            if ($status === 'COMPLETED' || $status === 'SUCCEEDED') {
                if ($planOrder->status === 'pending') {
                    // Use the standard processPaymentSuccess helper
                    processPaymentSuccess([
                        'user_id' => $planOrder->user_id,
                        'plan_id' => $planOrder->plan_id,
                        'billing_cycle' => $planOrder->billing_cycle,
                        'payment_method' => 'hitpay',
                        'coupon_code' => $planOrder->coupon_code,
                        'payment_id' => $paymentId,
                    ]);

                    $webhookLog->update(['status' => 'processed_success']);
                    \Log::info('HitPay payment succeeded', ['payment_id' => $paymentId]);
                } else {
                    $webhookLog->update(['status' => 'ignored_already_processed']);
                }
            } elseif (in_array($status, ['FAILED', 'CANCELLED', 'EXPIRED'])) {
                $planOrder->update(['status' => 'rejected']);
                $webhookLog->update(['status' => 'processed_failed']);
                \Log::info('HitPay payment failed/cancelled', ['payment_id' => $paymentId, 'status' => $status]);
            }

            return response('OK', 200);
        } catch (\Exception $e) {
            if (isset($webhookLog)) {
                $webhookLog->update([
                    'status' => 'exception',
                    'error_message' => [
                        'message' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine()
                    ]
                ]);
            }
            \Log::error('HitPay webhook error', ['error' => $e->getMessage()]);
            return response('Error', 500);
        }
    }

    /**
     * Success redirect — user lands here after completing payment on HitPay.
     */
    public function success(Request $request)
    {
        $paymentId = $request->get('payment_id');

        if (!$paymentId && auth()->check()) {
            // Find the most recent pending HitPay order for this user
            $planOrder = PlanOrder::where('user_id', auth()->id())
                ->where('payment_method', 'hitpay')
                ->where('status', 'pending')
                ->orderBy('created_at', 'desc')
                ->first();
        } else {
            $planOrder = PlanOrder::where('payment_id', $paymentId)->first();
        }

        if ($planOrder && $planOrder->status === 'approved') {
            return redirect()->route('plans.index')->with('success', __('Payment completed and plan activated!'));
        }

        // The webhook may not have arrived yet — show a pending message
        if ($planOrder && $planOrder->status === 'pending') {
            return redirect()->route('plans.index')->with('success', __('Payment is being processed. Your plan will be activated shortly.'));
        }

        return redirect()->route('plans.index')->with('error', __('Payment verification failed'));
    }

    /**
     * Verify HMAC-SHA256 signature from HitPay webhook.
     * Verify HMAC-SHA256 signature from HitPay webhook.
     * Ported from the ticketing project's multi-candidate HMAC logic.
     */
    private function verifyHmac(array $payload, string $salt, string $rawPayload = '', ?string $headerSignature = null, array &$debug = []): bool
    {
        // 1. Try V2 Validation (Header Signature against Raw Body)
        if ($headerSignature && !empty($rawPayload)) {
            $computedHmacHex = hash_hmac('sha256', $rawPayload, $salt);
            $computedHmacBase64 = base64_encode(hash_hmac('sha256', $rawPayload, $salt, true));
            $receivedSignature = preg_replace('/^sha256=/i', '', trim($headerSignature));

            $debug['v2'] = [
                'header_signature' => $headerSignature,
                'parsed_received' => $receivedSignature,
                'computed_hex' => $computedHmacHex,
                'computed_base64' => $computedHmacBase64,
            ];

            if (hash_equals($computedHmacHex, $receivedSignature) || hash_equals($computedHmacBase64, $receivedSignature)) {
                return true;
            }
        }

        // 2. Legacy Validation — try MULTIPLE candidate formats (ported from Node.js project)
        $receivedHmac = $payload['hmac'] ?? null;
        if (!$receivedHmac) {
            \Log::error('HitPay HMAC: no hmac in payload', ['debug' => $debug]);
            return false;
        }

        // Build all candidate strings to hash, just like the Node.js buildLegacyHmacCandidates
        $candidates = [];

        // --- Candidates from raw body (form-encoded) ---
        if (!empty($rawPayload)) {
            // Candidate: raw body as-is
            $candidates[] = $rawPayload;

            // Candidate: raw body with hmac= param stripped
            $parts = explode('&', $rawPayload);
            $withoutHmac = implode('&', array_filter($parts, function ($part) {
                return stripos($part, 'hmac=') !== 0;
            }));
            if ($withoutHmac) {
                $candidates[] = $withoutHmac;
                // URL-decoded version
                $decoded = urldecode($withoutHmac);
                if ($decoded !== $withoutHmac) {
                    $candidates[] = $decoded;
                }
            }

            // Candidate: parsed, sorted, re-encoded query string (without hmac)
            parse_str($rawPayload, $parsedParams);
            unset($parsedParams['hmac']);
            ksort($parsedParams);
            $sortedQuery = http_build_query($parsedParams);
            if ($sortedQuery) {
                $candidates[] = $sortedQuery;
            }
        }

        // --- Candidates from parsed payload array ---
        // Filter: exclude 'hmac', keep everything else (including empty strings)
        $entries = [];
        foreach ($payload as $key => $value) {
            if ($key === 'hmac')
                continue;
            $entries[$key] = ($value === null) ? '' : (string) $value;
        }
        ksort($entries);

        // Candidate: key=value&key2=value2
        $kvPairs = [];
        foreach ($entries as $k => $v) {
            $kvPairs[] = $k . '=' . $v;
        }
        $candidates[] = implode('&', $kvPairs);

        // Candidate: URL-encoded key=value pairs
        $kvEncoded = [];
        foreach ($entries as $k => $v) {
            $kvEncoded[] = urlencode($k) . '=' . urlencode($v);
        }
        $encodedStr = implode('&', $kvEncoded);
        if ($encodedStr !== implode('&', $kvPairs)) {
            $candidates[] = $encodedStr;
        }

        // Candidate: pipe-separated key:value
        $pipeParts = [];
        foreach ($entries as $k => $v) {
            $pipeParts[] = $k . ':' . $v;
        }
        $candidates[] = implode('|', $pipeParts);

        // Candidate: simple concatenation key1value1key2value2 (original approach)
        $concatStr = '';
        foreach ($entries as $k => $v) {
            $concatStr .= $k . $v;
        }
        $candidates[] = $concatStr;

        // Also try concat WITHOUT empty values (some HitPay versions skip them)
        $concatNoEmpty = '';
        $entriesNoEmpty = array_filter($entries, function ($v) {
            return $v !== '';
        });
        foreach ($entriesNoEmpty as $k => $v) {
            $concatNoEmpty .= $k . $v;
        }
        if ($concatNoEmpty !== $concatStr) {
            $candidates[] = $concatNoEmpty;
        }

        // Deduplicate
        $candidates = array_unique($candidates);

        // Try each candidate
        $candidateResults = [];
        foreach ($candidates as $idx => $candidate) {
            $hex = hash_hmac('sha256', $candidate, $salt);
            $base64 = base64_encode(hash_hmac('sha256', $candidate, $salt, true));

            $candidateResults[] = [
                'index' => $idx,
                'length' => strlen($candidate),
                'preview' => substr($candidate, 0, 80),
                'hex_prefix' => substr($hex, 0, 8),
            ];

            if (hash_equals($hex, $receivedHmac) || hash_equals($base64, $receivedHmac)) {
                $debug['v1_matched'] = [
                    'candidate_index' => $idx,
                    'candidate' => $candidate,
                ];
                return true;
            }
        }

        $debug['v1'] = [
            'payload_hmac' => $receivedHmac,
            'candidates_tried' => count($candidates),
            'candidate_results' => $candidateResults,
        ];

        \Log::error('HitPay Legacy HMAC Verification Failed', ['debug' => $debug, 'salt_preview' => substr($salt, 0, 4) . '...']);
        return false;
    }
}
