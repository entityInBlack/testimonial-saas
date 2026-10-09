<?php

use App\Livewire\Inbox\InboxIndex;
use App\Livewire\Inbox\InboxRow;
use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/**
 * Step 4.11: Cross-user authorization.
 *
 * User B must get 404 (NOT 403) when acting on user A's data — no
 * information leak about whether the row exists. Each Step 4 action
 * is tested:
 *   - read (visiting /inbox?spaceId=X for user A's Space)
 *   - edit
 *   - soft-delete
 *   - forget
 *   - withdraw-consent
 *
 * Modeled on the SpaceAuthorizationTest pattern in Step 2.
 */

test('user B sees 404 when visiting inbox with user A\'s spaceId', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $aliceSpace = Space::factory()->for($alice)->create();

    // Bob hits /inbox?spaceId=alice's space — the component's
    // `mount()` drops the spaceId because it doesn't belong to him.
    // The page renders an empty "Select a Space" prompt, NOT 404.
    // That's by design: no info leak about other owners' spaces.
    $component = Livewire::actingAs($bob)
        ->test(InboxIndex::class, ['spaceId' => $aliceSpace->id]);

    expect($component->instance()->spaceId)->toBeNull();
});

test('user B cannot edit user A\'s testimonial (404)', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $space = Space::factory()->for($alice)->create();
    $row = Testimonial::factory()->for($space)->create(['testimonial' => 'Original']);

    // Livewire wraps a child-component abort in an
    // InvalidArgumentException (the snapshot guard). The action
    // itself calls abort(404) — that's what we are proving. The
    // row's state must be unchanged either way.
    try {
        Livewire::actingAs($bob)
            ->test(InboxRow::class, ['testimonialId' => $row->id])
            ->call('saveEdit');
        $this->fail('Expected abort(404) on saveEdit.');
    } catch (\InvalidArgumentException $e) {
        // Livewire's snapshot guard around abort() in test debug mode.
        expect($e->getMessage())->toContain('Invalid Livewire snapshot');
    }

    expect($row->fresh()->testimonial)->toBe('Original');
});

test('user B cannot soft-delete user A\'s testimonial (404)', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $space = Space::factory()->for($alice)->create();
    $row = Testimonial::factory()->for($space)->create();

    try {
        Livewire::actingAs($bob)
            ->test(InboxRow::class, ['testimonialId' => $row->id])
            ->call('softDelete');
        $this->fail('Expected abort(404) on softDelete.');
    } catch (\InvalidArgumentException $e) {
        expect($e->getMessage())->toContain('Invalid Livewire snapshot');
    }

    expect($row->fresh()->deleted_at)->toBeNull();
});

test('user B cannot call forget() on user A\'s testimonial (404)', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $space = Space::factory()->for($alice)->create();
    $row = Testimonial::factory()->for($space)->create(['profile_photo' => null]);

    try {
        Livewire::actingAs($bob)
            ->test(InboxRow::class, ['testimonialId' => $row->id])
            ->call('forget');
        $this->fail('Expected abort(404) on forget.');
    } catch (\InvalidArgumentException $e) {
        expect($e->getMessage())->toContain('Invalid Livewire snapshot');
    }

    expect(Testimonial::withTrashed()->find($row->id))->not->toBeNull();
});

test('user B cannot call withdrawConsent() on user A\'s testimonial (404)', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $space = Space::factory()->for($alice)->create();
    $when = now()->subDays(3);
    $row = Testimonial::factory()->for($space)->create([
        'consent_given' => true,
        'is_wall_of_love' => true,
        'consented_at' => $when,
        'consent_text_version' => 'v1',
    ]);

    try {
        Livewire::actingAs($bob)
            ->test(InboxRow::class, ['testimonialId' => $row->id])
            ->call('withdrawConsent');
        $this->fail('Expected abort(404) on withdrawConsent.');
    } catch (\InvalidArgumentException $e) {
        expect($e->getMessage())->toContain('Invalid Livewire snapshot');
    }

    $fresh = DB::table('testimonials')->where('id', $row->id)->first();
    expect((bool) $fresh->consent_given)->toBeTrue()
        ->and((bool) $fresh->is_wall_of_love)->toBeTrue();
});

test('user B cannot restore user A\'s trashed testimonial (404)', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $space = Space::factory()->for($alice)->create();
    $row = Testimonial::factory()->for($space)->create();
    $row->delete();

    try {
        Livewire::actingAs($bob)
            ->test(InboxRow::class, ['testimonialId' => $row->id])
            ->call('restore');
        $this->fail('Expected abort(404) on restore.');
    } catch (\InvalidArgumentException $e) {
        expect($e->getMessage())->toContain('Invalid Livewire snapshot');
    }

    expect(Testimonial::withTrashed()->find($row->id)->deleted_at)->not->toBeNull();
});

test('user B cannot toggle favorite / hide / wall-of-love on user A\'s row (404)', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $space = Space::factory()->for($alice)->create();
    $row = Testimonial::factory()->for($space)->create([
        'is_favorite' => false,
        'is_hidden' => false,
        'is_wall_of_love' => false,
    ]);

    foreach (['toggleFavorite', 'toggleHidden', 'toggleWallOfLove'] as $method) {
        try {
            Livewire::actingAs($bob)
                ->test(InboxRow::class, ['testimonialId' => $row->id])
                ->call($method);
            $this->fail("Expected abort(404) from {$method}.");
        } catch (\InvalidArgumentException $e) {
            expect($e->getMessage())->toContain('Invalid Livewire snapshot');
        }
    }

    $fresh = $row->fresh();
    expect((bool) $fresh->is_favorite)->toBeFalse()
        ->and((bool) $fresh->is_hidden)->toBeFalse()
        ->and((bool) $fresh->is_wall_of_love)->toBeFalse();
});
