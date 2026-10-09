<?php

use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    // Flush the cache between tests so per-IP and per-email counters
    // don't leak from one test into the next.
    \Illuminate\Support\Facades\Cache::flush();
});

/**
 * Hard rule 4 (build order):
 *   - Two-key throttle per protected action (per-IP and per-email+IP)
 *   - 5 attempts per 15 minutes
 *   - tooManyAttempts and hit at the START of every attempt, success or fail
 *   - The 6th call is rejected with the literal error
 *     "Too many attempts. Try again later."
 *   - Counters are NOT cleared on success.
 *
 * Each test below drives 5 valid attempts (each hits both keys via
 * RateLimiter::hit at the start of the action) and then asserts the
 * 6th attempt fails with the exact error.
 */

test('forgot-password Livewire action throttles at 6th attempt with the expected error', function () {
    $user = User::factory()->create();

    // 5 successful sends. The component hits both keys at the start of
    // every attempt, so the counter accumulates to 5 after 5 calls.
    // Password::sendResetLink succeeds for a valid existing email,
    // RESET_LINK_SENT is returned, no DB error, and the 5 calls
    // complete cleanly.
    for ($i = 0; $i < 5; $i++) {
        Livewire::test('pages.auth.forgot-password')
            ->set('email', $user->email)
            ->call('sendPasswordResetLink');
    }

    // 6th call: per-IP key is at 5, per-email+IP key is at 5, throttle
    // must trip with the exact error message.
    Livewire::test('pages.auth.forgot-password')
        ->set('email', $user->email)
        ->call('sendPasswordResetLink')
        ->assertHasErrors(['email' => 'Too many attempts. Try again later.']);
});

test('register Livewire action throttles at 6th attempt with the expected error', function () {
    // 5 successful registrations from the same IP. The component hits
    // both keys at the start of every attempt. Note: registration
    // creates a User, so we use 5 different emails.
    for ($i = 0; $i < 5; $i++) {
        Livewire::test('pages.auth.register')
            ->set('name', "User {$i}")
            ->set('email', "user{$i}@example.test")
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->call('register');
    }

    // 6th call: per-IP key is at 5, throttle must trip.
    Livewire::test('pages.auth.register')
        ->set('name', 'User 6')
        ->set('email', 'user6@example.test')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('register')
        ->assertHasErrors(['email' => 'Too many attempts. Try again later.']);
});

test('reset-password Livewire action throttles at 6th attempt with the expected error', function () {
    $user = User::factory()->create();

    // 5 attempts with a wrong token. Password::reset returns INVALID_TOKEN,
    // the component throws the error, the throttle has already been hit
    // (at the start of the attempt, before the broker call). After 5
    // calls, both keys are at 5.
    for ($i = 0; $i < 5; $i++) {
        Livewire::test('pages.auth.reset-password', ['token' => 'wrong-token'])
            ->set('email', $user->email)
            ->set('password', 'new-password')
            ->set('password_confirmation', 'new-password')
            ->call('resetPassword');
    }

    // 6th call: throttle trips before the reset logic runs.
    Livewire::test('pages.auth.reset-password', ['token' => 'wrong-token'])
        ->set('email', $user->email)
        ->set('password', 'new-password')
        ->set('password_confirmation', 'new-password')
        ->call('resetPassword')
        ->assertHasErrors(['email' => 'Too many attempts. Try again later.']);
});

test('the six named limiters are registered in AppServiceProvider', function () {
    // The named limiters centralize the cap shape (5 / 15 min) and the
    // key derivation (per-IP or per-email+IP). The Livewire components
    // call RateLimiter::limiter('<name>') to get the Limit object and
    // then use $limit->key with tooManyAttempts / hit.
    foreach ([
        'forgot-password',
        'forgot-password-email',
        'reset-password',
        'reset-password-email',
        'register',
        'register-email',
    ] as $name) {
        $callback = \Illuminate\Support\Facades\RateLimiter::limiter($name);
        expect($callback)->not->toBeNull("limiter `{$name}` is not registered");
    }

    // Each limiter returns a Limit with maxAttempts=5 and decay=900s (15 min)
    // for any Request (the per-IP variants depend only on $request->ip(),
    // the per-email+IP variants also read $request->input('email')).
    $ipLimit = \Illuminate\Support\Facades\RateLimiter::limiter('forgot-password')(request());
    expect($ipLimit->maxAttempts)->toBe(5);
    expect($ipLimit->key)->toBe('forgot-password|ip|'.request()->ip());

    $emailLimit = \Illuminate\Support\Facades\RateLimiter::limiter('forgot-password-email')(
        request()->merge(['email' => 'someone@example.test'])
    );
    expect($emailLimit->maxAttempts)->toBe(5);
    expect($emailLimit->key)->toBe('forgot-password|email|someone@example.test|'.request()->ip());
});
