<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiters();
    }

    /**
     * v1 has six named rate limiters — one pair per protected action.
     *
     * Each protected action (forgot-password, reset-password, register)
     * throttles on TWO keys:
     *   - a per-IP key (stops a single attacker cycling emails)
     *   - a per-email+IP key (stops a single account being spammed from
     *     many IPs, and stops a real user from being locked out by an
     *     attacker on a different IP)
     *
     * Both keys must be under the cap to proceed. The cap is 5 attempts
     * per 15 minutes (data-model §3.1 / build-order hard rule 4).
     *
     * The named limiters below pin the cap shape and the key derivation.
     * The Livewire components consume them by calling
     * RateLimiter::limiter('<name>') and using the key it returns, then
     * gating on tooManyAttempts(key, 5) and hit(key, 15 * 60). Counters
     * are NOT cleared on success — successful attempts count too, so the
     * 6th call within the window is rejected regardless of prior outcomes.
     */
    protected function configureRateLimiters(): void
    {
        // per-IP limiters — one per action
        RateLimiter::for('forgot-password', function (Request $request) {
            return Limit::perMinutes(15, 5)->by('forgot-password|ip|'.$request->ip());
        });

        RateLimiter::for('reset-password', function (Request $request) {
            return Limit::perMinutes(15, 5)->by('reset-password|ip|'.$request->ip());
        });

        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinutes(15, 5)->by('register|ip|'.$request->ip());
        });

        // per-email+IP limiters — one per action
        // The email is read from the request body so the limiter can be
        // resolved at the start of the Livewire action, before $this->email
        // is bound (Livewire passes the request through the limiter
        // callback's $request argument, not the component).
        RateLimiter::for('forgot-password-email', function (Request $request) {
            $email = strtolower((string) $request->input('email', ''));

            return Limit::perMinutes(15, 5)->by('forgot-password|email|'.$email.'|'.$request->ip());
        });

        RateLimiter::for('reset-password-email', function (Request $request) {
            $email = strtolower((string) $request->input('email', ''));

            return Limit::perMinutes(15, 5)->by('reset-password|email|'.$email.'|'.$request->ip());
        });

        RateLimiter::for('register-email', function (Request $request) {
            $email = strtolower((string) $request->input('email', ''));

            return Limit::perMinutes(15, 5)->by('register|email|'.$email.'|'.$request->ip());
        });
    }
}
