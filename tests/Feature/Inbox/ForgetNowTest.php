<?php

use App\Livewire\Inbox\InboxRow;
use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * Step 4.7 / Step 4.8: Forget-now three-step order.
 *
 *   1. UPDATE matching deletion_requests (status='acted', acted_at=now)
 *      BEFORE anything else — so the testimonial_id FK is still
 *      populated. The match is by testimonial_id OR
 *      (testimonial_id IS NULL AND email=:email).
 *   2. Delete the photo from the `public` disk. Ignore ONLY
 *      "file not found"; any other error aborts the whole forget().
 *   3. forceDelete() the testimonial row LAST — so the
 *      nullOnDelete on deletion_requests.testimonial_id does not
 *      fire before Step 1.
 */

test('forget closes matching open deletion_requests, deletes photo, and force-deletes the row', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    $row = Testimonial::factory()->for($space)->create([
        'email' => 'sara@example.test',
        'profile_photo' => 'photos/abc123.webp',
    ]);

    // Write a real file on the fake disk so the delete has something
    // to find.
    Storage::disk('public')->put('photos/abc123.webp', 'fake-image-bytes');

    // Seed an open deletion_request with testimonial_id=NULL on
    // purpose (matches by email — Hard Rule 14 / Step 8 pattern).
    DB::table('deletion_requests')->insert([
        'email' => 'sara@example.test',
        'space_slug' => $space->slug,
        'space_id' => $space->id,
        'testimonial_id' => null,
        'status' => 'open',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Livewire::actingAs($user)
        ->test(InboxRow::class, ['testimonialId' => $row->id])
        ->call('forget');

    // 1. The deletion_request is now 'acted' with acted_at set.
    $dr = DB::table('deletion_requests')
        ->where('email', 'sara@example.test')
        ->where('space_id', $space->id)
        ->first();
    expect($dr->status)->toBe('acted');
    expect($dr->acted_at)->not->toBeNull();

    // 2. The photo is gone.
    Storage::disk('public')->assertMissing('photos/abc123.webp');

    // 3. The testimonial is hard-deleted (gone even from withTrashed()).
    expect(Testimonial::withTrashed()->find($row->id))->toBeNull();
});

test('forget matches deletion_requests by testimonial_id too (not just email)', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    $row = Testimonial::factory()->for($space)->create(['profile_photo' => null]);
    Storage::disk('public')->put('photos/abc.webp', 'x'); // ensure no photo, but keep file present

    DB::table('deletion_requests')->insert([
        'email' => $row->email, // same email so we can find it after FK nulls
        'space_slug' => $space->slug,
        'space_id' => $space->id,
        'testimonial_id' => $row->id, // matched by id
        'status' => 'open',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Livewire::actingAs($user)
        ->test(InboxRow::class, ['testimonialId' => $row->id])
        ->call('forget');

    // After forceDelete, the nullOnDelete FK sets testimonial_id to
    // null — so we look up by email + space_id, which is the only
    // way to find the row post-delete.
    $dr = DB::table('deletion_requests')
        ->where('email', $row->email)
        ->where('space_id', $space->id)
        ->first();
    expect($dr->status)->toBe('acted');
    expect($dr->testimonial_id)->toBeNull();
    expect(Testimonial::withTrashed()->find($row->id))->toBeNull();
});

test('forget works when the photo file is already missing (no error, still completes)', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    $row = Testimonial::factory()->for($space)->create([
        'email' => 'sara@example.test',
        'profile_photo' => 'photos/missing.webp',
    ]);
    // No file is actually written to the disk — simulate "file not
    // found" on delete.

    DB::table('deletion_requests')->insert([
        'email' => 'sara@example.test',
        'space_slug' => $space->slug,
        'space_id' => $space->id,
        'testimonial_id' => null,
        'status' => 'open',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Livewire::actingAs($user)
        ->test(InboxRow::class, ['testimonialId' => $row->id])
        ->call('forget');

    // The testimonial is hard-deleted; the deletion_request is acted.
    expect(Testimonial::withTrashed()->find($row->id))->toBeNull();
    $dr = DB::table('deletion_requests')->where('email', 'sara@example.test')->first();
    expect($dr->status)->toBe('acted');
});

test('forget only acts on OPEN deletion_requests (skips already-acted)', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    $row = Testimonial::factory()->for($space)->create(['email' => 'sara@example.test', 'profile_photo' => null]);

    $when = now()->subDays(2);

    DB::table('deletion_requests')->insert([
        [
            'email' => 'sara@example.test',
            'space_slug' => $space->slug,
            'space_id' => $space->id,
            'testimonial_id' => $row->id,
            'status' => 'acted',
            'acted_at' => $when,
            'created_at' => $when,
            'updated_at' => $when,
        ],
    ]);

    Livewire::actingAs($user)
        ->test(InboxRow::class, ['testimonialId' => $row->id])
        ->call('forget');

    $dr = DB::table('deletion_requests')->where('email', 'sara@example.test')->first();
    // The original acted_at is preserved — we did NOT touch it.
    expect(\Carbon\Carbon::parse($dr->acted_at)->toDateTimeString())
        ->toBe($when->toDateTimeString());
});

test('forget runs Step 1 BEFORE Step 3 (UPDATE before forceDelete) — nullOnDelete safety', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    $row = Testimonial::factory()->for($space)->create(['profile_photo' => null]);

    // Seed a deletion_request pointing at this testimonial with a
    // real testimonial_id. After forget, the testimonial_id FK
    // SHOULD be nulled (via nullOnDelete) — but the row's status
    // was already 'acted' before that happened. The Step 1 update
    // must succeed even if the FK nulls later.
    DB::table('deletion_requests')->insert([
        'email' => $row->email,
        'space_slug' => $space->slug,
        'space_id' => $space->id,
        'testimonial_id' => $row->id,
        'status' => 'open',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Livewire::actingAs($user)
        ->test(InboxRow::class, ['testimonialId' => $row->id])
        ->call('forget');

    $dr = DB::table('deletion_requests')->where('email', $row->email)->first();
    expect($dr->status)->toBe('acted');
    expect($dr->acted_at)->not->toBeNull();
    // After forceDelete, the nullOnDelete set testimonial_id to null.
    expect($dr->testimonial_id)->toBeNull();
});
