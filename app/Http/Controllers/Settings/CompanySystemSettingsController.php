<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CompanySystemSettingsController extends Controller
{
    /**
     * Update the company system settings.
     *
     * Handles company-level configuration including:
     * - Language and localization settings
     * - Date/time formats and timezone
     * - Excludes email verification and landing page settings
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request)
    {
        try {
            $validated = $request->validate([
                'defaultLanguage' => 'required|string',
                'dateFormat' => 'required|string',
                'timeFormat' => 'required|string',
                'defaultTimezone' => 'required|string',
                'calendarStartDay' => 'nullable|string',
            ]);

            foreach ($validated as $key => $value) {
                if (!is_null($value)) {
                    updateSetting($key, $value);
                }
            }

            // Sync the company user's personal language with their chosen default
            if (isset($validated['defaultLanguage'])) {
                $user = auth()->user();
                if ($user) {
                    $user->update(['lang' => $validated['defaultLanguage']]);
                }
            }

            return redirect()->back()->with('success', __('System settings updated successfully.'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Failed to update system settings: :error', ['error' => $e->getMessage()]));
        }
    }
}