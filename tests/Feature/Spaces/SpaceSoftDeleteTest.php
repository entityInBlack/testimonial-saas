<?php

use App\Livewire\Spaces\SpaceIndex;
use App\Models\Space;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

test('soft-deleting a Space sets deleted_at and keeps the row in the table', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create(['slug' => 'keep-slug']);

    Livewire::actingAs($user)
        ->test(SpaceIndex::class)
        ->call('delete', $space->id);

    $row = DB::table('spaces')->where('id', $space->id)->first();

    expect($row)->not->toBeNull();
    expect($row->slug)->toBe('keep-slug');
    expect($row->deleted_at)->not->toBeNull();
});

test('soft-deleted Space disappears from the LIVE index', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();

    Livewire::actingAs($user)
        ->test(SpaceIndex::class)
        ->call('delete', $space->id)
        ->assertSet('liveSpaceCount', 0);
});

test('soft-deleted Slug is still claimed (cannot be re-used for a new Space)', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create(['slug' => 'claimed-forever']);

    Livewire::actingAs($user)
        ->test(SpaceIndex::class)
        ->call('delete', $space->id);

    // Now the same user tries to create a new Space with the same slug
    // via the form — must be rejected.
    Livewire::actingAs($user)
        ->test(\App\Livewire\Spaces\SpaceForm::class)
        ->set('name', 'New')
        ->set('slug', 'claimed-forever')
        ->set('title', 'New')
        ->set('ask', 'ask')
        ->set('theme', 'minimal_light')
        ->call('save')
        ->assertHasErrors(['slug']);

    expect(Space::where('slug', 'claimed-forever')->count())->toBe(0);
    expect(Space::withTrashed()->where('slug', 'claimed-forever')->count())->toBe(1);
});

test('soft-delete does NOT touch testimonials (Hard Rule 6)', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();

    DB::table('testimonials')->insert([
        'space_id' => $space->id,
        'name' => 'Priya',
        'email' => 'priya-'.uniqid().'@e.test',
        'address' => '1 Test',
        'testimonial' => 'test',
        'consent_given' => 1,
        'is_wall_of_love' => 1,
        'is_hidden' => 0,
        'submitted_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Livewire::actingAs($user)
        ->test(SpaceIndex::class)
        ->call('delete', $space->id);

    expect(DB::table('testimonials')->where('space_id', $space->id)->count())->toBe(1);
});

test('soft-deleted Spaces do not count toward the cap', function () {
    $user = User::factory()->create();
    Space::factory()->for($user)->count(3)->create();
    $extra = Space::factory()->for($user)->create();
    Livewire::actingAs($user)
        ->test(SpaceIndex::class)
        ->call('delete', $extra->id);

    // Now LIVE count is 3 (the originals); creating another should fail
    Livewire::actingAs($user)
        ->test(\App\Livewire\Spaces\SpaceForm::class)
        ->set('name', 'x')
        ->set('slug', 'after-soft')
        ->set('title', 'After')
        ->set('ask', 'ask')
        ->set('theme', 'minimal_light')
        ->call('save')
        ->assertHasErrors(['cap']);
});
