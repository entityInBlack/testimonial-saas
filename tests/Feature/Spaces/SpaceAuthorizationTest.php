<?php

use App\Livewire\Spaces\SpaceDeleted;
use App\Livewire\Spaces\SpaceForm;
use App\Livewire\Spaces\SpaceIndex;
use App\Models\Space;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to login from /spaces index', function () {
    $this->get(route('spaces.index'))
        ->assertRedirect(route('login'));
});

test('guests are redirected to login from /spaces/new', function () {
    $this->get(route('spaces.new'))
        ->assertRedirect(route('login'));
});

test('guests are redirected to login from /spaces/deleted', function () {
    $this->get(route('spaces.deleted'))
        ->assertRedirect(route('login'));
});

test('guests are redirected to login from /spaces/{id}/edit', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();

    $this->get(route('spaces.edit', ['space' => $space->id]))
        ->assertRedirect(route('login'));
});

test('guests are redirected to login from /spaces/{id}/created', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();

    $this->get(route('spaces.created', ['space' => $space->id]))
        ->assertRedirect(route('login'));
});

test('user B sees 404 (not 403) on user A space edit page (no info leak)', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $space = Space::factory()->for($alice)->create();

    $this->actingAs($bob)
        ->get(route('spaces.edit', ['space' => $space->id]))
        ->assertStatus(404);
});

test('user B sees 404 on user A space success page', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $space = Space::factory()->for($alice)->create();

    $this->actingAs($bob)
        ->get(route('spaces.created', ['space' => $space->id]))
        ->assertStatus(404);
});

test('user B cannot update user A space (Livewire save returns 404)', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $space = Space::factory()->for($alice)->create(['title' => 'Original']);

    // Bob hits the edit URL for Alice's space — must 404, not update.
    $this->actingAs($bob)
        ->get(route('spaces.edit', ['space' => $space->id]))
        ->assertStatus(404);

    expect($space->fresh()->title)->toBe('Original');
});

test('user B cannot soft-delete user A space from the index (404)', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $space = Space::factory()->for($alice)->create();

    Livewire::actingAs($bob)
        ->test(SpaceIndex::class)
        ->call('delete', $space->id)
        ->assertStatus(404);

    expect($space->fresh()->deleted_at)->toBeNull();
});

test('user B cannot restore user A soft-deleted space (404)', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $space = Space::factory()->for($alice)->create();
    $space->delete();

    Livewire::actingAs($bob)
        ->test(SpaceDeleted::class)
        ->call('restore', $space->id)
        ->assertStatus(404);

    expect(Space::withTrashed()->find($space->id)->deleted_at)->not->toBeNull();
});

test('user B does not see user A spaces in the live index', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    Space::factory()->for($alice)->count(2)->create();
    Space::factory()->for($bob)->count(3)->create();

    Livewire::actingAs($bob)
        ->test(SpaceIndex::class)
        ->assertSet('liveSpaceCount', 3);
});

test('user B does not see user A spaces in the deleted tab', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $s = Space::factory()->for($alice)->create();
    $s->delete();
    Space::factory()->for($bob)->count(2)->create();

    Livewire::actingAs($bob)
        ->test(SpaceDeleted::class)
        ->assertSet('spaces', function ($spaces) {
            return $spaces->count() === 0;
        });
});

test('user A cannot see user A soft-deleted spaces on the live index', function () {
    $user = User::factory()->create();
    $live = Space::factory()->for($user)->create();
    $soft = Space::factory()->for($user)->create();
    $soft->delete();

    Livewire::actingAs($user)
        ->test(SpaceIndex::class)
        ->assertSet('liveSpaceCount', 1)
        ->assertSet('spaces', function ($spaces) use ($live) {
            return $spaces->count() === 1 && $spaces->first()->id === $live->id;
        });
});
