<?php

use App\Livewire\Inbox\InboxIndex;
use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;
use Livewire\Livewire;

/**
 * Step 4.9: Inbox card display.
 *
 * The card shows: name, email, address, company_name (if set), photo
 * (if set), rating, testimonial text, submitted_at, and a consent
 * badge.
 *
 * Consent badge has FOUR states, distinguishable from the schema:
 *   - 'live'        : consent_given=1, is_wall_of_love=1
 *   - 'consented'   : consent_given=1, is_wall_of_love=0
 *   - 'withdrawn'   : consent_given=0, consented_at NOT NULL (audit
 *                     history proves the user once consented)
 *   - 'none'        : consent_given=0, consented_at IS NULL (never
 *                     consented)
 *
 * Step 4.9 also calls out the hidden flag — hidden items must show a
 * "Hidden" chip in the row.
 */

test('card renders name, email, address, rating, testimonial text, submitted_at', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    $when = now()->subHour();
    Testimonial::factory()->for($space)->create([
        'name' => 'Sara Nkemi',
        'email' => 'sara@example.test',
        'address' => '42 Garden Lane, Bristol',
        'testimonial' => 'Brilliant service!',
        'rating' => 5,
        'submitted_at' => $when,
    ]);

    Livewire::actingAs($user)
        ->test(InboxIndex::class, ['spaceId' => $space->id])
        ->assertSee('Sara Nkemi')
        ->assertSee('sara@example.test')
        ->assertSee('42 Garden Lane, Bristol')
        ->assertSee('Brilliant service!')
        ->assertSee('5/5')
        ->assertSee($when->format('Y-m-d H:i'));
});

test('card renders company_name when set', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    Testimonial::factory()->for($space)->create(['company_name' => 'Acme Co.']);
    Testimonial::factory()->for($space)->create(['company_name' => null, 'name' => 'NoCo']);

    Livewire::actingAs($user)
        ->test(InboxIndex::class, ['spaceId' => $space->id])
        ->assertSee('Acme Co.');
});

test('card renders the photo when profile_photo is set', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    Testimonial::factory()->for($space)->create([
        'profile_photo' => 'photos/abc.webp',
        'name' => 'Has-Photo',
    ]);

    Livewire::actingAs($user)
        ->test(InboxIndex::class, ['spaceId' => $space->id])
        ->assertSeeHtml('data-testid="row-photo"');
});

test('card omits the photo when profile_photo is null', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    Testimonial::factory()->for($space)->create([
        'profile_photo' => null,
        'name' => 'No-Photo',
    ]);

    Livewire::actingAs($user)
        ->test(InboxIndex::class, ['spaceId' => $space->id])
        ->assertDontSeeHtml('data-testid="row-photo"');
});

test('consent badge distinguishes live vs consented vs withdrawn vs never-consented', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();

    // live: consent=1, WoL=1
    Testimonial::factory()->for($space)->create([
        'name' => 'LiveRow',
        'consent_given' => true,
        'is_wall_of_love' => true,
    ]);

    // consented: consent=1, WoL=0
    Testimonial::factory()->for($space)->create([
        'name' => 'ConsentedRow',
        'consent_given' => true,
        'is_wall_of_love' => false,
    ]);

    // withdrawn: consent=0, consented_at NOT NULL (audit)
    Testimonial::factory()->for($space)->create([
        'name' => 'WithdrawnRow',
        'consent_given' => false,
        'is_wall_of_love' => false,
        'consented_at' => now()->subDays(7),
        'consent_text_version' => 'v1',
    ]);

    // never consented: consent=0, consented_at NULL
    Testimonial::factory()->for($space)->create([
        'name' => 'NoConsentRow',
        'consent_given' => false,
        'is_wall_of_love' => false,
        'consented_at' => null,
        'consent_text_version' => null,
    ]);

    Livewire::actingAs($user)
        ->test(InboxIndex::class, ['spaceId' => $space->id])
        // All four badge labels are rendered with their data-consent attr.
        ->assertSeeHtml('data-testid="row-consent-badge" data-consent="live"')
        ->assertSeeHtml('data-testid="row-consent-badge" data-consent="consented"')
        ->assertSeeHtml('data-testid="row-consent-badge" data-consent="withdrawn"')
        ->assertSeeHtml('data-testid="row-consent-badge" data-consent="none"');
});

test('hidden items render a Hidden chip in the row', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    Testimonial::factory()->for($space)->hidden()->create(['name' => 'HiddenRow']);

    Livewire::actingAs($user)
        ->test(InboxIndex::class, ['spaceId' => $space->id])
        ->assertSeeHtml('data-testid="row-hidden-chip"')
        ->assertSee('Hidden');
});
