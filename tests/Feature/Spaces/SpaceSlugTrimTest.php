<?php

use App\Livewire\Spaces\SpaceForm;
use App\Models\Space;
use App\Models\User;
use App\Support\SlugService;
use Livewire\Livewire;

test('normalize() trims slugs to 60 characters', function () {
    $raw = str_repeat('a', 80);

    $normalized = SlugService::normalize($raw);

    expect(strlen($normalized))->toBeLessThanOrEqual(60);
    expect($normalized)->toBe(str_repeat('a', 60));
});

test('nextSuggestion trims the base so the -N suffix fits inside 60 chars', function () {
    // A 58-char base with -2 (3 chars) would be 61 — must trim base to 57.
    $base = str_repeat('a', 58);

    $suggestion = SlugService::nextSuggestion($base);

    expect(strlen($suggestion))->toBeLessThanOrEqual(60);
    expect($suggestion)->toEndWith('-2');
    expect(strlen($suggestion))->toBe(60);
});

test('a slug exactly at 60 characters is accepted on save', function () {
    $user = User::factory()->create();
    $slug = str_repeat('x', 60);

    Livewire::actingAs($user)
        ->test(SpaceForm::class)
        ->set('name', 'Long')
        ->set('slug', $slug)
        ->set('title', 'Long Slug')
        ->set('ask', 'ask')
        ->set('theme', 'minimal_light')
        ->call('save')
        ->assertHasNoErrors();

    $space = Space::where('user_id', $user->id)->first();

    expect($space)->not->toBeNull();
    expect(strlen($space->slug))->toBe(60);
});

test('a slug over 60 characters is rejected on save (validation rule)', function () {
    $user = User::factory()->create();
    $slug = str_repeat('x', 61);

    Livewire::actingAs($user)
        ->test(SpaceForm::class)
        ->set('name', 'Long')
        ->set('slug', $slug)
        ->set('title', 'Too Long')
        ->set('ask', 'ask')
        ->set('theme', 'minimal_light')
        ->call('save')
        ->assertHasErrors(['slug']);

    expect(Space::count())->toBe(0);
});