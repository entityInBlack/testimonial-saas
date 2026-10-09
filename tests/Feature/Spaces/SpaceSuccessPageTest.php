<?php

use App\Livewire\Spaces\SpaceCreated;
use App\Models\Space;
use App\Models\User;
use Livewire\Livewire;

test('success page renders the public /s/{slug} URL', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create(['slug' => 'public-wall']);

    $this->actingAs($user)
        ->get(route('spaces.created', ['space' => $space->id]))
        ->assertOk()
        ->assertSee('public-wall')
        ->assertSeeHtml('data-testid="public-url-box"')
        ->assertSeeHtml('data-testid="copy-link"')
        ->assertSeeHtml('data-testid="go-to-dashboard"');
});

test('success page shows the Spaces title and the public URL contains the slug', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create([
        'title' => 'My Brand New Wall',
        'slug' => 'my-brand-new-wall',
    ]);

    $this->actingAs($user)
        ->get(route('spaces.created', ['space' => $space->id]))
        ->assertOk()
        ->assertSee('My Brand New Wall')
        ->assertSee('my-brand-new-wall')
        ->assertSee(url('/s/my-brand-new-wall'));
});

test('success page is unreachable for guests (auth middleware)', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();

    $this->get(route('spaces.created', ['space' => $space->id]))
        ->assertRedirect(route('login'));
});

test('success page is unreachable for non-owners (404, not 403)', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $space = Space::factory()->for($alice)->create();

    $this->actingAs($bob)
        ->get(route('spaces.created', ['space' => $space->id]))
        ->assertStatus(404);
});
