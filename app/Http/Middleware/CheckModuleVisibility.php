<?php

namespace App\Http\Middleware;

use App\Services\ModuleVisibilityService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckModuleVisibility
{
    /**
     * Handle an incoming request.
     *
     * Usage: ->middleware('checkModuleVisibility:leads')
     *
     * Superadmins bypass this check entirely.
     * If the module key is in the global disabled list, returns 403.
     */
    public function handle(Request $request, Closure $next, string $moduleKey): Response
    {
        $user = $request->user();

        // Superadmin always has access
        if ($user && $user->isSuperAdmin()) {
            return $next($request);
        }

        $disabled = ModuleVisibilityService::getDisabledModules();

        if (in_array($moduleKey, $disabled)) {
            abort(403, 'This module is not available.');
        }

        return $next($request);
    }
}
