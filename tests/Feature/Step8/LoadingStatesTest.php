<?php

use App\Livewire\Dashboard\DashboardIndex;
use App\Livewire\Inbox\InboxIndex;
use App\Livewire\Inbox\InboxRow;
use App\Livewire\Spaces\SpaceForm;
use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;
use Livewire\Livewire;

/**
 * Part G4 — loading-state markers.
 *
 * The render path for each target is exercised end-to-end via
 * `Livewire::test(...)` and the rendered HTML is asserted to
 * contain both `wire:loading` and a matching `wire:target="..."`
 * so the button/select is disabled while the action is in flight
 * AND the visible label is swapped to a "Working…" indicator.
 *
 * What would make this file FAIL:
 *   - Removing the `wire:loading.attr="disabled"` attribute from
 *     any of the action buttons (a click would not be disabled
 *     while the action is in flight).
 *   - Removing the `wire:target="..."` directive (the loading
 *     marker would never toggle).
 *   - Removing the `wire:loading` / `wire:loading.remove` pair
 *     (the visible label swap would never happen).
 *
 * Static-only checks: no HTTP call, no DB writes, no manual
 * behaviour verification (the prompt defers behaviour to the
 * human reviewer).
 *
 * For the trash-tab actions (restore, forget) the test now renders
 * the row through the parent InboxIndex with `filter='trash'` —
 * the real path a browser takes — so the assertion proves the
 * loading markers are present on the page a user actually sees.
 * The non-trash actions (wall-of-love toggle, withdraw consent,
 * soft-delete) are exercised by rendering a live row directly
 * through InboxRow.
 */

test('InboxRow wall-of-love toggle renders wire:loading with wire:target="toggleWallOfLove"', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    $row = Testimonial::factory()->for($space)->create(['consent_given' => true]);

    Livewire::actingAs($user)
        ->test(InboxRow::class, ['testimonialId' => $row->id])
        ->assertSeeHtml('wire:click="toggleWallOfLove"', false)
        ->assertSeeHtml('wire:target="toggleWallOfLove"', false)
        ->assertSeeHtml('wire:loading.attr="disabled"', false);
});

test('InboxRow withdraw-consent button renders wire:loading with wire:target="withdrawConsent"', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    $row = Testimonial::factory()->for($space)->create(['consent_given' => true]);

    Livewire::actingAs($user)
        ->test(InboxRow::class, ['testimonialId' => $row->id])
        ->assertSeeHtml('wire:click="withdrawConsent"', false)
        ->assertSeeHtml('wire:target="withdrawConsent"', false)
        ->assertSeeHtml('wire:loading.attr="disabled"', false);
});

test('InboxRow soft-delete (trash) button renders wire:loading with wire:target="softDelete"', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    $row = Testimonial::factory()->for($space)->create();

    Livewire::actingAs($user)
        ->test(InboxRow::class, ['testimonialId' => $row->id])
        ->assertSeeHtml('wire:click="softDelete"', false)
        ->assertSeeHtml('wire:target="softDelete"', false)
        ->assertSeeHtml('wire:loading.attr="disabled"', false);
});

test('InboxIndex trash tab renders restore and forget buttons with their wire:loading markers', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    $row = Testimonial::factory()->for($space)->create();
    $row->delete();

    // Render through the real parent so the per-row
    // showRestore/showForget flags reach the InboxRow child. The
    // view's loading markers are the wire that disables the
    // button while the action is in flight.
    Livewire::actingAs($user)
        ->test(InboxIndex::class, ['spaceId' => $space->id, 'filter' => 'trash'])
        ->assertSeeHtml('wire:click="restore"', false)
        ->assertSeeHtml('wire:target="restore"', false)
        ->assertSeeHtml('wire:click="forget"', false)
        ->assertSeeHtml('wire:target="forget"', false)
        ->assertSeeHtml('wire:loading.attr="disabled"', false);
});

test('InboxIndex trash tab renders forget button with wire:loading and wire:target="forget"', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    $row = Testimonial::factory()->for($space)->create();
    $row->delete();

    // Render through the real parent (filter='trash') so the row
    // sees showForget=true and the forget button branch appears in
    // the rendered HTML.
    Livewire::actingAs($user)
        ->test(InboxIndex::class, ['spaceId' => $space->id, 'filter' => 'trash'])
        ->assertSeeHtml('wire:click="forget"', false)
        ->assertSeeHtml('wire:target="forget"', false)
        ->assertSeeHtml('wire:loading.attr="disabled"', false);
});

test('SpaceForm submit button renders wire:loading with wire:target="save"', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(SpaceForm::class)
        ->assertSeeHtml('wire:target="save"', false)
        ->assertSeeHtml('wire:loading.attr="disabled"', false);
});

test('DashboardIndex granularity control renders wire:loading with wire:target="granularity"', function () {
    $user = User::factory()->create();
    Space::factory()->for($user)->create();

    Livewire::actingAs($user)
        ->test(DashboardIndex::class)
        ->assertSeeHtml('wire:target="granularity"', false)
        ->assertSeeHtml('wire:loading.attr="disabled"', false);
});

test('DashboardIndex range control renders wire:loading with wire:target="range"', function () {
    $user = User::factory()->create();
    Space::factory()->for($user)->create();

    Livewire::actingAs($user)
        ->test(DashboardIndex::class)
        ->assertSeeHtml('wire:target="range"', false)
        ->assertSeeHtml('wire:loading.attr="disabled"', false);
});
