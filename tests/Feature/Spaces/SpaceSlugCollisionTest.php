<?php

use App\Livewire\Spaces\SpaceForm;
use App\Models\Space;
use App\Models\User;
use App\Support\SlugService;
use Livewire\Livewire;

test('probe marks a slug as taken when a live Space already has it, and returns a -2 suggestion', function () {
    $user = User::factory()->create();
    Space::factory()->for($user)->create(['slug' => 'acme-wall']);

    $result = SlugService::probe('acme-wall');

    expect($result['available'])->toBeFalse();
    expect($result['suggested'])->toBe('acme-wall-2');
});

test('probe marks a slug as taken when a soft-deleted Space already has it (slug is claimed forever)', function () {
    $user = User::factory()->create();
    Space::factory()->for($user)->create(['slug' => 'old-wall'])->delete();

    $result = SlugService::probe('old-wall');

    expect($result['available'])->toBeFalse();
    expect($result['suggested'])->toBe('old-wall-2');
});

test('probe marks a reserved word as taken, no 500, and returns a suggestion', function () {
    foreach (Space::RESERVED_SLUGS as $reserved) {
        $result = SlugService::probe($reserved);
        expect($result['available'])->toBeFalse("reserved '{$reserved}' should not be available");
        expect($result['suggested'])->toBe($reserved.'-2');
    }
});

test('save() surfaces a slug error and suggestion when the slug collides with a live Space (no write happens)', function () {
    $user = User::factory()->create();
    Space::factory()->for($user)->create(['slug' => 'taken']);

    Livewire::actingAs($user)
        ->test(SpaceForm::class)
        ->set('name', 'New')
        ->set('slug', 'taken')
        ->set('title', 'New')
        ->set('ask', 'ask')
        ->set('theme', 'minimal_light')
        ->call('save')
        ->assertHasErrors(['slug'])
        ->assertSet('slugSuggestion', 'taken-2')
        ->assertSet('slugAvailable', false);

    // No second row was created
    expect(Space::withTrashed()->where('slug', 'taken')->count())->toBe(1);
    expect(Space::where('slug', 'taken-2')->count())->toBe(0);
});

test('save() surfaces a slug error and suggestion when the slug collides with a soft-deleted Space', function () {
    $user = User::factory()->create();
    Space::factory()->for($user)->create(['slug' => 'softgone'])->delete();

    Livewire::actingAs($user)
        ->test(SpaceForm::class)
        ->set('name', 'New')
        ->set('slug', 'softgone')
        ->set('title', 'New')
        ->set('ask', 'ask')
        ->set('theme', 'minimal_light')
        ->call('save')
        ->assertHasErrors(['slug'])
        ->assertSet('slugSuggestion', 'softgone-2');
});

test('save() rejects a reserved word as slug, no 500, no write', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(SpaceForm::class)
        ->set('name', 'New')
        ->set('slug', 'admin')
        ->set('title', 'New')
        ->set('ask', 'ask')
        ->set('theme', 'minimal_light')
        ->call('save')
        ->assertHasErrors(['slug'])
        ->assertSet('slugSuggestion', 'admin-2');

    expect(Space::where('slug', 'admin')->count())->toBe(0);
    expect(Space::where('slug', 'admin-2')->count())->toBe(0);
});

test('updatedTitle auto-suggests a kebab-case slug', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(SpaceForm::class)
        ->set('title', 'Acme Wall of Love!')
        ->assertSet('slugHint', 'acme-wall-of-love')
        ->assertSet('slug', 'acme-wall-of-love');
});

test('updatedTitle does not overwrite a slug the user typed themselves', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(SpaceForm::class)
        ->set('slug', 'my-custom-slug')
        ->set('title', 'First title')
        // Now user edits the title — the slug they typed should NOT be overwritten
        // (it differs from the hint, so the guard kicks in)
        ->set('title', 'Second title')
        ->assertSet('slug', 'my-custom-slug');
});

test('applySuggestion fills the form with the proposed slug and reprobes', function () {
    $user = User::factory()->create();
    Space::factory()->for($user)->create(['slug' => 'taken']);

    Livewire::actingAs($user)
        ->test(SpaceForm::class)
        ->set('slug', 'taken')
        ->call('probeSlug')
        ->assertSet('slugAvailable', false)
        ->assertSet('slugSuggestion', 'taken-2')
        ->call('applySuggestion')
        ->assertSet('slug', 'taken-2')
        ->assertSet('slugAvailable', true);
});
