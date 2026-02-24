<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\PlanOrder;
use App\Models\HitpayWebhookLog;
use App\Models\User;
use App\Models\Setting;
use App\Models\UserPaymentMethod;
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
     * Create a HitPay recurring billing session for trial plans.
     * Uses /v1/recurring-billing with save_payment_method to tokenize the user's card
     * with a minimal authorization hold (1.00) instead of charging the full price.
     */
    public function processTrialPayment(Request $request)
    {
        $validated = $request->validate([
            'plan_id' => 'required|exists:plans,id',
        ]);

        try {
            $plan = Plan::findOrFail($validated['plan_id']);

            // Guard: only trial-enabled plans can use this endpoint
            if ($plan->is_trial !== 'on' || $plan->trial_day <= 0) {
                return response()->json(['success' => false, 'error' => __('This plan does not support free trial.')]);
            }

            $superAdminId = User::where('type', 'superadmin')->first()?->id;
            $settings = getPaymentMethodConfig('hitpay', $superAdminId);

            if (empty($settings['api_key']) || empty($settings['salt'])) {
                return response()->json(['success' => false, 'error' => __('HitPay not configured')]);
            }

            $paymentId = 'hp_trial_' . $plan->id . '_' . time() . '_' . uniqid();

            // Create a pending plan order tagged as hitpay_trial
            createPlanOrder([
                'user_id' => auth()->id(),
                'plan_id' => $plan->id,
                'billing_cycle' => 'monthly', // Trial starts as monthly
                'payment_method' => 'hitpay_trial',
                'coupon_code' => null,
                'payment_id' => $paymentId,
                'status' => 'pending',
            ]);

            // Determine base URL based on mode
            $isLive = ($settings['mode'] ?? 'sandbox') === 'live';
            $baseUrl = $isLive
                ? 'https://api.hit-pay.com'
                : 'https://api.sandbox.hit-pay.com';

            // Get currency
            $generalSettings = \App\Models\Setting::getUserSettings($superAdminId);
            $currency = $generalSettings['defaultCurrency'] ?? 'PHP';

            // Build recurring billing payload
            $payload = [
                'name' => 'Subscription to ' . $plan->name . ' - Free Trial',
                'description' => 'Free Trial Authorization Hold',
                'customer_email' => auth()->user()->email,
                'customer_name' => auth()->user()->name,
                'amount' => number_format(20.00, 2, '.', ''),
                'currency' => strtoupper($currency),
                'save_payment_method' => 'true',
                'reference' => $paymentId,
                'redirect_url' => secure_url('payments/hitpay/success') . '?payment_id=' . $paymentId,
                'webhook' => secure_url('payments/hitpay/webhook'),
            ];

            // Call HitPay Recurring Billing API
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $baseUrl . '/v1/recurring-billing',
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
                \Log::error('HitPay trial recurring billing creation failed', [
                    'httpCode' => $httpCode,
                    'curlError' => $curlError,
                    'response' => $data,
                ]);
                return response()->json([
                    'success' => false,
                    'error' => $data['message'] ?? $data['error'] ?? __('Failed to create HitPay trial session.'),
                ]);
            }

            $checkoutUrl = $data['url'] ?? $data['payment_url'] ?? $data['checkout_url'] ?? null;

            if (!$checkoutUrl) {
                \Log::error('HitPay trial checkout URL missing', ['response' => $data]);
                return response()->json(['success' => false, 'error' => __('Checkout URL missing from HitPay response')]);
            }

            return response()->json([
                'success' => true,
                'checkoutUrl' => $checkoutUrl,
            ]);

        } catch (\Throwable $e) {
            \Log::error('HitPay processTrialPayment error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => __('Trial setup failed with internal error')]);
        }
    }

    /**
     * Cancel the user's active trial.
     * Reverts to the default plan, revokes saved payment methods, and clears trial fields.
     */
    public function cancelTrial(Request $request)
    {
        try {
            $user = auth()->user();

            // Guard: user must be on an active trial
            if ($user->is_trial !== 'on') {
                return response()->json(['success' => false, 'error' => __('You are not on an active trial.')]);
            }

            // Find the default (free) plan to revert to
            $defaultPlan = Plan::where('is_default', true)->first();

            if (!$defaultPlan) {
                \Log::error('Cancel trial: no default plan found');
                return response()->json(['success' => false, 'error' => __('No default plan available.')]);
            }

            // Revoke all active saved payment methods for this user
            UserPaymentMethod::where('user_id', $user->id)
                ->where('status', 'active')
                ->update(['status' => 'revoked']);

            // Cancel any pending/approved trial plan orders
            PlanOrder::where('user_id', $user->id)
                ->where('payment_method', 'hitpay_trial')
                ->whereIn('status', ['pending', 'approved'])
                ->update(['status' => 'cancelled']);

            // Revert user to default plan and clear trial fields
            $user->update([
                'plan_id' => $defaultPlan->id,
                'is_trial' => null,
                'trial_day' => null,
                'trial_expire_date' => null,
                'plan_is_active' => 1,
            ]);

            \Log::info('Trial cancelled', [
                'user_id' => $user->id,
                'reverted_to_plan' => $defaultPlan->id,
            ]);

            return response()->json(['success' => true, 'message' => __('Trial cancelled. You have been reverted to the free plan.')]);

        } catch (\Throwable $e) {
            \Log::error('Cancel trial error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => __('Failed to cancel trial.')]);
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
                    // Check if this is a trial order (recurring billing / save payment method)
                    if ($planOrder->payment_method === 'hitpay_trial') {
                        $this->handleTrialWebhook($planOrder, $eventData, $payload, $webhookLog, $paymentId);
                    } else {
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
                    }
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
     * HitPay redirects here for ALL outcomes (success, cancel, back button).
     * We must verify the actual payment status before showing any message.
     */
    public function success(Request $request)
    {
        $paymentId = $request->get('payment_id');
        $hitpayStatus = $request->get('status'); // HitPay appends this on redirect
        $hitpayReference = $request->get('reference'); // HitPay payment request ID

        // Find the plan order
        if (!$paymentId && auth()->check()) {
            $planOrder = PlanOrder::where('user_id', auth()->id())
                ->where('payment_method', 'hitpay')
                ->where('status', 'pending')
                ->orderBy('created_at', 'desc')
                ->first();
        } else {
            $planOrder = PlanOrder::where('payment_id', $paymentId)->first();
        }

        // If already processed by webhook, show the real result
        if ($planOrder && $planOrder->status === 'approved') {
            return redirect()->route('plans.index')->with('success', __('Payment completed and plan activated!'));
        }
        if ($planOrder && $planOrder->status === 'rejected') {
            return redirect()->route('plans.index')->with('error', __('Payment was canceled or failed. Please try again.'));
        }

        // If HitPay sent a status query param, use it for immediate feedback
        if ($hitpayStatus) {
            $normalizedStatus = strtolower($hitpayStatus);
            if (in_array($normalizedStatus, ['canceled', 'cancelled', 'failed', 'expired'])) {
                // Mark the order as rejected immediately so it doesn't stay as "pending"
                if ($planOrder && $planOrder->status === 'pending') {
                    $planOrder->update(['status' => 'rejected']);
                }
                return redirect()->route('plans.index')->with('error', __('Payment was canceled. No charges were made.'));
            }
        }

        // For pending orders, try to verify the actual status via HitPay API
        if ($planOrder && $planOrder->status === 'pending' && $hitpayReference) {
            try {
                $superAdminId = User::where('type', 'superadmin')->first()?->id;
                $settings = getPaymentMethodConfig('hitpay', $superAdminId);

                if (!empty($settings['api_key'])) {
                    $isLive = ($settings['mode'] ?? 'sandbox') === 'live';
                    $baseUrl = $isLive
                        ? 'https://api.hit-pay.com'
                        : 'https://api.sandbox.hit-pay.com';

                    $ch = curl_init();
                    curl_setopt_array($ch, [
                        CURLOPT_URL => $baseUrl . '/v1/payment-requests/' . $hitpayReference,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_HTTPHEADER => [
                            'X-BUSINESS-API-KEY: ' . $settings['api_key'],
                        ],
                    ]);

                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    if ($httpCode === 200 && $response) {
                        $data = json_decode($response, true);
                        $apiStatus = strtoupper($data['status'] ?? '');

                        if (in_array($apiStatus, ['COMPLETED', 'SUCCEEDED'])) {
                            // Webhook may be delayed — process it now
                            if ($planOrder->status === 'pending') {
                                processPaymentSuccess([
                                    'user_id' => $planOrder->user_id,
                                    'plan_id' => $planOrder->plan_id,
                                    'billing_cycle' => $planOrder->billing_cycle,
                                    'payment_method' => 'hitpay',
                                    'coupon_code' => $planOrder->coupon_code,
                                    'payment_id' => $planOrder->payment_id,
                                ]);
                            }
                            return redirect()->route('plans.index')->with('success', __('Payment completed and plan activated!'));
                        }

                        if (in_array($apiStatus, ['FAILED', 'CANCELLED', 'EXPIRED'])) {
                            $planOrder->update(['status' => 'rejected']);
                            return redirect()->route('plans.index')->with('error', __('Payment was canceled or failed. Please try again.'));
                        }

                        // Still pending on HitPay's side
                        return redirect()->route('plans.index')->with('info', __('Payment is still being processed. Your plan will be activated once payment is confirmed.'));
                    }
                }
            } catch (\Throwable $e) {
                \Log::error('HitPay status check failed', ['error' => $e->getMessage()]);
            }
        }

        // Fallback: order is still pending and we couldn't verify
        if ($planOrder && $planOrder->status === 'pending') {
            return redirect()->route('plans.index')->with('info', __('Payment is being verified. Your plan will be activated once confirmed.'));
        }

        return redirect()->route('plans.index')->with('error', __('Payment verification failed. Please contact support.'));
    }

    /**
     * Handle webhook specifically for trial orders (recurring billing / save payment method).
     * Saves the tokenized payment method and activates the user's free trial.
     */
    private function handleTrialWebhook(PlanOrder $planOrder, array $eventData, array $payload, HitpayWebhookLog $webhookLog, string $paymentId): void
    {
        $user = User::findOrFail($planOrder->user_id);
        $plan = Plan::findOrFail($planOrder->plan_id);

        // Extract payment method token from HitPay response
        // HitPay may nest this in different locations depending on API version
        $recurringBillingId = $eventData['id'] ?? $payload['id'] ?? null;
        $chargeDetails = $eventData['payment_provider']['charge']['details'] ?? $payload['payment_provider']['charge']['details'] ?? null;

        $cardBrand = $chargeDetails['brand'] ?? null;
        $last4 = $chargeDetails['last4'] ?? null;

        // Save the payment method token (idempotent — skip if already saved)
        if ($recurringBillingId) {
            $existing = UserPaymentMethod::where('user_id', $user->id)
                ->where('payment_method_id', $recurringBillingId)
                ->first();

            if (!$existing) {
                UserPaymentMethod::create([
                    'user_id' => $user->id,
                    'payment_method_id' => $recurringBillingId,
                    'card_brand' => $cardBrand,
                    'last_4' => $last4,
                    'status' => 'active',
                ]);
            }
        }

        // Activate the trial subscription on the user
        $user->update([
            'plan_id' => $plan->id,
            'is_trial' => 'on',
            'trial_day' => $plan->trial_day,
            'trial_expire_date' => now()->addDays($plan->trial_day),
            'plan_is_active' => 1,
        ]);

        // Mark the plan order as approved
        $planOrder->update(['status' => 'approved']);

        $webhookLog->update(['status' => 'processed_trial_success']);
        \Log::info('HitPay trial activated', [
            'payment_id' => $paymentId,
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'trial_days' => $plan->trial_day,
            'recurring_billing_id' => $recurringBillingId,
        ]);
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
