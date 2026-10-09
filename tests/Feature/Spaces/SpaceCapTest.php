<?php

use App\Livewire\Spaces\SpaceForm;
use App\Livewire\Spaces\SpaceIndex;
use App\Models\Space;
use App\Models\User;
use Livewire\Livewire;

test('owner can create up to 3 LIVE Spaces (cap=3)', function () {
    $user = User::factory()->create();
    expect((int) config('limits.max_spaces'))->toBe(3);

    for ($i = 1; $i <= 3; $i++) {
        Livewire::actingAs($user)
            ->test(SpaceForm::class)
            ->set('name', "S{$i}")
            ->set('slug', "cap-{$i}")
            ->set('title', "Title {$i}")
            ->set('ask', 'ask')
            ->set('theme', 'minimal_light')
            ->call('save')
            ->assertHasNoErrors();
    }

    expect(Space::where('user_id', $user->id)->count())->toBe(3);
});

test('a 4th create is blocked by the cap and surfaces a cap error (no row written)', function () {
    $user = User::factory()->create();
    Space::factory()->for($user)->count(3)->create();

    Livewire::actingAs($user)
        ->test(SpaceForm::class)
        ->set('name', 'S4')
        ->set('slug', 'cap-4')
        ->set('title', 'Title 4')
        ->set('ask', 'ask')
        ->set('theme', 'minimal_light')
        ->call('save')
        ->assertHasErrors(['cap']);

    expect(Space::where('user_id', $user->id)->count())->toBe(3);
    expect(Space::where('slug', 'cap-4')->count())->toBe(0);
});

test('the create form shows the cap-block notice when the owner is already at the cap', function () {
    $user = User::factory()->create();
    Space::factory()->for($user)->count(3)->create();

    Livewire::actingAs($user)
        ->test(SpaceForm::class)
        ->assertSet('isAtCap', true)
        // The cap-block banner contains the exact message from PRD §11
        // (no upgrade CTA in v1).
        ->assertSee('Delete a Space or contact support.')
        ->assertSeeHtml('data-testid="cap-block"');
});

test('the index page hides the New Space link when the owner is at the cap', function () {
    $user = User::factory()->create();
    Space::factory()->for($user)->count(3)->create();

    Livewire::actingAs($user)
        ->test(SpaceIndex::class)
        ->assertSet('isAtCap', true)
        // "new-space-link" is hidden when at cap
        ->assertDontSeeHtml('data-testid="new-space-link"');
});

test('cap counts only LIVE Spaces (soft-deleted do not count toward the cap)', function () {
    $user = User::factory()->create();
    // 3 LIVE
    Space::factory()->for($user)->count(3)->create();
    // 2 soft-deleted
    Space::factory()->for($user)->count(2)->create()->each(fn ($s) => $s->delete());

    Livewire::actingAs($user)
        ->test(SpaceForm::class)
        ->set('name', 'S6')
        ->set('slug', 'cap-soft')
        ->set('title', 'Title 6')
        ->set('ask', 'ask')
        ->set('theme', 'minimal_light')
        ->call('save')
        ->assertHasErrors(['cap']);
});

test('warning banner appears at the threshold (3 of 3, since ceil(3*0.8)=3)', function () {
    $user = User::factory()->create();
    Space::factory()->for($user)->count(3)->create();

    Livewire::actingAs($user)
        ->test(SpaceIndex::class)
        ->assertSet('showWarningBanner', true)
        ->assertSeeHtml('data-testid="cap-warning"')
        ->assertSee('using 3 of 3 Spaces');
});

test('warning banner does NOT appear at 1 of 3 (below 80% threshold)', function () {
    $user = User::factory()->create();
    Space::factory()->for($user)->count(1)->create();

    Livewire::actingAs($user)
        ->test(SpaceIndex::class)
        ->assertSet('showWarningBanner', false)
        ->assertDontSeeHtml('data-testid="cap-warning"');
});

test('per-owner cap isolation: a 2nd user is not affected by the first users cap', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    Space::factory()->for($alice)->count(3)->create();

    // Bob has zero Spaces and can still create
    Livewire::actingAs($bob)
        ->test(SpaceForm::class)
        ->set('name', 'B1')
        ->set('slug', 'bob-one')
        ->set('title', 'Bob 1')
        ->set('ask', 'ask')
        ->set('theme', 'minimal_light')
        ->call('save')
        ->assertHasNoErrors();

    expect(Space::where('user_id', $bob->id)->count())->toBe(1);
});
