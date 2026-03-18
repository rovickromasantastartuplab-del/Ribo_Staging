<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\FieldMapping;
use Illuminate\Http\Request;

class IntegrationsSettingsController extends Controller
{
    /**
     * Update the company integrations settings.
     */
    public function update(Request $request)
    {
        try {
            $validated = $request->validate([
                'wordpress_api_key' => 'nullable|string',
                'ai_intent_enabled' => 'nullable|boolean',
                'ai_auto_apply_threshold' => 'nullable|numeric|min:1|max:100',
                'google_client_id' => 'nullable|string',
                'google_client_secret' => 'nullable|string',
                'google_redirect_uri' => 'nullable|string',
                'google_gmail_pub_sub_topic' => 'nullable|string',
                'pusher_app_id' => 'nullable|string',
                'pusher_app_key' => 'nullable|string',
                'pusher_app_secret' => 'nullable|string',
                'pusher_app_cluster' => 'nullable|string',
            ]);

            // Only superadmins may update Google OAuth and Pusher credentials
            $user = auth()->user();
            $restrictedFields = [
                'google_client_id', 'google_client_secret', 'google_redirect_uri', 'google_gmail_pub_sub_topic',
                'pusher_app_id', 'pusher_app_key', 'pusher_app_secret', 'pusher_app_cluster'
            ];
            if (!in_array($user->type, ['superadmin', 'super admin'])) {
                $validated = array_diff_key($validated, array_flip($restrictedFields));
            }

            foreach ($validated as $key => $value) {
                if (is_bool($value)) {
                    $value = $value ? 'true' : 'false';
                }
                updateSetting($key, $value);
            }

            return redirect()->back()->with('success', __('Integrations settings updated successfully.'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Failed to update integrations settings: :error', ['error' => $e->getMessage()]));
        }
    }

    /**
     * Get all field mappings for the current company and provider.
     */
    public function getFieldMappings(string $provider)
    {
        $user = auth()->user();
        $mappings = FieldMapping::where('user_id', $user->id)
            ->where('provider', $provider)
            ->get(['id', 'external_field', 'crm_field', 'default_value']);

        return response()->json($mappings);
    }

    /**
     * Save (bulk upsert) field mappings for the current company and provider.
     */
    public function saveFieldMappings(Request $request, string $provider)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'mappings' => 'required|array',
            'mappings.*.external_field' => 'required|string|max:255',
            'mappings.*.crm_field' => 'required|string|max:255',
            'mappings.*.default_value' => 'nullable|string|max:255',
        ]);

        // Delete old mappings for this provider, then insert new ones
        FieldMapping::where('user_id', $user->id)
            ->where('provider', $provider)
            ->delete();

        foreach ($validated['mappings'] as $mapping) {
            FieldMapping::create([
                'user_id' => $user->id,
                'provider' => $provider,
                'external_field' => $mapping['external_field'],
                'crm_field' => $mapping['crm_field'],
                'default_value' => $mapping['default_value'] ?? null,
            ]);
        }

        return response()->json(['message' => 'Field mappings saved successfully.']);
    }
}

