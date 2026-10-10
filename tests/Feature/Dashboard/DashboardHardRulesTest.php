<?php

use App\Livewire\Dashboard\DashboardIndex;
use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;
use Livewire\Livewire;

/**
 * Step 5 — Hard-rules regression sweep.
 *
 * Verifies that nothing from the hard-rules list regresses on
 * /dashboard: no billing/upgrade text anywhere on the dashboard.
 *
 * Hard rule 1, 2: no Stripe / Cashier / /billing / Plan enum /
 * upgrade CTA. The Free-plan note is informational only.
 */

test('/dashboard has no billing, upgrade, stripe, or plan-CTA copy', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    Testimonial::factory()->for($space)->count(2)->create();

    $html = Livewire::actingAs($user)
        ->test(DashboardIndex::class)
        ->html();

    // No upgrade / billing language anywhere on the dashboard.
    expect($html)
        ->not->toContain('Upgrade')
        ->not->toContain('upgrade')
        ->not->toContain('/billing')
        ->not->toContain('Pro plan')
        ->not->toContain('stripe')
        ->not->toContain('Stripe')
        ->not->toContain('Subscribe')
        ->not->toContain('subscribed(');
});

test('/dashboard does not contain a /billing route via route()', function () {
    expect(route('dashboard'))->toBe(url('/dashboard'));
    // The /billing route does not exist in v1 (Hard Rule 1).
    expect(app('router')->getRoutes()->getByName('billing'))->toBeNull();
});

test('Free-plan note copy matches the spec exactly with default config', function () {
    $user = User::factory()->create();
    Space::factory()->for($user)->create();

    $html = (string) Livewire::actingAs($user)
        ->test(DashboardIndex::class)
        ->html();

    // The exact copy the spec pins (with the default config values
    // for the Free plan — see config/limits.php):
    //   "You're on the Free plan (3 Spaces, 100 testimonials per Space)."
    $expected = sprintf(
        "You're on the Free plan (%d Spaces, %d testimonials per Space).",
        (int) config('limits.max_spaces'),
        (int) config('limits.max_testimonials_per_space'),
    );
    expect($html)
        ->toContain($expected)
        ->and(substr_count($html, $expected))->toBe(1);
});
