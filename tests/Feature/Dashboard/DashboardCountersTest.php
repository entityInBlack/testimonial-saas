<?php

use App\Livewire\Dashboard\DashboardIndex;
use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;
use Livewire\Livewire;

/**
 * Step 5 — Counter cards on /dashboard.
 *
 * Spec (counters, all against LIVE Spaces only):
 *   - Total testimonials
 *   - Total customers  (label exactly "Total customers"; COUNT(DISTINCT email))
 *   - Spaces used      (X of config('limits.max_spaces'))
 *   - Average rating   (non-null ratings only; clear empty value)
 *   - Open deletion requests count
 *
 * Plus the synthetic Total-customers fixture (2 Spaces, 3 + 2
 * testimonials, 1 shared email → 4 customers) which pins the
 * off-by-one.
 */

test('Total customers is 0 for a fresh owner with no Spaces', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(DashboardIndex::class)
        ->assertSet('granularity', 'day')
        ->assertSet('range', '30d')
        ->assertSet('spaceId', null)
        ->assertSet('tab', 'active');

    $c = Livewire::actingAs($user)->test(DashboardIndex::class)->instance();

    expect($c->totalTestimonials)->toBe(0)
        ->and($c->totalCustomers)->toBe(0)
        ->and($c->spacesUsed)->toBe(0)
        ->and($c->maxSpaces)->toBe(3)
        ->and($c->averageRating)->toBeNull()
        ->and($c->openDeletionRequestsCount)->toBe(0);
});

test('Total customers synthetic fixture: 2 Spaces, 3+2 rows, 1 shared email → 4', function () {
    $user = User::factory()->create();
    $a = Space::factory()->for($user)->create();
    $b = Space::factory()->for($user)->create();

    // Space A — 3 testimonials, emails a@x, shared@x, c@x
    Testimonial::factory()->for($a)->create(['email' => 'a@example.test']);
    Testimonial::factory()->for($a)->create(['email' => 'shared@example.test']);
    Testimonial::factory()->for($a)->create(['email' => 'c@example.test']);

    // Space B — 2 testimonials, emails d@x, shared@x (the shared one)
    Testimonial::factory()->for($b)->create(['email' => 'd@example.test']);
    Testimonial::factory()->for($b)->create(['email' => 'shared@example.test']);

    $c = Livewire::actingAs($user)->test(DashboardIndex::class)->instance();

    // 5 rows total, 4 distinct emails.
    expect($c->totalTestimonials)->toBe(5)
        ->and($c->totalCustomers)->toBe(4);
});

test('Total customers ignores other owners data', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $aAlice = Space::factory()->for($alice)->create();
    $aBob = Space::factory()->for($bob)->create();

    Testimonial::factory()->for($aAlice)->count(3)->create();
    Testimonial::factory()->for($aBob)->count(7)->create();

    $aliceC = Livewire::actingAs($alice)->test(DashboardIndex::class)->instance();
    $bobC = Livewire::actingAs($bob)->test(DashboardIndex::class)->instance();

    expect($aliceC->totalCustomers)->toBe(3)
        ->and($aliceC->totalTestimonials)->toBe(3)
        ->and($bobC->totalCustomers)->toBe(7)
        ->and($bobC->totalTestimonials)->toBe(7);
});

test('Counters ignore soft-deleted Space data', function () {
    $user = User::factory()->create();
    $live = Space::factory()->for($user)->create();
    $dead = Space::factory()->for($user)->create();
    $dead->delete();

    Testimonial::factory()->for($live)->count(4)->create();
    Testimonial::factory()->for($dead)->count(9)->create();

    $c = Livewire::actingAs($user)->test(DashboardIndex::class)->instance();

    // Only the live Space's 4 rows are counted.
    expect($c->totalTestimonials)->toBe(4)
        ->and($c->totalCustomers)->toBe(4)
        ->and($c->spacesUsed)->toBe(1);
});

test('Counters ignore soft-deleted testimonials inside a live Space', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();

    $live1 = Testimonial::factory()->for($space)->create(['email' => 'live1@example.test']);
    $live2 = Testimonial::factory()->for($space)->create(['email' => 'live2@example.test']);
    $dead = Testimonial::factory()->for($space)->create(['email' => 'dead@example.test']);
    $dead->delete();

    $c = Livewire::actingAs($user)->test(DashboardIndex::class)->instance();

    expect($c->totalTestimonials)->toBe(2)
        ->and($c->totalCustomers)->toBe(2);
});

test('Average rating ignores NULL ratings and shows empty when none', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();

    // Mix: rating=4, rating=5, rating=NULL, rating=NULL.
    Testimonial::factory()->for($space)->create(['rating' => 4]);
    Testimonial::factory()->for($space)->create(['rating' => 5]);
    Testimonial::factory()->for($space)->create(['rating' => null]);
    Testimonial::factory()->for($space)->create(['rating' => null]);

    $c = Livewire::actingAs($user)->test(DashboardIndex::class)->instance();

    // (4 + 5) / 2 = 4.5 (nulls are ignored).
    expect($c->averageRating)->toBe(4.5);
});

test('Average rating returns null when no testimonials have a rating', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();

    Testimonial::factory()->for($space)->create(['rating' => null]);
    Testimonial::factory()->for($space)->create(['rating' => null]);

    $c = Livewire::actingAs($user)->test(DashboardIndex::class)->instance();

    expect($c->averageRating)->toBeNull();
});

test('Spaces used renders "X of N" from config and follows config changes', function () {
    $user = User::factory()->create();
    Space::factory()->for($user)->count(2)->create();

    // Default config('limits.max_spaces') is 3.
    $c = Livewire::actingAs($user)->test(DashboardIndex::class)->instance();
    expect($c->spacesUsed)->toBe(2)
        ->and($c->maxSpaces)->toBe(3);

    // Mutate the config (the component reads it every render).
    config(['limits.max_spaces' => 5]);
    $c2 = Livewire::actingAs($user)->test(DashboardIndex::class)->instance();
    expect($c2->maxSpaces)->toBe(5);

    // The counter view text also reflects the change.
    config(['limits.max_spaces' => 7]);
    Livewire::actingAs($user)
        ->test(DashboardIndex::class)
        ->assertSee('2 of 7', false);
});
