<?php

use App\Livewire\Spaces\SpaceForm;
use App\Models\Space;
use App\Models\User;
use Livewire\Livewire;

test('owner can open the edit form for one of their spaces', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create(['title' => 'Original']);

    $this->actingAs($user)
        ->get(route('spaces.edit', ['space' => $space->id]))
        ->assertOk()
        ->assertSee('Original');
});

test('owner can update an existing Space and is redirected to the index', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create([
        'title' => 'Old title',
        'slug' => 'old-slug',
    ]);

    Livewire::actingAs($user)
        ->test(\App\Livewire\Spaces\SpaceForm::class, ['space' => $space->id])
        ->assertSet('spaceId', $space->id)
        // Break the title -> slug auto-link by typing a custom slug
        // BEFORE setting the new title. The auto-suggest only fires
        // when the current slug equals the previous title-derived
        // hint. Setting the slug first makes the guard skip the
        // auto-overwrite on the subsequent title change.
        ->set('slug', 'custom-keep-me')
        ->set('title', 'New title')
        ->set('ask', 'New ask')
        ->set('theme', 'minimal_dark')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('spaces.index'));

    $space->refresh();
    expect($space->title)->toBe('New title');
    expect($space->ask)->toBe('New ask');
    expect($space->theme)->toBe('minimal_dark');
    // Slug stays put when the user explicitly keeps it.
    expect($space->slug)->toBe('custom-keep-me');
});

test('updating does not trip the cap (an update is not a create)', function () {
    $user = User::factory()->create();
    Space::factory()->for($user)->count(3)->create();
    $existing = Space::factory()->for($user)->create();

    // The owner is at cap (3) but they're UPDATING an existing space.
    // Updates are not cap-blocked.
    Livewire::actingAs($user)
        ->test(\App\Livewire\Spaces\SpaceForm::class, ['space' => $existing->id])
        ->set('title', 'Updated')
        ->call('save')
        ->assertHasNoErrors();

    expect($existing->fresh()->title)->toBe('Updated');
});

test('edit form is unavailable for a soft-deleted Space (404, not 403)', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    $space->delete();

    $this->actingAs($user)
        ->get(route('spaces.edit', ['space' => $space->id]))
        ->assertStatus(404);
});
