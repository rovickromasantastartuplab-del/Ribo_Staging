<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class IntegrationsSettingsController extends Controller
{
    /**
     * Update the company integrations settings.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request)
    {
        try {
            $validated = $request->validate([
                'wordpress_api_key' => 'nullable|string',
                'ai_intent_enabled' => 'nullable|boolean',
                'ai_auto_apply_threshold' => 'nullable|numeric|min:1|max:100',
            ]);

            foreach ($validated as $key => $value) {
                // Store boolean as string 'true' or 'false'
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
}
