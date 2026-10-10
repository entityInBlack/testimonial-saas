<?php

use App\Livewire\Dashboard\DashboardIndex;
use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;
use Livewire\Livewire;

/**
 * Step 5 — HTTP-boundary proof of cross-user authorization on
 * /dashboard-driven actions.
 *
 * Mirrors Step 4's InboxAuthorizationHttpTest pattern. We mount
 * a DashboardIndex as Alice (the owner), steal the Livewire
 * snapshot, then POST a `restoreDeletedSpace` call as Bob to
 * /livewire/update. Bob must get a real 404 (with the exception
 * handler ACTIVE), and the Space must remain soft-deleted. The
 * CONTROL case asserts that Alice gets 200 (sanity check).
 */

test('user B hitting restoreDeletedSpace via /livewire/update gets a real HTTP 404 and the Space stays deleted', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $space = Space::factory()->for($alice)->create();
    $space->delete();

    $aliceMounted = Livewire::actingAs($alice)
        ->test(DashboardIndex::class, ['tab' => 'deleted']);

    $reflection = new ReflectionObject($aliceMounted);
    $lastStateProp = $reflection->getProperty('lastState');
    $lastStateProp->setAccessible(true);
    $lastState = $lastStateProp->getValue($aliceMounted);
    $snapshot = $lastState->getSnapshot();

    $payload = [
        'components' => [
            [
                'snapshot' => json_encode($snapshot),
                'calls' => [
                    [
                        'path' => '',
                        'method' => 'restoreDeletedSpace',
                        'params' => [$space->id],
                    ],
                ],
                'updates' => [],
            ],
        ],
    ];

    $response = $this->actingAs($bob)
        ->withHeaders(['X-Livewire' => '1'])
        ->post('/livewire/update', $payload);

    $response->assertStatus(404);
    expect(Space::withTrashed()->find($space->id)->deleted_at)->not->toBeNull();
});

test('CONTROL: user A (the owner) hitting restoreDeletedSpace via /livewire/update gets 200 and the Space is restored', function () {
    $alice = User::factory()->create();
    $space = Space::factory()->for($alice)->create();
    $space->delete();

    $aliceMounted = Livewire::actingAs($alice)
        ->test(DashboardIndex::class, ['tab' => 'deleted']);

    $reflection = new ReflectionObject($aliceMounted);
    $lastStateProp = $reflection->getProperty('lastState');
    $lastStateProp->setAccessible(true);
    $lastState = $lastStateProp->getValue($aliceMounted);
    $snapshot = $lastState->getSnapshot();

    $payload = [
        'components' => [
            [
                'snapshot' => json_encode($snapshot),
                'calls' => [
                    [
                        'path' => '',
                        'method' => 'restoreDeletedSpace',
                        'params' => [$space->id],
                    ],
                ],
                'updates' => [],
            ],
        ],
    ];

    $response = $this->actingAs($alice)
        ->withHeaders(['X-Livewire' => '1'])
        ->post('/livewire/update', $payload);

    $response->assertOk();
    expect(Space::withTrashed()->find($space->id)->deleted_at)->toBeNull();
});
