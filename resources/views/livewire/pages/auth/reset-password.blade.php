<?php

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    #[Locked]
    public string $token = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Mount the component.
     */
    public function mount(string $token): void
    {
        $this->token = $token;

        $this->email = request()->string('email');
    }

    /**
     * Two-key throttle (per-IP and per-email+IP) using the named
     * limiters registered in AppServiceProvider::boot().
     *
     * - reset-password: per-IP, 5 per 15 min
     * - reset-password-email: per-email+IP, 5 per 15 min
     *
     * The 6th attempt within the window is rejected with the
     * literal string "Too many attempts. Try again later."
     * Counters are NOT cleared on success — every attempt counts.
     */
    protected function throttleKeys(): array
    {
        $ipLimit = RateLimiter::limiter('reset-password')(request());
        $emailLimit = RateLimiter::limiter('reset-password-email')(
            request()->merge(['email' => $this->email])
        );

        return [$ipLimit->key, $emailLimit->key];
    }

    /**
     * Reset the password for the given user.
     */
    public function resetPassword(): void
    {
        [$ipKey, $emailKey] = $this->throttleKeys();

        // Check the cap on BOTH keys. If either is at 5/15min, reject.
        if (RateLimiter::tooManyAttempts($ipKey, 5)
            || RateLimiter::tooManyAttempts($emailKey, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Too many attempts. Try again later.',
            ]);
        }

        $this->validate([
            'token' => ['required'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        // Hit BOTH keys at the start of every attempt, success or fail.
        // Successful resets count too — the 6th call is rejected regardless
        // of prior outcomes. This is the user's hard rule.
        RateLimiter::hit($ipKey, 15 * 60);
        RateLimiter::hit($emailKey, 15 * 60);

        // Here we will attempt to reset the user's password. If it is successful we
        // will update the password on an actual user model and persist it to the
        // database. Otherwise we will parse the error and return the response.
        $status = Password::reset(
            $this->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) {
                $user->forceFill([
                    'password' => Hash::make($this->password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status != Password::PASSWORD_RESET) {
            $this->addError('email', __($status));

            return;
        }

        Session::flash('status', __($status));

        $this->redirectRoute('login', navigate: true);
    }
}; ?>

<div>
    {{-- Icon chip (key) — outside the h1, placed BEFORE it. --}}
    <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 mb-4"
          data-testid="auth-icon"
          aria-hidden="true">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z" />
        </svg>
    </span>

    <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-gray-900 mb-2"
        data-testid="auth-heading">{{ __('Set a new password') }}</h1>

    <form wire:submit="resetPassword">
        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input wire:model="email" id="email" class="block mt-1 w-full" type="email" name="email" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input wire:model="password" id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />

            <x-text-input wire:model="password_confirmation" id="password_confirmation" class="block mt-1 w-full"
                          type="password"
                          name="password_confirmation" required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <x-primary-button class="gst-btn w-full sm:w-auto !rounded-xl !shadow-lg !shadow-indigo-600/20">
                {{ __('Reset password') }}
            </x-primary-button>
        </div>
    </form>
</div>
