<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Two-key throttle (per-IP and per-email+IP) using the named
     * limiters registered in AppServiceProvider::boot().
     *
     * - register: per-IP, 5 per 15 min
     * - register-email: per-email+IP, 5 per 15 min
     *
     * The 6th attempt within the window is rejected with the
     * literal string "Too many attempts. Try again later."
     * Counters are NOT cleared on success — every attempt counts,
     * including successful registrations.
     */
    protected function throttleKeys(): array
    {
        $ipLimit = RateLimiter::limiter('register')(request());
        $emailLimit = RateLimiter::limiter('register-email')(
            request()->merge(['email' => $this->email])
        );

        return [$ipLimit->key, $emailLimit->key];
    }

    /**
     * Handle an incoming registration request.
     */
    public function register(): void
    {
        [$ipKey, $emailKey] = $this->throttleKeys();

        // Check the cap on BOTH keys. If either is at 5/15min, reject.
        if (RateLimiter::tooManyAttempts($ipKey, 5)
            || RateLimiter::tooManyAttempts($emailKey, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Too many attempts. Try again later.',
            ]);
        }

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        // Hit BOTH keys at the start of every attempt, success or fail.
        // Successful registrations count too — the 6th call is rejected
        // regardless of prior outcomes. This is the user's hard rule.
        RateLimiter::hit($ipKey, 15 * 60);
        RateLimiter::hit($emailKey, 15 * 60);

        $validated['password'] = Hash::make($validated['password']);

        event(new Registered($user = User::create($validated)));

        Auth::login($user);

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <form wire:submit="register">
        <!-- Name -->
        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input wire:model="name" id="name" class="block mt-1 w-full" type="text" name="name" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <!-- Email Address -->
        <div class="mt-4">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input wire:model="email" id="email" class="block mt-1 w-full" type="email" name="email" required autocomplete="username" />
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
            <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('login') }}" wire:navigate>
                {{ __('Already registered?') }}
            </a>

            <x-primary-button class="ms-4">
                {{ __('Register') }}
            </x-primary-button>
        </div>
    </form>
</div>
