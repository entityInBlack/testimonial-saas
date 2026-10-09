<?php

use App\Models\Space;
use App\Models\User;

test('check-slug endpoint requires authentication', function () {
    $this->getJson('/spaces/check-slug?slug=foo')
        ->assertStatus(401);
});

test('check-slug returns available=true for a free slug', function () {
    $user = User::factory()->create();
    $this->actingAs($user)
        ->getJson('/spaces/check-slug?slug=brand-new-slug')
        ->assertOk()
        ->assertJson([
            'available' => true,
            'suggested' => null,
        ]);
});

test('check-slug returns available=false and a -2 suggestion for a slug held by a LIVE Space', function () {
    $user = User::factory()->create();
    Space::factory()->for($user)->create(['slug' => 'taken-by-live']);

    $this->actingAs($user)
        ->getJson('/spaces/check-slug?slug=taken-by-live')
        ->assertOk()
        ->assertJson([
            'available' => false,
            'suggested' => 'taken-by-live-2',
        ]);
});

test('check-slug returns available=false for a soft-deleted slug (the slug is still claimed)', function () {
    $user = User::factory()->create();
    Space::factory()->for($user)->create(['slug' => 'soft-taken'])->delete();

    $this->actingAs($user)
        ->getJson('/spaces/check-slug?slug=soft-taken')
        ->assertOk()
        ->assertJson([
            'available' => false,
            'suggested' => 'soft-taken-2',
        ]);
});

test('check-slug returns available=false for a reserved word, no 500', function () {
    $user = User::factory()->create();
    foreach (Space::RESERVED_SLUGS as $reserved) {
        $this->actingAs($user)
            ->getJson('/spaces/check-slug?slug='.$reserved)
            ->assertOk()
            ->assertJson([
                'available' => false,
                'suggested' => $reserved.'-2',
            ]);
    }
});

test('check-slug normalizes the input (uppercase, spaces, punctuation) before checking', function () {
    $user = User::factory()->create();
    Space::factory()->for($user)->create(['slug' => 'acme-wall']);

    $this->actingAs($user)
        ->getJson('/spaces/check-slug?slug='.urlencode('ACME Wall!'))
        ->assertOk()
        ->assertJson([
            'available' => false,
            'suggested' => 'acme-wall-2',
        ]);
});

test('check-slug handles an empty / blank slug without 500', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/spaces/check-slug?slug=')
        ->assertOk()
        ->assertJsonStructure(['available', 'suggested']);
});

test('check-slug response always has the canonical {available, suggested} shape', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/spaces/check-slug?slug=anything')
        ->assertOk()
        ->assertJsonStructure([
            'available',
            'suggested',
        ]);
});
