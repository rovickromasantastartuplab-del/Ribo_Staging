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

        // Check account status BEFORE Auth::attempt() to avoid destroying the session/CSRF token
        $user = \App\Models\User::where('email', $this->input('email'))->first();

        if ($user) {
            // Check if user account is inactive
            if ($user->status === 'inactive') {
                RateLimiter::hit($this->throttleKey());
                throw ValidationException::withMessages([
                    'email' => __('Your account is inactive. Please contact administrator.'),
                ]);
            }

            // For staff users: block login if company is disabled by super admin
            if ($user->type !== 'company' && $user->type !== 'superadmin') {
                $company = \App\Models\User::find($user->created_by);
                if ($company && $company->type === 'company' && $company->status === 'inactive') {
                    RateLimiter::hit($this->throttleKey());
                    throw ValidationException::withMessages([
                        'email' => __('Your company account has been disabled. Please contact administrator.'),
                    ]);
                }
            }
        }

        // Now attempt login — session and CSRF token are untouched if we reach here
        if (!Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
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
