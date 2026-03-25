<?php

namespace App\Observers;

use App\Models\User;
use App\Models\Plan;

class UserObserver
{
    /**
     * Handle the User "creating" event.
     */
    public function creating(User $user): void
    {
        // If user is company type and has no plan_id, assign default plan
        if ($user->type === 'company' && is_null($user->plan_id)) {
            $defaultPlan = Plan::getDefaultPlan();
            if ($defaultPlan) {
                $user->plan_id = $defaultPlan->id;
                $user->plan_is_active = 1;
            }
        }
    }
    
    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        // Generate a unique referral code if not already set
        if (auth()->check() && auth()->user()->isSuperAdmin()) {
            do {
                $code = rand(100000, 999999);
            } while (User::where('referral_code', $code)->exists());
            
            $user->referral_code = $code;
            $user->save();
        }
        
        // Create default settings for new users
        if ($user->isSuperAdmin()) {
            createDefaultSettings($user->id);
        } elseif ($user->type === 'company') {
            copySettingsFromSuperAdmin($user->id);
        }
    }
}