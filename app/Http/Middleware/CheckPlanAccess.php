<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Models\Plan;
use App\Models\User;

class CheckPlanAccess
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();

        if (!$user) {
            return $next($request);
        }

        // Super admin has full access
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // Staff user checks
        if ($user->type !== 'company') {
            $company = User::find($user->created_by);
            if ($company && $company->type === 'company') {
                // Log out staff if company is disabled by super admin
                if ($company->status === 'inactive') {
                    auth()->logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();
                    return redirect()->route('login')
                        ->with('error', __('Your company account has been disabled. Please contact administrator.'));
                }

                if ($company->isPlanExpired()) {
                    auth()->logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();
                    return redirect()->route('login')->with('error', __('Access denied. Only company users can access this area.'));
                }

                // Re-evaluate which staff are within the plan limit periodically.
                // Throttled to once every 5 minutes per company to avoid per-request DB overhead.
                $syncKey = 'staff_sync_' . $company->id;
                if (!Cache::has($syncKey)) {
                    syncStaffUserLoginAccess($company);
                    Cache::put($syncKey, true, now()->addMinutes(5));
                }

                // Reload to get the freshly updated is_enable_login value.
                $user->refresh();

                if ((int) $user->is_enable_login === 0) {
                    auth()->logout();
                    return redirect()->route('login')->with('error', __('Your account has been temporarily disabled because your company has exceeded its user limit. Please contact your administrator.'));
                }
            }
        }


        // Check if user needs plan subscription
        if ($user->needsPlanSubscription()) {
            $message = __('Please subscribe to a plan to continue.');

            if ($user->isTrialExpired()) {
                $message = __('Your trial period has expired. Please subscribe to a plan to continue.');
                // Reset trial status
                $user->update([
                    'plan_id' => null,
                    'is_trial' => 0,
                    'trial_expire_date' => null
                ]);
            } elseif ($user->isPlanExpired()) {
                $message = __('Your plan has expired. Please renew your subscription.');
                // Reset expired plan
                $user->update([
                    'plan_id' => null,
                    'plan_expire_date' => null
                ]);
            }

            return redirect()->route('plans.index')->with('error', $message);
        }

        return $next($request);
    }
}