<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPlanFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        if ($user->type === 'company') {
            $user->loadMissing('plan');
            $plan = $user->plan;
        } else {
            $user->loadMissing('creator.plan');
            $plan = $user->creator?->plan;
        }

        if (!$plan) {
            return $this->deny($request);
        }

        if ($this->planHasFeature($plan, $feature)) {
            return $next($request);
        }

        return $this->deny($request);
    }

    private function planHasFeature($plan, string $feature): bool
    {
        $features = is_array($plan->module) ? $plan->module : [];
        $features = array_values(array_unique(array_filter($features, fn ($v) => is_string($v) && $v !== '')));

        if (in_array($feature, $features, true)) {
            return true;
        }

        return match ($feature) {
            'branding' => $plan->enable_branding === 'on',
            'ai_integration' => $plan->enable_chatgpt === 'on',
            'trial' => $plan->is_trial === 'on',
            default => false,
        };
    }

    private function deny(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Feature not available in your plan.'], 403);
        }

        return redirect()->route('dashboard.redirect')->with('error', __('Feature not available in your plan.'));
    }
}
