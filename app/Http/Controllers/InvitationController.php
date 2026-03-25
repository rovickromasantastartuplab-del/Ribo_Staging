<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;

class InvitationController extends Controller
{
    /**
     * Show the invitation acceptance page.
     */
    public function accept(string $token)
    {
        $user = User::where('invitation_token', $token)->first();

        if (!$user) {
            return redirect()->route('login')->withErrors([
                'email' => __('This invitation link is invalid or has already been used.'),
            ]);
        }

        return Inertia::render('auth/accept-invitation', [
            'token' => $token,
            'userName' => $user->name,
            'userEmail' => $user->email,
            'companyName' => (function () use ($user) {
                $creator = User::find($user->created_by);
                if (!$creator)
                    return '';
                // If the creator is a staff member, walk up to the owner
                if ($creator->type !== 'company') {
                    $owner = User::find($creator->created_by);
                    return $owner?->company_name ?? $creator->company_name ?? $creator->name ?? '';
                }
                return $creator->company_name ?? $creator->name ?? '';
            })(),
        ]);
    }

    /**
     * Complete the invitation by setting a password.
     */
    public function complete(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::where('invitation_token', $request->token)->first();

        if (!$user) {
            return back()->withErrors([
                'token' => __('This invitation link is invalid or has already been used.'),
            ]);
        }

        $user->update([
            'password' => Hash::make($request->password),
            'email_verified_at' => now(),
            'invitation_token' => null,
            'is_enable_login' => 1,
        ]);

        Auth::login($user);

        return redirect()->route('dashboard');
    }
}