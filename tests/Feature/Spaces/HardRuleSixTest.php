<?php

use App\Livewire\Spaces\SpaceIndex;
use App\Models\Space;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
 * Hard Rule 6 (build order §3): soft-delete touches NOTHING but `deleted_at`.
 * This test pins the rule at the FILE level — only the `purge:run` job
 * (Step 6) is allowed to remove files. A soft-deleted testimonial that
 * still has a photo file on the `public` disk must keep that file through
 * the soft-delete AND through the parent Space's soft-delete.
 */

test('soft-delete leaves photo files on the public disk untouched (Hard Rule 6)', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();

    // 1) create a testimonial row with a photo path that exists on disk.
    $path = 'photos/abc-original.webp';
    Storage::disk('public')->put($path, 'fake-webp-bytes');

    DB::table('testimonials')->insert([
        'space_id' => $space->id,
        'name' => 'Priya',
        'email' => 'priya-'.uniqid().'@e.test',
        'address' => '1 Test',
        'testimonial' => 'soft-delete me',
        'consent_given' => 1,
        'is_wall_of_love' => 1,
        'is_hidden' => 0,
        'profile_photo' => $path,
        'submitted_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(Storage::disk('public')->exists($path))->toBeTrue();

    // 2) soft-delete the testimonial (raw — there's no Livewire UI for it yet).
    DB::table('testimonials')->where('profile_photo', $path)->update([
        'deleted_at' => now(),
    ]);

    // 3) soft-delete the parent Space via the live Index action.
    Livewire::actingAs($user)
        ->test(SpaceIndex::class)
        ->call('delete', $space->id);

    // The file STILL exists. Only `purge:run` (Step 6) may remove it.
    expect(Storage::disk('public')->exists($path))->toBeTrue();

    // Sanity: rows still present, just soft-deleted.
    expect(DB::table('testimonials')->where('profile_photo', $path)->value('deleted_at'))->not->toBeNull();
    expect(DB::table('spaces')->where('id', $space->id)->value('deleted_at'))->not->toBeNull();
});
