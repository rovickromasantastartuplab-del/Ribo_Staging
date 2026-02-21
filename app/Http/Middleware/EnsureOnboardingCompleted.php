<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureOnboardingCompleted
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();

        if (!$user) {
            return $next($request);
        }

        // Super admin bypasses onboarding
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // Only company-type owners need onboarding
        if ($user->type === 'company' && !$user->hasCompletedOnboarding()) {
            if (!$request->routeIs('onboarding.*') && !$request->routeIs('logout')) {
                return redirect()->route('onboarding.company');
            }
        }

        return $next($request);
    }
}
