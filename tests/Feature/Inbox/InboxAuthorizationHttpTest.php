<?php

use App\Livewire\Inbox\InboxIndex;
use App\Livewire\Inbox\InboxRow;
use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;
use Livewire\Livewire;

/**
 * HTTP-boundary proof of cross-user authorization.
 *
 * Each InboxRow action is a Livewire component method. From a real
 * browser, the only way to reach these methods is via the Livewire
 * JavaScript client, which POSTs to /livewire/update with a serialized
 * snapshot + method name. Livewire's /livewire/update route handler
 * (vendor/livewire/livewire/src/Mechanisms/HandleRequests/HandleRequests.php)
 * catches ONLY TypeError — any other exception, including the
 * NotFoundHttpException thrown by abort(404), propagates to Laravel's
 * exception handler and becomes a real HTTP 404 response.
 *
 * We do NOT use Livewire::test()->call('withdrawConsent') here, because
 * the test harness (SubsequentRender::makeSubsequentRequest, line 35)
 * calls temporarilyDisableExceptionHandlingAndMiddleware, which masks
 * the real HTTP behaviour. This file fires a real POST /livewire/update
 * with Laravel's exception handler fully active.
 */

test('user B hitting withdrawConsent via /livewire/update gets a real HTTP 404 and the row is unchanged', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $space = Space::factory()->for($alice)->create();
    $row = Testimonial::factory()->for($space)->create([
        'consent_given' => true,
        'is_wall_of_love' => true,
    ]);

    // 1. Build a real, valid Livewire snapshot AS ALICE. This snapshot
    // encodes an InboxRow instance mounted with testimonialId=
    // {alice's row}. We use Livewire's testable ONLY for the
    // InitialRender path (which does NOT disable exception handling),
    // then steal the snapshot out of the resulting state.
    $aliceMounted = Livewire::actingAs($alice)
        ->test(InboxRow::class, ['testimonialId' => $row->id]);

    // Reach into the testable's lastState to get the snapshot.
    $reflection = new ReflectionObject($aliceMounted);
    $lastStateProp = $reflection->getProperty('lastState');
    $lastStateProp->setAccessible(true);
    $lastState = $lastStateProp->getValue($aliceMounted);

    $snapshot = $lastState->getSnapshot();

    // 2. Build the exact payload shape the Livewire front-controller
    //    expects at /livewire/update (see HandleRequests::handleUpdate
    //    lines 116-130).
    $payload = [
        'components' => [
            [
                'snapshot' => json_encode($snapshot),
                'calls' => [
                    [
                        'path' => '',
                        'method' => 'withdrawConsent',
                        'params' => [],
                    ],
                ],
                'updates' => [],
            ],
        ],
    ];

    // 3. POST that payload to /livewire/update AS BOB. The handler
    //    re-hydrates the InboxRow from the snapshot, runs
    //    withdrawConsent() → refresh() → authorizeOwner() → abort(404)
    //    because $space->user_id !== $bob->id. The NotFoundHttpException
    //    bubbles past Livewire's try/catch (which only catches TypeError)
    //    and Laravel's exception handler turns it into a 404 response.
    $response = $this->actingAs($bob)
        ->withHeaders(['X-Livewire' => '1'])
        ->post('/livewire/update', $payload);

    // 4. Assert the real HTTP status. The Livewire test harness was
    //    NOT used — this is the real boundary.
    $response->assertStatus(404);

    // 5. The row must be unchanged.
    $fresh = $row->fresh();
    expect((bool) $fresh->consent_given)->toBeTrue()
        ->and((bool) $fresh->is_wall_of_love)->toBeTrue();
});

test('CONTROL: user A hitting withdrawConsent via /livewire/update gets 200 (sanity check that 404 is from auth, not from the test setup)', function () {
    $alice = User::factory()->create();
    $space = Space::factory()->for($alice)->create();
    $row = Testimonial::factory()->for($space)->create([
        'consent_given' => true,
        'is_wall_of_love' => true,
    ]);

    $aliceMounted = Livewire::actingAs($alice)
        ->test(InboxRow::class, ['testimonialId' => $row->id]);

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
                    ['path' => '', 'method' => 'withdrawConsent', 'params' => []],
                ],
                'updates' => [],
            ],
        ],
    ];

    $response = $this->actingAs($alice)
        ->withHeaders(['X-Livewire' => '1'])
        ->post('/livewire/update', $payload);

    // 200 OK: Alice owns the row, the action runs, the response is a
    // normal Livewire JSON envelope. (NOT 404.)
    $response->assertOk();

    // Row state DID change: consent was withdrawn.
    $fresh = $row->fresh();
    expect((bool) $fresh->consent_given)->toBeFalse()
        ->and((bool) $fresh->is_wall_of_love)->toBeFalse();
});

test('user B hitting forget via /livewire/update gets a real HTTP 404 and the row is unchanged', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $space = Space::factory()->for($alice)->create();
    $row = Testimonial::factory()->for($space)->create([
        'testimonial' => 'Original',
        'profile_photo' => null,
    ]);

    $aliceMounted = Livewire::actingAs($alice)
        ->test(InboxRow::class, ['testimonialId' => $row->id]);

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
                    ['path' => '', 'method' => 'forget', 'params' => []],
                ],
                'updates' => [],
            ],
        ],
    ];

    $response = $this->actingAs($bob)
        ->withHeaders(['X-Livewire' => '1'])
        ->post('/livewire/update', $payload);

    $response->assertStatus(404);

    // Row must still exist (forget was rejected with 404, not run).
    expect(Testimonial::withTrashed()->find($row->id))->not->toBeNull()
        ->and($row->fresh()->testimonial)->toBe('Original');
});
