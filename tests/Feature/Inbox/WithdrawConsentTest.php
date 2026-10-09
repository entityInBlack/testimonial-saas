<?php

use App\Livewire\Inbox\InboxRow;
use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/**
 * Step 4.3 / Hard Rule 7: withdraw-consent is a Livewire method on
 * the inbox row component, no POST route. The method:
 *   - sets consent_given=0 AND is_wall_of_love=0 in the same UPDATE
 *   - KEEPS consented_at and consent_text_version UNCHANGED (audit)
 *   - never writes to is_public (STORED column recomputes)
 *   - never deletes the row
 */

test('withdrawConsent sets consent_given=0 AND is_wall_of_love=0', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    $row = Testimonial::factory()->for($space)->create([
        'consent_given' => true,
        'is_wall_of_love' => true,
    ]);

    Livewire::actingAs($user)
        ->test(InboxRow::class, ['testimonialId' => $row->id])
        ->call('withdrawConsent');

    $fresh = DB::table('testimonials')->where('id', $row->id)->first();
    expect((bool) $fresh->consent_given)->toBeFalse()
        ->and((bool) $fresh->is_wall_of_love)->toBeFalse();
});

test('withdrawConsent KEEPS consented_at and consent_text_version as audit history', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    $when = now()->subDays(7);
    $row = Testimonial::factory()->for($space)->create([
        'consent_given' => true,
        'is_wall_of_love' => true,
        'consented_at' => $when,
        'consent_text_version' => 'v1',
    ]);

    // Snapshot BEFORE.
    $before = DB::table('testimonials')->where('id', $row->id)->first();
    expect($before->consent_text_version)->toBe('v1');
    expect($before->consented_at)->not->toBeNull();

    Livewire::actingAs($user)
        ->test(InboxRow::class, ['testimonialId' => $row->id])
        ->call('withdrawConsent');

    // AFTER — audit fields UNCHANGED.
    $after = DB::table('testimonials')->where('id', $row->id)->first();
    expect($after->consent_text_version)->toBe('v1');
    expect($after->consented_at)->toBe($when->toDateTimeString());
});

test('withdrawConsent recomputes is_public to 0 (STORED generated column)', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    $row = Testimonial::factory()->for($space)->create([
        'consent_given' => true,
        'is_wall_of_love' => true,
        'is_hidden' => false,
    ]);

    // Sanity: before withdraw, is_public=1 (consent+WoL+visible+alive).
    $before = (int) DB::table('testimonials')->where('id', $row->id)->value('is_public');
    expect($before)->toBe(1);

    Livewire::actingAs($user)
        ->test(InboxRow::class, ['testimonialId' => $row->id])
        ->call('withdrawConsent');

    // After withdraw: is_public recomputes to 0 because consent_given=0.
    $after = (int) DB::table('testimonials')->where('id', $row->id)->value('is_public');
    expect($after)->toBe(0);
});

test('withdrawConsent does NOT delete the testimonial row', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    $row = Testimonial::factory()->for($space)->create(['consent_given' => true]);

    Livewire::actingAs($user)
        ->test(InboxRow::class, ['testimonialId' => $row->id])
        ->call('withdrawConsent');

    expect(Testimonial::withTrashed()->find($row->id))->not->toBeNull();
    expect(Testimonial::find($row->id))->not->toBeNull();
});
