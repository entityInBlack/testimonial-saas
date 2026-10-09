<?php

use App\Livewire\Inbox\InboxIndex;
use App\Livewire\Inbox\InboxRow;
use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;
use Livewire\Livewire;

test('favorite toggle flips and persists', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    $row = Testimonial::factory()->for($space)->create(['is_favorite' => false]);

    Livewire::actingAs($user)
        ->test(InboxRow::class, ['testimonialId' => $row->id])
        ->call('toggleFavorite');

    expect((bool) $row->fresh()->is_favorite)->toBeTrue();

    Livewire::actingAs($user)
        ->test(InboxRow::class, ['testimonialId' => $row->id])
        ->call('toggleFavorite');

    expect((bool) $row->fresh()->is_favorite)->toBeFalse();
});

test('hide toggle flips and persists', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    $row = Testimonial::factory()->for($space)->create(['is_hidden' => false]);

    Livewire::actingAs($user)
        ->test(InboxRow::class, ['testimonialId' => $row->id])
        ->call('toggleHidden');

    expect((bool) $row->fresh()->is_hidden)->toBeTrue();
});

test('wall of love is BLOCKED when consent_given=0 (single-row)', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    $row = Testimonial::factory()->for($space)->noConsent()->create(['is_wall_of_love' => false]);

    Livewire::actingAs($user)
        ->test(InboxRow::class, ['testimonialId' => $row->id])
        ->call('toggleWallOfLove');

    // Block: is_wall_of_love stays false.
    expect((bool) $row->fresh()->is_wall_of_love)->toBeFalse();
});

test('wall of love toggles when consent is given', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    $row = Testimonial::factory()->for($space)->create(['is_wall_of_love' => false]);

    Livewire::actingAs($user)
        ->test(InboxRow::class, ['testimonialId' => $row->id])
        ->call('toggleWallOfLove');

    expect((bool) $row->fresh()->is_wall_of_love)->toBeTrue();
});

test('edit overwrites testimonial text and rating with no original kept, no edited marker', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create(['rating_enabled' => true]);
    $row = Testimonial::factory()->for($space)->create([
        'testimonial' => 'Original text body',
        'rating' => 3,
    ]);

    Livewire::actingAs($user)
        ->test(InboxRow::class, ['testimonialId' => $row->id])
        ->call('startEdit')
        ->set('editTestimonial', 'Edited text body — totally different')
        ->set('editRating', 5)
        ->call('saveEdit');

    $fresh = $row->fresh();
    expect($fresh->testimonial)->toBe('Edited text body — totally different')
        ->and($fresh->rating)->toBe(5)
        // No "edited" column exists in the schema, and the model
        // has no such attribute.
        ->and(\Schema::hasColumn('testimonials', 'edited'))->toBeFalse()
        ->and(\Schema::hasColumn('testimonials', 'edited_at'))->toBeFalse()
        ->and(\Schema::hasColumn('testimonials', 'original_testimonial'))->toBeFalse();
});

test('edit rejects empty/emoji-only/zero-width-only bodies', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    $row = Testimonial::factory()->for($space)->create(['testimonial' => 'Original']);

    $cases = [
        'whitespace' => "   \n\t  ",
        'emoji-only' => '😀😁😂🤣',
        'zero-width' => "\u{200B}\u{200C}\u{200D}",
    ];

    foreach ($cases as $label => $body) {
        Livewire::actingAs($user)
            ->test(InboxRow::class, ['testimonialId' => $row->id])
            ->call('startEdit')
            ->set('editTestimonial', $body)
            ->call('saveEdit')
            ->assertSeeHtml('data-testid="row-flash"');
    }

    // No row's text was overwritten by any of the bad inputs.
    expect($row->fresh()->testimonial)->toBe('Original');
});

test('soft-delete moves a row to Trash (it disappears from main filters and appears in trash)', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    $row = Testimonial::factory()->for($space)->create();

    Livewire::actingAs($user)
        ->test(InboxRow::class, ['testimonialId' => $row->id])
        ->call('softDelete');

    // SoftDeletes: Testimonial::find excludes the row.
    expect(Testimonial::find($row->id))->toBeNull();
    // withTrashed finds it.
    $trashed = Testimonial::withTrashed()->find($row->id);
    expect($trashed->deleted_at)->not->toBeNull();
});

test('row component re-renders after every action', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    $row = Testimonial::factory()->for($space)->create(['is_favorite' => false]);

    Livewire::actingAs($user)
        ->test(InboxRow::class, ['testimonialId' => $row->id])
        ->call('toggleFavorite')
        ->assertSet('testimonial.is_favorite', true);
});

test('bulk wall of love is BLOCKED when the selection includes any unconsented row', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();

    $ok = Testimonial::factory()->for($space)->create(['name' => 'OK-row', 'is_wall_of_love' => false]);
    $no = Testimonial::factory()->for($space)->noConsent()->create(['name' => 'No-row', 'is_wall_of_love' => false]);

    Livewire::actingAs($user)
        ->test(InboxIndex::class, ['spaceId' => $space->id])
        ->call('bulkWallOfLove', [$ok->id, $no->id])
        ->assertSeeHtml('data-testid="inbox-error"');

    // Both rows stay at is_wall_of_love=false — the action was blocked
    // for the whole selection, not silently skipped on the unconsented one.
    expect((bool) $ok->fresh()->is_wall_of_love)->toBeFalse();
    expect((bool) $no->fresh()->is_wall_of_love)->toBeFalse();
});

test('bulk wall of love applies when the whole selection is consented', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    $a = Testimonial::factory()->for($space)->create(['is_wall_of_love' => false]);
    $b = Testimonial::factory()->for($space)->create(['is_wall_of_love' => false]);

    Livewire::actingAs($user)
        ->test(InboxIndex::class, ['spaceId' => $space->id])
        ->call('bulkWallOfLove', [$a->id, $b->id]);

    expect((bool) $a->fresh()->is_wall_of_love)->toBeTrue();
    expect((bool) $b->fresh()->is_wall_of_love)->toBeTrue();
});
