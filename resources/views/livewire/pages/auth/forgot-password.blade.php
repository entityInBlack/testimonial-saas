<?php

use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $email = '';

    /**
     * Two-key throttle (per-IP and per-email+IP) using the named
     * limiters registered in AppServiceProvider::boot().
     *
     * - forgot-password: per-IP, 5 per 15 min
     * - forgot-password-email: per-email+IP, 5 per 15 min
     *
     * The 6th attempt within the window is rejected with the
     * literal string "Too many attempts. Try again later."
     * Counters are NOT cleared on success — every attempt counts.
     */
    protected function throttleKeys(): array
    {
        $ipLimit = RateLimiter::limiter('forgot-password')(request());
        $emailLimit = RateLimiter::limiter('forgot-password-email')(
            request()->merge(['email' => $this->email])
        );

        return [$ipLimit->key, $emailLimit->key];
    }

    /**
     * Send a password reset link to the provided email address.
     *
     * @throws ValidationException
     */
    public function sendPasswordResetLink(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        [$ipKey, $emailKey] = $this->throttleKeys();

        // Check the cap on BOTH keys. If either is at 5/15min, reject.
        if (RateLimiter::tooManyAttempts($ipKey, 5)
            || RateLimiter::tooManyAttempts($emailKey, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Too many attempts. Try again later.',
            ]);
        }

        // Hit BOTH keys at the start of every attempt, success or fail.
        // Successful sends count too — the 6th call is rejected regardless
        // of prior outcomes. This is the user's hard rule.
        RateLimiter::hit($ipKey, 15 * 60);
        RateLimiter::hit($emailKey, 15 * 60);

        $status = Password::sendResetLink(
            $this->only('email')
        );

        if ($status != Password::RESET_LINK_SENT) {
            $this->addError('email', __($status));

            return;
        }

        $this->reset('email');

        session()->flash('status', __($status));
    }
}; ?>

<div>
    {{-- Icon chip (envelope) — outside the h1, placed BEFORE it. --}}
    <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 mb-4"
          data-testid="auth-icon"
          aria-hidden="true">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
        </svg>
    </span>

    <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-gray-900 mb-2"
        data-testid="auth-heading">{{ __('Forgot your password') }}</h1>

    <div class="mb-6 text-sm text-gray-600">
        {{ __('Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one.') }}
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form wire:submit="sendPasswordResetLink">
        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input wire:model="email" id="email" class="block mt-1 w-full" type="email" name="email" required autofocus />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <x-primary-button class="gst-btn w-full sm:w-auto !rounded-xl !shadow-lg !shadow-indigo-600/20">
                {{ __('Email password reset link') }}
            </x-primary-button>
        </div>
    </form>
</div>
