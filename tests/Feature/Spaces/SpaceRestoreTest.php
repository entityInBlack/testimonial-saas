<?php

use App\Livewire\Spaces\SpaceDeleted;
use App\Livewire\Spaces\SpaceForm;
use App\Livewire\Spaces\SpaceIndex;
use App\Models\Space;
use App\Models\User;
use Livewire\Livewire;

test('owner can restore a soft-deleted Space within the retention window', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create(['slug' => 'restore-me']);
    Livewire::actingAs($user)
        ->test(SpaceIndex::class)
        ->call('delete', $space->id);

    expect(Space::find($space->id))->toBeNull();

    Livewire::actingAs($user)
        ->test(SpaceDeleted::class)
        ->call('restore', $space->id);

    $restored = Space::find($space->id);
    expect($restored)->not->toBeNull();
    expect($restored->deleted_at)->toBeNull();
});

test('restored Space shows up on the LIVE index again and counts toward the cap', function () {
    $user = User::factory()->create();
    $a = Space::factory()->for($user)->create();
    $b = Space::factory()->for($user)->create();
    $c = Space::factory()->for($user)->create();

    Livewire::actingAs($user)
        ->test(SpaceIndex::class)
        ->call('delete', $a->id)
        ->assertSet('liveSpaceCount', 2);

    Livewire::actingAs($user)
        ->test(SpaceDeleted::class)
        ->call('restore', $a->id);

    Livewire::actingAs($user)
        ->test(SpaceIndex::class)
        ->assertSet('liveSpaceCount', 3)
        ->assertSeeHtml('data-testid="space-row"');
});

test('restore is blocked (sets a restore error) when owner is already at cap', function () {
    $user = User::factory()->create();
    Space::factory()->for($user)->count(3)->create();
    $fourth = Space::factory()->for($user)->create();
    Livewire::actingAs($user)
        ->test(SpaceIndex::class)
        ->call('delete', $fourth->id);

    // Now we have 3 LIVE + 1 soft-deleted. Restoring the soft-deleted
    // would push LIVE to 4 — must be blocked.
    Livewire::actingAs($user)
        ->test(SpaceDeleted::class)
        ->call('restore', $fourth->id)
        ->assertHasErrors(['restore'])
        ->assertSee("Delete a Space first");

    expect(Space::find($fourth->id))->toBeNull();
    expect(Space::withTrashed()->find($fourth->id)->deleted_at)->not->toBeNull();
});

test('tombstoned (deleted > retention_days) Space is NOT listed in the deleted tab', function () {
    $user = User::factory()->create();
    $old = Space::factory()->for($user)->create();
    $old->delete();
    // Force the deleted_at back past the retention window.
    // Use withTrashed() — Space::query() has the SoftDeletes global
    // scope which filters out already-trashed rows.
    Space::withTrashed()
        ->where('id', $old->id)
        ->update(['deleted_at' => now()->subDays((int) config('purge.retention_days', 30) + 5)]);

    // The component's `spaces` computed property is queried via
    // Space::recentlyDeleted(), which excludes tombstones. So the
    // tombstoned Space is NOT in the rendered list.
    $component = Livewire::actingAs($user)
        ->test(SpaceDeleted::class);

    $spaces = $component->instance()->spaces; // #[Computed] property
    expect($spaces->pluck('id')->contains($old->id))->toBeFalse();
    // The deleted-row testid should not be rendered for a tombstoned row.
    $component->assertDontSeeHtml('data-testid="deleted-row"');
});

test('tombstoned Space cannot be restored (restore is blocked)', function () {
    $user = User::factory()->create();
    $old = Space::factory()->for($user)->create();
    $old->delete();
    Space::withTrashed()
        ->where('id', $old->id)
        ->update(['deleted_at' => now()->subDays((int) config('purge.retention_days', 30) + 5)]);

    // Restore is a no-op for tombstoned — and the component
    // surfaces a 'past the restore window' error.
    Livewire::actingAs($user)
        ->test(SpaceDeleted::class)
        ->call('restore', $old->id)
        ->assertHasErrors(['restore']);

    $row = Space::withTrashed()->find($old->id);
    expect($row->deleted_at)->not->toBeNull();
});

test('restore() 404s for a Space that does not belong to the user', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $space = Space::factory()->for($alice)->create();
    $space->delete();

    Livewire::actingAs($bob)
        ->test(SpaceDeleted::class)
        ->call('restore', $space->id)
        ->assertStatus(404);
});
