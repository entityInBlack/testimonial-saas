<?php

use App\Livewire\Inbox\InboxIndex;
use App\Livewire\Inbox\InboxRow;
use App\Models\DeletionRequest;
use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/**
 * Regression test for the Inbox Trash tab.
 *
 * The parent InboxIndex passes per-row flags (:show-restore,
 * :show-forget, :show-soft-delete) to its <livewire:inbox.inbox-row>
 * child. The child component class must accept those properties;
 * otherwise Livewire silently drops them and the row view falls
 * back to its `?? true`/`?? false` defaults — which always hid
 * Restore and Forget on the trash tab. Step 4's earlier tests
 * called the actions directly, so they never rendered the parent
 * and missed the gap.
 *
 * These tests render the row through the REAL parent (Livewire
 * ::test(InboxIndex::class) as the owner) so the child rows get
 * the parent's flags, and assert which buttons are present per
 * filter.
 *
 * What would make this file FAIL:
 *   - InboxRow dropping the show-restore / show-forget /
 *     show-soft-delete properties (the trash tab would not show
 *     Restore and Forget, or the all filter would still show
 *     them).
 *   - InboxIndex passing the wrong flag (e.g. forgetting to gate
 *     on filter='trash' for the restore/forget buttons).
 *   - forget() running on a LIVE row (Step 4 hard rule: Trash
 *     only — PRD §7 / build order Step 4).
 */

test('InboxIndex filter=all with one live row shows Delete and hides Restore and Forget', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    $row = Testimonial::factory()->for($space)->create();

    $html = (string) Livewire::actingAs($user)
        ->test(InboxIndex::class, ['spaceId' => $space->id, 'filter' => 'all'])
        ->html();

    expect($html)->toContain('wire:click="softDelete"')
        ->and($html)->not->toContain('wire:click="restore"')
        ->and($html)->not->toContain('wire:click="forget"');
});

test('InboxIndex filter=trash with one trashed row shows Restore and Forget and hides Delete', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    $row = Testimonial::factory()->for($space)->create();
    $row->delete();

    $html = (string) Livewire::actingAs($user)
        ->test(InboxIndex::class, ['spaceId' => $space->id, 'filter' => 'trash'])
        ->html();

    expect($html)->toContain('wire:click="restore"')
        ->and($html)->toContain('wire:click="forget"')
        ->and($html)->not->toContain('wire:click="softDelete"');
});

test('InboxRow forget on a LIVE row is refused; the row stays and any open deletion_request is unchanged', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    $row = Testimonial::factory()->for($space)->create(['profile_photo' => null]);

    // Seed an open deletion_request that would otherwise be closed
    // by forget — proves the guard refused BEFORE Step 1.
    DB::table('deletion_requests')->insert([
        'email' => $row->email,
        'space_slug' => $space->slug,
        'space_id' => $space->id,
        'testimonial_id' => $row->id,
        'status' => DeletionRequest::STATUS_OPEN,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Livewire::actingAs($user)
        ->test(InboxRow::class, ['testimonialId' => $row->id])
        ->call('forget');

    // Row must still exist with deleted_at null and not be hard-deleted.
    $fresh = Testimonial::withTrashed()->find($row->id);
    expect($fresh)->not->toBeNull()
        ->and($fresh->deleted_at)->toBeNull();

    // The matching deletion_request is still 'open' with acted_at null.
    $dr = DB::table('deletion_requests')
        ->where('email', $row->email)
        ->where('space_id', $space->id)
        ->first();
    expect($dr->status)->toBe(DeletionRequest::STATUS_OPEN)
        ->and($dr->acted_at)->toBeNull();
});

test('InboxRow forget on a TRASHED row completes: row hard-deleted and matching open request becomes acted', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    $row = Testimonial::factory()->for($space)->create(['profile_photo' => null]);

    DB::table('deletion_requests')->insert([
        'email' => $row->email,
        'space_slug' => $space->slug,
        'space_id' => $space->id,
        'testimonial_id' => $row->id,
        'status' => DeletionRequest::STATUS_OPEN,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Trash first through the real InboxRow action.
    Livewire::actingAs($user)
        ->test(InboxRow::class, ['testimonialId' => $row->id])
        ->call('softDelete');

    Livewire::actingAs($user)
        ->test(InboxRow::class, ['testimonialId' => $row->id])
        ->call('forget');

    // Row is hard-deleted.
    expect(Testimonial::withTrashed()->where('id', $row->id)->count())->toBe(0);

    // Matching open request is now 'acted' with acted_at set.
    $dr = DB::table('deletion_requests')
        ->where('email', $row->email)
        ->where('space_id', $space->id)
        ->first();
    expect($dr->status)->toBe(DeletionRequest::STATUS_ACTED)
        ->and($dr->acted_at)->not->toBeNull();
});
