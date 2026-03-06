<?php

namespace App\Services\Omnichannel;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FacebookLeadAdsService
{
    /**
     * Fetch real lead data from Facebook Graph API using a leadgen_id.
     *
     * Facebook Lead Ad webhooks only send a leadgen_id.
     * We must call the Graph API to get the actual form field values
     * (name, email, phone, etc.) that the user submitted.
     *
     * @param  string $leadgenId   The leadgen_id from the webhook payload
     * @param  string $accessToken The Page access token stored in social_accounts
     * @return array               Normalized lead data ['name', 'email', 'phone', 'raw_fields', 'form_id']
     */
    public function fetchLeadData(string $leadgenId, string $accessToken): array
    {
        $url = "https://graph.facebook.com/v23.0/{$leadgenId}";

        try {
            $response = Http::get($url, [
                'access_token' => $accessToken,
                'fields' => 'id,created_time,field_data,form_id,ad_id',
            ]);

            if ($response->failed()) {
                Log::error("Facebook Graph API error fetching lead {$leadgenId}", [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return $this->emptyResult($leadgenId);
            }

            $data = $response->json();

            return $this->normalizeFields($data, $leadgenId);

        } catch (\Exception $e) {
            Log::error("Exception fetching Facebook lead {$leadgenId}: {$e->getMessage()}");
            return $this->emptyResult($leadgenId);
        }
    }

    /**
     * Normalize the raw Graph API field_data array into a flat associative array.
     *
     * Facebook returns fields like:
     *   [{"name": "email", "values": ["john@test.com"]}, {"name": "full_name", "values": ["John Doe"]}]
     *
     * We flatten this into:
     *   ["email" => "john@test.com", "full_name" => "John Doe"]
     *
     * And also extract common CRM fields (name, email, phone) for direct use.
     */
    private function normalizeFields(array $data, string $leadgenId): array
    {
        $fieldData = $data['field_data'] ?? [];
        $rawFields = [];

        foreach ($fieldData as $field) {
            $fieldName = $field['name'] ?? '';
            $fieldValue = $field['values'][0] ?? '';
            if ($fieldName) {
                $rawFields[$fieldName] = $fieldValue;
            }
        }

        // Extract common fields using typical Facebook form field names
        $name = $rawFields['full_name']
            ?? $rawFields['name']
            ?? trim(($rawFields['first_name'] ?? '') . ' ' . ($rawFields['last_name'] ?? ''))
            ?: null;

        $email = $rawFields['email'] ?? null;
        $phone = $rawFields['phone_number'] ?? $rawFields['phone'] ?? null;

        return [
            'leadgen_id' => $leadgenId,
            'form_id' => $data['form_id'] ?? null,
            'ad_id' => $data['ad_id'] ?? null,
            'created_time' => $data['created_time'] ?? null,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'raw_fields' => $rawFields,
        ];
    }

    /**
     * Return an empty/fallback result when the API call fails.
     */
    private function emptyResult(string $leadgenId): array
    {
        return [
            'leadgen_id' => $leadgenId,
            'form_id' => null,
            'ad_id' => null,
            'created_time' => null,
            'name' => null,
            'email' => null,
            'phone' => null,
            'raw_fields' => [],
        ];
    }
}
