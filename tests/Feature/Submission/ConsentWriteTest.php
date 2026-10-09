<?php

use App\Livewire\PublicSubmission;
use App\Models\Space;
use Illuminate\Support\Facades\DB;

/*
 * Consent write. The audit fields are set ONLY when consent is given.
 * An unconsented row must have is_public=0 via the STORED column.
 */

test('with consent_given=1, consented_at and consent_text_version are set', function () {
    $space = Space::factory()->create(['rating_enabled' => false]);

    Livewire::test(PublicSubmission::class, ['slug' => $space->slug])
        ->set('name', 'Priya')
        ->set('email', 'p@example.test')
        ->set('address', '1 St')
        ->set('testimonial', 'I loved it, would buy again.')
        ->set('consentGiven', true)
        ->call('submit')
        ->assertHasNoErrors();

    $row = DB::table('testimonials')->where('space_id', $space->id)->first();

    expect($row->consent_given)->toBe(1);
    expect($row->consented_at)->not->toBeNull();
    expect($row->consent_text_version)->toBe(config('consent.current'));
});

test('with consent_given=0, consented_at and consent_text_version are null', function () {
    $space = Space::factory()->create(['rating_enabled' => false]);

    Livewire::test(PublicSubmission::class, ['slug' => $space->slug])
        ->set('name', 'Tom')
        ->set('email', 't@example.test')
        ->set('address', '1 St')
        ->set('testimonial', 'Fine product overall, decent quality.')
        ->set('consentGiven', false)
        ->call('submit')
        ->assertHasNoErrors();

    $row = DB::table('testimonials')->where('space_id', $space->id)->first();

    expect($row->consent_given)->toBe(0);
    expect($row->consented_at)->toBeNull();
    expect($row->consent_text_version)->toBeNull();
});

test('an unconsented row has is_public=0 read from the DB (STORED column)', function () {
    $space = Space::factory()->create(['rating_enabled' => false]);

    Livewire::test(PublicSubmission::class, ['slug' => $space->slug])
        ->set('name', 'Sara')
        ->set('email', 's@example.test')
        ->set('address', '1 St')
        ->set('testimonial', 'Did not consent, but here is feedback.')
        ->set('consentGiven', false)
        ->call('submit');

    $row = DB::table('testimonials')->where('space_id', $space->id)->first();

    expect((int) DB::table('testimonials')->where('id', $row->id)->value('is_public'))->toBe(0);
});

test('submitted_at is set on every saved row', function () {
    $space = Space::factory()->create(['rating_enabled' => false]);

    Livewire::test(PublicSubmission::class, ['slug' => $space->slug])
        ->set('name', 'A')
        ->set('email', 'a@example.test')
        ->set('address', '1 St')
        ->set('testimonial', 'great product overall, thanks.')
        ->call('submit');

    $row = DB::table('testimonials')->where('space_id', $space->id)->first();
    expect($row->submitted_at)->not->toBeNull();
});
