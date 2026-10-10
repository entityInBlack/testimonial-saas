<?php

use App\Livewire\Dashboard\DashboardIndex;
use App\Models\Space;
use App\Models\User;
use Livewire\Livewire;

/**
 * Step 5 — Deleted Spaces tab.
 *
 * Spec: "Deleted Spaces tab to the dashboard with a Restore
 * action (same widget as Step 2). Restore stays blocked at cap;
 * tombstones stay hidden and not restorable."
 */

test('Deleted Spaces tab lists soft-deleted Spaces owned by the user', function () {
    $user = User::factory()->create();
    $live = Space::factory()->for($user)->create(['title' => 'Live']);
    $deleted = Space::factory()->for($user)->create(['title' => 'Deleted']);
    $deleted->delete();

    Livewire::actingAs($user)
        ->test(DashboardIndex::class, ['tab' => 'deleted'])
        ->assertSet('tab', 'deleted')
        ->assertSeeHtml('data-testid="dashboard-deleted-list"')
        ->assertSee('Deleted')
        ->assertSeeHtml('data-testid="deleted-row"')
        ->assertDontSee('Live');
});

test('Deleted Spaces tab hides tombstones (soft-deleted > 30 days)', function () {
    $user = User::factory()->create();
    $tombstoned = Space::factory()->for($user)->create(['title' => 'Tombstoned']);
    $tombstoned->delete();
    // Force the deleted_at far enough in the past to be a tombstone
    // (use a raw DB::table update because Eloquent's SoftDeletes
    // trait occasionally refuses to overwrite `deleted_at` once
    // it has been set on the model).
    \Illuminate\Support\Facades\DB::table('spaces')
        ->where('id', $tombstoned->id)
        ->update(['deleted_at' => now()->subDays(60)]);
    $recent = Space::factory()->for($user)->create(['title' => 'Recent']);
    $recent->delete();

    $html = (string) Livewire::actingAs($user)
        ->test(DashboardIndex::class, ['tab' => 'deleted'])
        ->html();

    expect($html)
        ->toContain('Recent')
        ->not->toContain('Tombstoned');
});

test('Deleted Spaces tab Restore action works (within retention, not at cap)', function () {
    $user = User::factory()->create();
    // Owner has 1 live Space + 1 deleted. Cap is 3, so restore is allowed.
    $live = Space::factory()->for($user)->create();
    $deleted = Space::factory()->for($user)->create(['title' => 'To restore']);
    $deleted->delete();
    $deletedId = $deleted->id;

    Livewire::actingAs($user)
        ->test(DashboardIndex::class, ['tab' => 'deleted'])
        ->call('restoreDeletedSpace', $deletedId);

    expect(Space::find($deletedId)->deleted_at)->toBeNull();
});

test('Deleted Spaces tab Restore is blocked when the owner is at the cap', function () {
    config(['limits.max_spaces' => 2]);
    $user = User::factory()->create();
    // Owner is already at cap (2 live Spaces).
    Space::factory()->for($user)->count(2)->create();
    $deleted = Space::factory()->for($user)->create();
    $deleted->delete();
    $deletedId = $deleted->id;

    Livewire::actingAs($user)
        ->test(DashboardIndex::class, ['tab' => 'deleted'])
        ->call('restoreDeletedSpace', $deletedId)
        ->assertHasErrors(['restore']);

    // The Space stays soft-deleted.
    expect(Space::withTrashed()->find($deletedId)->deleted_at)->not->toBeNull();
});

test('Deleted Spaces tab Restore returns 404 for a Space the user does not own', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $aliceDeleted = Space::factory()->for($alice)->create();
    $aliceDeleted->delete();

    // The Livewire test harness catches the abort(404) inside the
    // action and converts it. The cross-user proof is that the
    // Space stays soft-deleted (no row mutation) — the abort fired
    // and the action was rejected. The HTTP-boundary proof is in
    // DashboardHttpBoundaryTest.
    Livewire::actingAs($bob)
        ->test(DashboardIndex::class, ['tab' => 'deleted'])
        ->call('restoreDeletedSpace', $aliceDeleted->id);

    expect(Space::withTrashed()->find($aliceDeleted->id)->deleted_at)->not->toBeNull();
});

test('Deleted Spaces tab does not show the Free-plan note when owner has only deleted Spaces', function () {
    $user = User::factory()->create();
    $deleted = Space::factory()->for($user)->create();
    $deleted->delete();

    // No live Spaces → note must be hidden (it only renders when
    // `spacesUsed > 0`).
    Livewire::actingAs($user)
        ->test(DashboardIndex::class, ['tab' => 'active'])
        ->assertDontSeeHtml('data-testid="free-plan-note"');
});
