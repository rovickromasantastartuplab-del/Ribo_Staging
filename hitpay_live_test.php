<?php
// hitpay_live_test.php
$baseUrl = 'https://api.hit-pay.com';

$payload = [
    'amount' => '25.00',
    'currency' => 'PHP',
    'reference_number' => 'TEST-1234',
    'redirect_url' => 'http://localhost',
    'webhook' => 'http://localhost',
    'purpose' => 'Test',
    'email' => 'test@test.com',
    'name' => 'Tester',
];

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $baseUrl . '/v1/payment-requests',
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query($payload),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/x-www-form-urlencoded',
        'X-BUSINESS-API-KEY: invalid-live-key-123',
    ],
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "HTTP Code: " . $httpCode . "\n";
echo "cURL Error: " . $curlError . "\n";
echo "Raw Response String: " . var_export($response, true) . "\n";
echo "JSON Decoded: \n";
var_dump(json_decode($response, true));
