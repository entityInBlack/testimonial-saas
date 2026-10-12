<?php

use App\Livewire\Dashboard\DashboardIndex;
use App\Models\Space;
use App\Models\User;
use Livewire\Livewire;

/**
 * Wave 1, Task B (B4, B6): dashboard sentence case + loading text.
 *
 * TB3:
 *   - the counter-grid element's HTML contains no "uppercase" class;
 *   - the Free-plan note is still present exactly as
 *     FreePlanNoteTest asserts.
 *
 * TB4: for the granularity and the range select, the rendered
 * dashboard contains an element with wire:loading and the matching
 * wire:target whose text contains "Loading".
 *
 * Would fail if: any counter label reverts to "uppercase", the
 * Free-plan note text is touched, or the "Loading..." indicators
 * are removed.
 */
test('counter-grid has no "uppercase" class and the Free-plan note is unchanged', function () {
    config(['limits.max_spaces' => 3, 'limits.max_testimonials_per_space' => 100]);

    $user = User::factory()->create();
    Space::factory()->for($user)->create();

    $html = (string) Livewire::actingAs($user)
        ->test(DashboardIndex::class)
        ->html();

    // The grid wrapper itself (and its descendants) must not carry
    // an `uppercase` class — sentence case rule for the counter labels.
    preg_match('#<div[^>]*data-testid="counter-grid"[^>]*>.*?</div>\s*</div>#s', $html, $m);
    $grid = $m[0] ?? '';

    expect($grid)->not->toContain('uppercase')
        ->and($html)->toContain('data-testid="free-plan-note"')
        ->and($html)->toContain("You're on the Free plan (3 Spaces, 100 testimonials per Space).");
});

test('granularity control has a Loading indicator tied to its wire:target', function () {
    $user = User::factory()->create();
    Space::factory()->for($user)->create();

    $html = (string) Livewire::actingAs($user)
        ->test(DashboardIndex::class)
        ->html();

    // The Loading span lives next to the granularity select, is
    // rendered when the granularity request is in flight, and its
    // text contains "Loading".
    expect($html)
        ->toContain('data-testid="graph-granularity-loading"')
        ->toMatch('/wire:loading[^>]*wire:target="granularity"/')
        ->toMatch('/<span[^>]*wire:target="granularity"[^>]*>[^<]*Loading/i');
});

test('range control has a Loading indicator tied to its wire:target', function () {
    $user = User::factory()->create();
    Space::factory()->for($user)->create();

    $html = (string) Livewire::actingAs($user)
        ->test(DashboardIndex::class)
        ->html();

    expect($html)
        ->toContain('data-testid="graph-range-loading"')
        ->toMatch('/wire:loading[^>]*wire:target="range"/')
        ->toMatch('/<span[^>]*wire:target="range"[^>]*>[^<]*Loading/i');
});
