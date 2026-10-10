<?php

use App\Livewire\Dashboard\DashboardIndex;
use App\Models\User;
use Livewire\Livewire;

/**
 * Step 5 — basic access to /dashboard.
 *
 * Spec: "Route: GET /dashboard (auth group)." The route is
 * `auth` only (NOT `verified`); Breeze email verification is
 * off in v1.
 */

test('guests are redirected to login from /dashboard', function () {
    $this->get('/dashboard')
        ->assertRedirect(route('login'));
});

test('authenticated users can render the dashboard', function () {
    $user = User::factory()->create();
    \App\Models\Space::factory()->for($user)->create();

    Livewire::actingAs($user)
        ->test(DashboardIndex::class)
        ->assertOk()
        // The Active tab button is in the component's view
        // (the page title is in the header slot, which
        // Livewire::test does NOT render — the layout does).
        ->assertSeeHtml('data-testid="tab-active"')
        ->assertSeeHtml('data-testid="counter-grid"');
});

test('the dashboard layout still includes the navigation menu', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertSeeVolt('layout.navigation');
});
