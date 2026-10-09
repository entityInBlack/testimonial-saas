<?php

use App\Livewire\Inbox\InboxIndex;
use App\Livewire\Inbox\InboxRow;
use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/**
 * Step 4.5 / Step 4.6: Trash Restore.
 *
 * Restore clears deleted_at, the STORED is_public column recomputes
 * automatically, and flags come back as they were. Restore is
 * ALLOWED even when the Space is at the testimonial cap — the cap
 * only blocks NEW public submissions, not the return of existing
 * data (PRD §3.3 / Step 3 lock applies only to /s/{slug}).
 */

test('restore clears deleted_at and flags come back unchanged', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    $row = Testimonial::factory()->for($space)->create([
        'is_favorite' => true,
        'is_wall_of_love' => true,
        'is_hidden' => false,
    ]);
    $row->delete();

    expect($row->fresh()->deleted_at)->not->toBeNull();

    Livewire::actingAs($user)
        ->test(InboxRow::class, ['testimonialId' => $row->id])
        ->call('restore');

    $fresh = $row->fresh();
    expect($fresh->deleted_at)->toBeNull()
        ->and((bool) $fresh->is_favorite)->toBeTrue()
        ->and((bool) $fresh->is_wall_of_love)->toBeTrue()
        ->and((bool) $fresh->is_hidden)->toBeFalse();
});

test('restore recomputes is_public automatically (STORED column)', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    $row = Testimonial::factory()->for($space)->create([
        'consent_given' => true, 'is_wall_of_love' => true, 'is_hidden' => false,
    ]);

    expect((int) DB::table('testimonials')->where('id', $row->id)->value('is_public'))->toBe(1);

    $row->delete();

    // After soft-delete, is_public is 0 (deleted_at is set in STORED).
    expect((int) DB::table('testimonials')->where('id', $row->id)->value('is_public'))->toBe(0);

    Livewire::actingAs($user)
        ->test(InboxRow::class, ['testimonialId' => $row->id])
        ->call('restore');

    // After restore, is_public recomputes to 1.
    expect((int) DB::table('testimonials')->where('id', $row->id)->value('is_public'))->toBe(1);
});

test('restore is ALLOWED even when the Space is at the testimonial cap', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    $cap = (int) config('limits.max_testimonials_per_space');

    // Build-order requirement: "fill a Space to the cap, soft-delete
    // one, confirm cap drops to cap-1, soft-delete-restore it, confirm
    // restore succeeds even though the Space is 'full'."
    //
    // We use direct DB writes (the public submission lock is enforced
    // at the /s/{slug} submission layer, not at the DB level — the
    // tests are creating rows directly through the model, which is
    // how every other Step 4 test does it).

    // 1. Fill the Space to the cap with LIVE rows.
    Testimonial::factory()->for($space)->count($cap)->create();
    expect($space->testimonials()->count())->toBe($cap);

    // 2. Soft-delete ONE — the live count drops to cap-1.
    $trashed = Testimonial::factory()->for($space)->create(); // LIVE count now cap+1
    expect($space->testimonials()->count())->toBe($cap + 1);
    $trashed->delete();
    expect($space->testimonials()->count())->toBe($cap);

    // 3. Restore the soft-deleted row — must succeed even though the
    //    Space is "full" at cap+1 LIVE. (In production the cap+1
    //    state cannot be reached because /s/{slug} locks at cap, but
    //    the test proves the Restore action itself does not enforce
    //    the cap.)
    Livewire::actingAs($user)
        ->test(InboxRow::class, ['testimonialId' => $trashed->id])
        ->call('restore');

    expect($trashed->fresh()->deleted_at)->toBeNull();
});

test('restore is allowed when the live count is at exactly the cap', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    $cap = (int) config('limits.max_testimonials_per_space');

    // Fill to the cap, then create a soft-deleted one (so the live
    // count stays at exactly $cap while the Space has a trashed row).
    Testimonial::factory()->for($space)->count($cap)->create();
    $soft = Testimonial::factory()->for($space)->create();
    $soft->delete();
    expect($space->testimonials()->count())->toBe($cap);

    // Restore must succeed even though the live count is at cap.
    Livewire::actingAs($user)
        ->test(InboxRow::class, ['testimonialId' => $soft->id])
        ->call('restore');

    expect($soft->fresh()->deleted_at)->toBeNull();
});

test('trash filter lists soft-deleted rows and hides live rows', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    $live = Testimonial::factory()->for($space)->create(['name' => 'LiveRow']);
    $dead = Testimonial::factory()->for($space)->create(['name' => 'DeadRow']);
    $dead->delete();

    Livewire::actingAs($user)
        ->test(InboxIndex::class, ['spaceId' => $space->id, 'filter' => 'trash'])
        ->assertSee('DeadRow')
        ->assertDontSee('LiveRow');

    Livewire::actingAs($user)
        ->test(InboxIndex::class, ['spaceId' => $space->id, 'filter' => 'all'])
        ->assertSee('LiveRow')
        ->assertDontSee('DeadRow');
});
