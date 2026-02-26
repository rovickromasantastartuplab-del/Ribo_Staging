<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\PaymentSetting;
use App\Models\User;
use Illuminate\Http\Request;
use App\Models\HitpayWebhookLog;

class InvoiceHitpayPaymentController extends Controller
{
    public function processPayment(Request $request)
    {
        $validated = $this->validateInvoicePaymentRequest($request);

        try {
            $invoice = Invoice::findOrFail($validated['invoice_id']);

            // Auto-correct payment type if partial payment equals remaining balance
            $remainingAmount = $invoice->getRemainingAmount();
            if ($validated['payment_type'] === 'partial' && $validated['amount'] == $remainingAmount) {
                $validated['payment_type'] = 'full';
            }

            // Validate payment amount and type
            $validation = $invoice->validatePaymentAmount($validated['amount'], $validated['payment_type']);
            if (!$validation['valid']) {
                return response()->json(['success' => false, 'error' => $validation['message']]);
            }

            $creatorId = $invoice->created_by;
            $creatorUser = User::findOrFail($creatorId);

            // Resolve to actual company ID
            $companyId = in_array($creatorUser->type, ['company', 'superadmin']) ? $creatorUser->id : $creatorUser->created_by;

            $settings = getPaymentMethodConfig('hitpay', $companyId);
            $generalSettings = \App\Models\Setting::getUserSettings($companyId);
            $currency = $validated['currency'] ?? $generalSettings['defaultCurrency'] ?? 'PHP';

            if (empty($settings['api_key']) || empty($settings['salt'])) {
                return response()->json(['success' => false, 'error' => __('HitPay not configured')]);
            }

            $isLive = ($settings['mode'] ?? 'sandbox') === 'live';
            $baseUrl = $isLive
                ? 'https://api.hit-pay.com'
                : 'https://api.sandbox.hit-pay.com';

            // Reference format: inv_ID_AMOUNT_TYPE_UNIQ
            $referenceNumber = 'inv_' . $invoice->id . '_' . $validated['amount'] . '_' . $validated['payment_type'] . '_' . uniqid();

            $payload = [
                'amount' => number_format($validated['amount'], 2, '.', ''),
                'currency' => strtoupper($currency),
                'reference_number' => $referenceNumber,
                'redirect_url' => route('invoice.hitpay.success') . '?invoice_id=' . $invoice->id . '&ref=' . urlencode($referenceNumber),
                'webhook' => route('invoice.hitpay.callback'),
                'purpose' => 'Invoice Payment - ' . $invoice->invoice_number . ' - ' . ucfirst($validated['payment_type']),
                'email' => auth()->check() ? auth()->user()->email : ($invoice->customer_email ?? 'customer@example.com'),
                'name' => auth()->check() ? auth()->user()->name : $invoice->name,
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
                \Log::error('HitPay invoice checkout creation failed', [
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
     * Success redirect — user lands here after completing payment on HitPay.
     * Verifies payment status via HitPay API and records the payment.
     */
    public function success(Request $request)
    {
        $invoiceId = $request->get('invoice_id');
        $referenceNumber = $request->get('ref');
        $hitpayReference = $request->get('reference'); // HitPay appends this
        $hitpayStatus = $request->get('status');

        if (!$invoiceId) {
            return redirect('/')->with('error', __('Invalid payment reference'));
        }

        $invoice = Invoice::find($invoiceId);
        if (!$invoice) {
            return redirect('/')->with('error', __('Invoice not found'));
        }

        $encryptedId = encrypt($invoice->id);

        // If HitPay sent a cancel/fail status, redirect back
        if ($hitpayStatus) {
            $normalizedStatus = strtolower($hitpayStatus);
            if (in_array($normalizedStatus, ['canceled', 'cancelled', 'failed', 'expired'])) {
                return redirect()->route('invoices.public', $encryptedId)->with('error', __('Payment was canceled. No charges were made.'));
            }
        }

        // Check if payment was already recorded by webhook
        if ($referenceNumber) {
            $existing = InvoicePayment::where('payment_id', $referenceNumber)->first();
            if ($existing) {
                return redirect()->route('invoices.public', $encryptedId)->with('success', __('Payment completed successfully!'));
            }
        }

        // Try to verify via HitPay API (fallback when webhook hasn't fired)
        if ($hitpayReference) {
            try {
                $creatorUser = User::find($invoice->created_by);
                $companyId = in_array($creatorUser->type, ['company', 'superadmin']) ? $creatorUser->id : $creatorUser->created_by;
                $settings = getPaymentMethodConfig('hitpay', $companyId);

                if (!empty($settings['api_key'])) {
                    $isLive = ($settings['mode'] ?? 'sandbox') === 'live';
                    $baseUrl = $isLive ? 'https://api.hit-pay.com' : 'https://api.sandbox.hit-pay.com';

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
                            // Parse reference number to get amount and payment type
                            $refNumber = $data['reference_number'] ?? $referenceNumber;
                            $parts = explode('_', $refNumber);
                            $amount = $parts[2] ?? $invoice->getRemainingAmount();
                            $paymentType = $parts[3] ?? 'full';

                            // Record payment if not already recorded
                            $existing = InvoicePayment::where('payment_id', $refNumber)->first();
                            if (!$existing) {
                                InvoicePayment::storePayment([
                                    'invoice_id' => $invoice->id,
                                    'amount' => $amount,
                                    'payment_type' => $paymentType,
                                    'payment_method' => 'hitpay',
                                    'payment_id' => $refNumber,
                                ]);
                                \Log::info('HitPay invoice payment recorded via redirect', ['payment_id' => $refNumber]);
                            }

                            return redirect()->route('invoices.public', $encryptedId)->with('success', __('Payment completed successfully!'));
                        }

                        if (in_array($apiStatus, ['FAILED', 'CANCELLED', 'EXPIRED'])) {
                            return redirect()->route('invoices.public', $encryptedId)->with('error', __('Payment was canceled or failed.'));
                        }

                        // Still pending
                        return redirect()->route('invoices.public', $encryptedId)->with('info', __('Payment is being processed. Your invoice will be updated once confirmed.'));
                    }
                }
            } catch (\Throwable $e) {
                \Log::error('HitPay invoice status check failed', ['error' => $e->getMessage()]);
            }
        }

        // Fallback: redirect back with pending message
        return redirect()->route('invoices.public', $encryptedId)->with('info', __('Payment is being verified. Your invoice will be updated once confirmed.'));
    }

    public function callback(Request $request)
    {
        $payload = $request->all();
        $rawPayload = $request->getContent();

        if (empty($rawPayload) && !empty($payload)) {
            $rawPayload = http_build_query($payload);
        }

        $webhookLog = HitpayWebhookLog::create([
            'request_payload' => $payload,
            'status' => 'received_invoice'
        ]);

        try {
            $eventData = $payload['data']['payment_request'] ?? $payload['data'] ?? $payload['payment_request'] ?? $payload;
            $paymentId = $eventData['reference_number'] ?? $payload['reference_number'] ?? $eventData['id'] ?? null;
            $status = strtoupper($eventData['status'] ?? $payload['status'] ?? '');

            if (!$paymentId || !str_starts_with($paymentId, 'inv_')) {
                $webhookLog->update(['error_message' => ['error' => 'Invalid reference_number']]);
                return response('Invalid reference', 400);
            }

            $parts = explode('_', $paymentId);
            if (count($parts) < 4) {
                $webhookLog->update(['error_message' => ['error' => 'Malformed reference_number']]);
                return response('Malformed reference', 400);
            }

            $invoiceId = $parts[1];
            $amount = $parts[2];
            $paymentType = $parts[3];

            $invoice = Invoice::find($invoiceId);
            if (!$invoice) {
                $webhookLog->update(['error_message' => ['error' => 'Invoice not found']]);
                return response('Invoice not found', 404);
            }

            $creatorUser = User::find($invoice->created_by);
            $companyId = in_array($creatorUser->type, ['company', 'superadmin']) ? $creatorUser->id : $creatorUser->created_by;

            $settings = getPaymentMethodConfig('hitpay', $companyId);

            if (empty($settings['salt'])) {
                $webhookLog->update(['status' => 'error', 'error_message' => ['error' => 'Salt not configured']]);
                return response('Configuration error', 500);
            }

            $headerSignature = $request->header('x-hitpay-signature')
                ?: $request->header('hitpay-signature')
                ?: $request->header('x-signature');

            $debugData = [];
            if (!$this->verifyHmac($payload, $settings['salt'], $rawPayload, $headerSignature, $debugData)) {
                $webhookLog->update([
                    'status' => 'hmac_failed',
                    'error_message' => ['error' => 'Invalid HMAC signature']
                ]);
                return response('Invalid signature', 401);
            }

            $webhookLog->update([
                'payment_id' => $paymentId,
                'status' => 'parsed_' . strtolower($status)
            ]);

            if ($status === 'COMPLETED' || $status === 'SUCCEEDED') {
                $existing = InvoicePayment::where('payment_id', $paymentId)->first();
                if (!$existing) {
                    InvoicePayment::storePayment([
                        'invoice_id' => $invoice->id,
                        'amount' => $amount,
                        'payment_type' => $paymentType,
                        'payment_method' => 'hitpay',
                        'payment_id' => $paymentId,
                    ]);
                    $webhookLog->update(['status' => 'processed_success']);
                    \Log::info('HitPay invoice payment succeeded', ['payment_id' => $paymentId]);
                }
            }

            return response('OK', 200);

        } catch (\Throwable $e) {
            $webhookLog->update(['error_message' => ['error' => $e->getMessage()]]);
            \Log::error('HitPay invoice webhook error', ['error' => $e->getMessage()]);
            return response('Server error', 500);
        }
    }

    private function verifyHmac($payload, $salt, $rawPayload, $headerSignature, &$debugData)
    {
        if (empty($headerSignature))
            return false;
        $calculatedSignature = hash_hmac('sha256', $rawPayload, $salt);
        return hash_equals($calculatedSignature, $headerSignature);
    }

    private function validateInvoicePaymentRequest($request, $additionalRules = [])
    {
        $baseRules = [
            'invoice_id' => 'required|exists:invoices,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_type' => 'required|in:full,partial',
            'currency' => 'nullable|string|max:10',
        ];

        return $request->validate(array_merge($baseRules, $additionalRules));
    }
}
