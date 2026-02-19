<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile settings.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        try {
            $validated = $request->validated();
            
            // Remove _method from validated data if present
            unset($validated['_method']);
            
            // Handle avatar upload
            if ($request->hasFile('avatar')) {
                \Log::info('Avatar file detected', [
                    'file' => $request->file('avatar')->getClientOriginalName(),
                    'size' => $request->file('avatar')->getSize(),
                    'mime' => $request->file('avatar')->getMimeType()
                ]);
                
                // Delete old avatar if exists
                if ($request->user()->avatar && Storage::disk('public')->exists($request->user()->avatar)) {
                    Storage::disk('public')->delete($request->user()->avatar);
                    \Log::info('Old avatar deleted', ['path' => $request->user()->avatar]);
                }
                
                // Store new avatar
                $avatarPath = $request->file('avatar')->store('avatars', 'public');
                $validated['avatar'] = $avatarPath;
                \Log::info('New avatar stored', ['path' => $avatarPath]);
            } else {
                // Don't update avatar field if no file is uploaded
                unset($validated['avatar']);
            }
            
            $user = $request->user();
            $user->fill($validated);

            if ($user->isDirty('email')) {
                $user->email_verified_at = null;
            }

            $user->save();
            
            // Refresh the user instance to get the latest data
            $user->refresh();
            
            \Log::info('Profile updated successfully', ['user_id' => $user->id, 'avatar' => $user->avatar]);

            return redirect()->route('profile')->with('status', 'profile-updated');
            
        } catch (\Exception $e) {
            \Log::error('Profile update failed', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id
            ]);
            
            return back()->withErrors(['avatar' => 'Failed to update profile. Please try again.']);
        }
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
