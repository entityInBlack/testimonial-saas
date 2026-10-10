<?php

use App\Livewire\Dashboard\DashboardIndex;
use App\Models\DeletionRequest;
use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;
use Livewire\Livewire;

/**
 * Step 5 — Open deletion-requests list.
 *
 * Spec: list of `deletion_requests` rows with `status='open'`
 * whose `space_id` matches one of the owner's LIVE Spaces.
 * Requests pointing at a soft-deleted Space are NOT shown or
 * counted. Requests with `testimonial_id = NULL` (Hard Rule 14 /
 * Sara's seeded one) must render fine.
 */

test('open deletion request for a live Space is shown in the list and counted', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    $row = Testimonial::factory()->for($space)->create();
    DeletionRequest::create([
        'email' => $row->email,
        'space_slug' => $space->slug,
        'space_id' => $space->id,
        'testimonial_id' => $row->id,
        'status' => 'open',
    ]);

    $c = Livewire::actingAs($user)->test(DashboardIndex::class)->instance();

    expect($c->openDeletionRequestsCount)->toBe(1)
        ->and($c->openDeletionRequests)->toHaveCount(1)
        ->and($c->openDeletionRequests->first()->testimonial_id)->toBe($row->id);

    Livewire::actingAs($user)
        ->test(DashboardIndex::class)
        ->assertSeeHtml('data-testid="deletion-request-row"');
});

test('open deletion request for a soft-deleted Space is hidden and not counted', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    $row = Testimonial::factory()->for($space)->create();
    DeletionRequest::create([
        'email' => $row->email,
        'space_slug' => $space->slug,
        'space_id' => $space->id,
        'testimonial_id' => $row->id,
        'status' => 'open',
    ]);

    // Soft-delete the Space.
    $space->delete();

    $c = Livewire::actingAs($user)->test(DashboardIndex::class)->instance();

    expect($c->openDeletionRequestsCount)->toBe(0)
        ->and($c->openDeletionRequests)->toHaveCount(0);

    Livewire::actingAs($user)
        ->test(DashboardIndex::class)
        ->assertDontSeeHtml('data-testid="deletion-request-row"');
});

test('open deletion request with null testimonial_id renders fine (Hard Rule 14 / Sara case)', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    DeletionRequest::create([
        'email' => 'sara@example.test',
        'space_slug' => $space->slug,
        'space_id' => $space->id,
        'testimonial_id' => null,
        'status' => 'open',
    ]);

    $c = Livewire::actingAs($user)->test(DashboardIndex::class)->instance();

    expect($c->openDeletionRequestsCount)->toBe(1)
        ->and($c->openDeletionRequests)->toHaveCount(1)
        ->and($c->openDeletionRequests->first()->testimonial_id)->toBeNull()
        ->and($c->openDeletionRequests->first()->email)->toBe('sara@example.test');

    Livewire::actingAs($user)
        ->test(DashboardIndex::class)
        ->assertSee('sara@example.test');
});

test('acted deletion requests are excluded from the list and count', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    $row = Testimonial::factory()->for($space)->create();
    DeletionRequest::create([
        'email' => $row->email,
        'space_slug' => $space->slug,
        'space_id' => $space->id,
        'testimonial_id' => $row->id,
        'status' => 'acted',
        'acted_at' => now(),
    ]);

    $c = Livewire::actingAs($user)->test(DashboardIndex::class)->instance();

    expect($c->openDeletionRequestsCount)->toBe(0)
        ->and($c->openDeletionRequests)->toHaveCount(0);
});

test('deletion requests belonging to other owners are excluded', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $aSpace = Space::factory()->for($alice)->create();
    $bSpace = Space::factory()->for($bob)->create();

    DeletionRequest::create([
        'email' => 'a@example.test',
        'space_slug' => $aSpace->slug,
        'space_id' => $aSpace->id,
        'testimonial_id' => null,
        'status' => 'open',
    ]);
    DeletionRequest::create([
        'email' => 'b@example.test',
        'space_slug' => $bSpace->slug,
        'space_id' => $bSpace->id,
        'testimonial_id' => null,
        'status' => 'open',
    ]);

    $aliceC = Livewire::actingAs($alice)->test(DashboardIndex::class)->instance();
    $bobC = Livewire::actingAs($bob)->test(DashboardIndex::class)->instance();

    expect($aliceC->openDeletionRequestsCount)->toBe(1)
        ->and($aliceC->openDeletionRequests->pluck('email')->all())->toBe(['a@example.test'])
        ->and($bobC->openDeletionRequestsCount)->toBe(1)
        ->and($bobC->openDeletionRequests->pluck('email')->all())->toBe(['b@example.test']);
});
