<?php

use App\Livewire\Dashboard\DashboardIndex;
use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;
use Livewire\Livewire;

/**
 * Step 5 — Free-plan info note.
 *
 * Spec: exact copy:
 *   "You're on the Free plan (3 Spaces, 100 testimonials per Space)."
 *
 * Rules:
 *   - Above the counter cards, below the page title.
 *   - No link, no button, no CTA. There is no /billing route in v1.
 *   - Renders only when the authenticated user owns at least one
 *     live Space.
 */

test('Free-plan note renders with exact copy when the owner has at least one live Space', function () {
    $user = User::factory()->create();
    Space::factory()->for($user)->create();

    $html = (string) Livewire::actingAs($user)
        ->test(DashboardIndex::class)
        ->html();

    // The exact copy the spec pins. The apostrophe is rendered
    // as a literal character (Blade's `{{ }}` does not encode
    // apostrophes — only the HTML-special set).
    expect($html)
        ->toContain('data-testid="free-plan-note"')
        ->toContain("You're on the Free plan (3 Spaces, 100 testimonials per Space).");
});

test('Free-plan note copy reflects mutated limits config', function () {
    // Mutate the config BEFORE the component renders so the
    // computed `maxSpaces` / `maxTestimonialsPerSpace` read the
    // new values. This proves the note is not hardcoded.
    config(['limits.max_spaces' => 5]);
    config(['limits.max_testimonials_per_space' => 50]);

    $user = User::factory()->create();
    Space::factory()->for($user)->create();

    $html = (string) Livewire::actingAs($user)
        ->test(DashboardIndex::class)
        ->html();

    expect($html)
        ->toContain("You're on the Free plan (5 Spaces, 50 testimonials per Space).")
        // The default copy must not appear when config is mutated.
        ->not->toContain("You're on the Free plan (3 Spaces, 100 testimonials per Space).");
});

test('Free-plan note is absent when the owner has zero live Spaces', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(DashboardIndex::class)
        ->assertDontSeeHtml('data-testid="free-plan-note"')
        ->assertDontSeeText("You're on the Free plan");
});

test('Free-plan note is absent when the owner only has soft-deleted Spaces', function () {
    $user = User::factory()->create();
    $deleted = Space::factory()->for($user)->create();
    $deleted->delete();

    Livewire::actingAs($user)
        ->test(DashboardIndex::class)
        ->assertDontSeeHtml('data-testid="free-plan-note"');
});

test('Free-plan note does not contain any link, button, or /billing reference', function () {
    $user = User::factory()->create();
    Space::factory()->for($user)->create();

    $html = (string) Livewire::actingAs($user)
        ->test(DashboardIndex::class)
        ->html();

    // No /billing anywhere on the dashboard.
    expect($html)->not->toContain('/billing');

    // The Free-plan note must NOT be wrapped in an <a> element.
    // We check a 400-char window around the copy and assert there
    // is no opening <a tag inside it.
    $pos = strpos($html, "You're on the Free plan");
    expect($pos)->not->toBeFalse();
    $window = substr($html, max(0, $pos - 200), 400);
    expect($window)
        ->not->toContain('<a')
        ->not->toContain('<button')
        ->not->toContain('href=');
});
