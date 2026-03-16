<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        // --- Pre-flight access checks (before Auth::attempt) ---
        // Checks run before Auth::attempt() to avoid session mutation/CSRF issues (419).
        $candidate = \App\Models\User::where('email', $this->string('email')->value())->first();

        if ($candidate) {
            // Check if account is inactive
            if ($candidate->status === 'inactive') {
                RateLimiter::hit($this->throttleKey());
                throw ValidationException::withMessages([
                    'email' => __('Your account is inactive. Please contact administrator.'),
                ]);
            }

            // For staff users: sync plan limits then check if login is allowed
            if ($candidate->type !== 'company' && $candidate->type !== 'superadmin') {
                $company = \App\Models\User::find($candidate->created_by);
                if ($company && $company->type === 'company') {
                    // Block login if the company account is disabled by super admin
                    if ($company->status === 'inactive') {
                        RateLimiter::hit($this->throttleKey());
                        throw ValidationException::withMessages([
                            'email' => __('Your company account has been disabled. Please contact administrator.'),
                        ]);
                    }

                    syncStaffUserLoginAccess($company);
                    $candidate->refresh();
                }

                if ((int) $candidate->is_enable_login === 0) {
                    RateLimiter::hit($this->throttleKey());
                    throw ValidationException::withMessages([
                        'email' => __('Your account has been temporarily disabled because your company has exceeded its user limit. Please contact your administrator to upgrade the plan.'),
                    ]);
                }
            }
        }

        // --- Authenticate credentials ---
        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }


    /**
     * Ensure the login request is not rate limited.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (!RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')) . '|' . $this->ip());
    }
}
