<?php

use App\Livewire\Dashboard\DashboardIndex;
use App\Models\Space;
use App\Models\User;
use Livewire\Livewire;

/**
 * Step 5 — Empty states.
 *
 * Spec:
 *   - no Spaces → "Create your first Space"
 *   - Spaces but no testimonials → "Share your link to start collecting."
 */

test('no Spaces empty state shows the exact string "Create your first Space"', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(DashboardIndex::class)
        ->assertSeeHtml('data-testid="empty-no-spaces"')
        ->assertSeeText('Create your first Space');
});

test('Spaces but no testimonials empty state shows the exact string', function () {
    $user = User::factory()->create();
    Space::factory()->for($user)->create();

    Livewire::actingAs($user)
        ->test(DashboardIndex::class)
        ->assertSeeHtml('data-testid="empty-no-testimonials"')
        ->assertSeeText('Share your link to start collecting.');
});

test('when the owner has live Spaces AND live testimonials, no empty state shows', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    \App\Models\Testimonial::factory()->for($space)->create();

    Livewire::actingAs($user)
        ->test(DashboardIndex::class)
        ->assertDontSeeHtml('data-testid="empty-no-spaces"')
        ->assertDontSeeHtml('data-testid="empty-no-testimonials"');
});
