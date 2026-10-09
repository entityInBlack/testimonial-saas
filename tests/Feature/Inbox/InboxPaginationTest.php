<?php

use App\Livewire\Inbox\InboxIndex;
use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;
use Livewire\Livewire;

test('inbox paginates 20 per page', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    Testimonial::factory()->for($space)->count(25)->create();

    // Page 1: 20 rows.
    $component = Livewire::actingAs($user)
        ->test(InboxIndex::class, ['spaceId' => $space->id]);
    $paginator = $component->instance()->testimonials;
    expect($paginator->perPage())->toBe(20)
        ->and($paginator->count())->toBe(20)
        ->and($paginator->total())->toBe(25);

    // Page 2: 5 rows. Use the WithPagination helper `gotoPage(2)`.
    $component = Livewire::actingAs($user)
        ->test(InboxIndex::class, ['spaceId' => $space->id])
        ->call('gotoPage', 2);
    $paginator = $component->instance()->testimonials;
    expect($paginator->count())->toBe(5)
        ->and($paginator->total())->toBe(25);
});

test('inbox pagination reflects the current filter', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    // 25 LIVE, 5 trashed (30 total).
    Testimonial::factory()->for($space)->count(25)->create();
    $trash = Testimonial::factory()->for($space)->count(5)->create();
    foreach ($trash as $r) {
        $r->delete();
    }

    // Default 'all' filter: 25 LIVE, paginated 20 per page.
    $component = Livewire::actingAs($user)
        ->test(InboxIndex::class, ['spaceId' => $space->id]);
    expect($component->instance()->testimonials->total())->toBe(25);

    // Trash filter: 5 rows, single page.
    $component = Livewire::actingAs($user)
        ->test(InboxIndex::class, ['spaceId' => $space->id, 'filter' => 'trash']);
    expect($component->instance()->testimonials->total())->toBe(5);
});
