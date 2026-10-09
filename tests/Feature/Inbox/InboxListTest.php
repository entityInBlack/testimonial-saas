<?php

use App\Livewire\Inbox\InboxIndex;
use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;
use Livewire\Livewire;

/**
 * Step 4.1 — /inbox page is auth-gated, Space dropdown shows only
 * LIVE Spaces, default filter is 'all', default sort is the firmware
 * rule (is_favorite DESC, submitted_at DESC).
 */

test('guests are redirected to login from /inbox', function () {
    $this->get(route('inbox.index'))
        ->assertRedirect(route('login'));
});

test('inbox renders for the owner with a Space dropdown', function () {
    $user = User::factory()->create();
    Space::factory()->for($user)->create(['title' => 'My Space']);

    Livewire::actingAs($user)
        ->test(InboxIndex::class)
        ->assertSet('filter', 'all')
        ->assertSet('spaceId', null)
        ->assertSeeHtml('data-testid="space-selector"');
});

test('inbox dropdown shows only LIVE Spaces owned by the user', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $live1 = Space::factory()->for($alice)->create(['title' => 'Alice Live']);
    $live2 = Space::factory()->for($alice)->create(['title' => 'Alice Live 2']);
    $dead = Space::factory()->for($alice)->create(['title' => 'Alice Deleted']);
    $dead->delete();

    $other = Space::factory()->for($bob)->create(['title' => 'Bob Live']);

    $component = Livewire::actingAs($alice)
        ->test(InboxIndex::class);

    $ids = $component->instance()->spaces->pluck('id')->all();

    expect($ids)->toContain($live1->id, $live2->id)
        ->and($ids)->not->toContain($dead->id)
        ->and($ids)->not->toContain($other->id);
});

test('inbox shows only the selected Space\'s testimonials', function () {
    $user = User::factory()->create();
    $a = Space::factory()->for($user)->create();
    $b = Space::factory()->for($user)->create();

    $ta = Testimonial::factory()->for($a)->create(['name' => 'Alice-row']);
    $tb = Testimonial::factory()->for($b)->create(['name' => 'Bob-row']);

    Livewire::actingAs($user)
        ->test(InboxIndex::class, ['spaceId' => $a->id])
        ->assertSee('Alice-row')
        ->assertDontSee('Bob-row');
});

test('each filter returns the correct subset', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();

    // Build four rows with mutually exclusive flag combinations so
    // each filter shows exactly one row.
    $onlyFav = Testimonial::factory()->for($space)->create([
        'name' => 'FavOnly-row', 'is_favorite' => true, 'is_wall_of_love' => false, 'is_hidden' => false,
    ]);
    $onlyWol = Testimonial::factory()->for($space)->create([
        'name' => 'WolOnly-row', 'is_favorite' => false, 'is_wall_of_love' => true, 'is_hidden' => false,
    ]);
    $onlyHidden = Testimonial::factory()->for($space)->create([
        'name' => 'HiddenOnly-row', 'is_favorite' => false, 'is_wall_of_love' => false, 'is_hidden' => true,
    ]);
    $plain = Testimonial::factory()->for($space)->pending()->create([
        'name' => 'PlainOnly-row', 'is_favorite' => false, 'is_wall_of_love' => false, 'is_hidden' => false,
    ]);

    // All
    Livewire::actingAs($user)
        ->test(InboxIndex::class, ['spaceId' => $space->id])
        ->assertSee('FavOnly-row')
        ->assertSee('WolOnly-row')
        ->assertSee('HiddenOnly-row')
        ->assertSee('PlainOnly-row');

    // Favorites
    Livewire::actingAs($user)
        ->test(InboxIndex::class, ['spaceId' => $space->id, 'filter' => 'favorites'])
        ->assertSee('FavOnly-row')
        ->assertDontSee('WolOnly-row')
        ->assertDontSee('HiddenOnly-row')
        ->assertDontSee('PlainOnly-row');

    // Wall
    Livewire::actingAs($user)
        ->test(InboxIndex::class, ['spaceId' => $space->id, 'filter' => 'wall'])
        ->assertSee('WolOnly-row')
        ->assertDontSee('FavOnly-row')
        ->assertDontSee('HiddenOnly-row')
        ->assertDontSee('PlainOnly-row');

    // Hidden
    Livewire::actingAs($user)
        ->test(InboxIndex::class, ['spaceId' => $space->id, 'filter' => 'hidden'])
        ->assertSee('HiddenOnly-row')
        ->assertDontSee('FavOnly-row')
        ->assertDontSee('WolOnly-row')
        ->assertDontSee('PlainOnly-row');
});

test('inbox sorts by is_favorite DESC, submitted_at DESC (firmware rule)', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();

    $when = now()->subHour();

    // Two rows, same submitted_at, different is_favorite.
    $a = Testimonial::factory()->for($space)->create([
        'name' => 'Not-Fav',
        'is_favorite' => false,
        'submitted_at' => $when,
    ]);
    $b = Testimonial::factory()->for($space)->create([
        'name' => 'Fav',
        'is_favorite' => true,
        'submitted_at' => $when,
    ]);

    $component = Livewire::actingAs($user)
        ->test(InboxIndex::class, ['spaceId' => $space->id]);

    $ids = $component->instance()->testimonials->pluck('id')->all();

    expect($ids[0])->toBe($b->id, 'favorite row must come first when submitted_at is equal')
        ->and($ids[1])->toBe($a->id);

    // Raw DB check — also test the SQL directly so the order is
    // nailed to the column names (the spec is "is_favorite DESC,
    // submitted_at DESC" — not "is_wall_of_love DESC").
    $row = DB::table('testimonials')
        ->whereIn('id', [$a->id, $b->id])
        ->orderByDesc('is_favorite')
        ->orderByDesc('submitted_at')
        ->orderByDesc('id')
        ->get();

    expect($row->first()->id)->toBe($b->id);
});

test('hidden items remain in the All filter but are visually flagged', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    $hidden = Testimonial::factory()->for($space)->hidden()->create(['name' => 'Flagged']);

    Livewire::actingAs($user)
        ->test(InboxIndex::class, ['spaceId' => $space->id])
        ->assertSee('Flagged')
        ->assertSeeHtml('data-testid="row-hidden-chip"');
});

test('counts are correct per filter', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();

    Testimonial::factory()->for($space)->count(3)->create();
    Testimonial::factory()->for($space)->favorite()->count(2)->create();
    Testimonial::factory()->for($space)->hidden()->count(1)->create();

    $component = Livewire::actingAs($user)
        ->test(InboxIndex::class, ['spaceId' => $space->id]);

    $counts = $component->instance()->counts;

    expect($counts['all'])->toBe(6)
        ->and($counts['favorites'])->toBe(2)
        ->and($counts['wall'])->toBe(6) // 3 default + 2 fav + 1 hidden (factory sets is_wall_of_love=true by default)
        ->and($counts['hidden'])->toBe(1)
        ->and($counts['trash'])->toBe(0);
});
