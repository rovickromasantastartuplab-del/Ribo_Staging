<?php

namespace App\Http\Controllers;

use App\Services\ModuleVisibilityService;
use Illuminate\Http\Request;

class ModuleVisibilityController extends Controller
{
    /**
     * Return the current module visibility state (for the settings page).
     * Returns a JSON response consumed by the frontend via router.get / usePage props.
     */
    public function index()
    {
        $modules = ModuleVisibilityService::modules();
        $disabled = ModuleVisibilityService::getDisabledModules();

        // Build a [ key => is_enabled ] map for the frontend
        $visibility = [];
        foreach ($modules as $key => $label) {
            $visibility[$key] = [
                'key'     => $key,
                'label'   => $label,
                'enabled' => !in_array($key, $disabled),
            ];
        }

        return response()->json([
            'modules' => array_values($visibility),
        ]);
    }

    /**
     * Toggle a single module on or off globally.
     *
     * @param  Request  $request  { module_key: string, enabled: bool }
     */
    public function toggle(Request $request)
    {
        $validated = $request->validate([
            'module_key' => 'required|string|in:' . implode(',', array_keys(ModuleVisibilityService::modules())),
            'enabled'    => 'required|boolean',
        ]);

        $disabled = ModuleVisibilityService::getDisabledModules();

        if ($validated['enabled']) {
            // Remove from disabled list
            $disabled = array_values(array_filter($disabled, fn($k) => $k !== $validated['module_key']));
        } else {
            // Add to disabled list if not already there
            if (!in_array($validated['module_key'], $disabled)) {
                $disabled[] = $validated['module_key'];
            }
        }

        ModuleVisibilityService::setDisabledModules($disabled);

        return response()->json(['success' => true]);
    }
}
