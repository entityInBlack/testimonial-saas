<?php

use App\Livewire\PublicSubmission;
use App\Models\Space;
use App\Models\Testimonial;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/*
 * The happy path. Anonymous submit works, a row is created with the
 * consent fields honoured, and a rotating confirmation message is
 * returned. New rows have the four `is_*` flags at their false
 * defaults so `is_public` is 0 until the owner promotes them.
 */

test('anonymous submit saves a testimonial row with is_public computed by the DB', function () {
    $space = Space::factory()->create([
        'rating_enabled' => false,
    ]);

    Livewire::test(PublicSubmission::class, ['slug' => $space->slug])
        ->set('name', 'Priya Sharma')
        ->set('email', 'priya@example.test')
        ->set('address', '42 Galaxy Way, Bengaluru')
        ->set('testimonial', 'This is a great product, highly recommend.')
        ->call('submit')
        ->assertSet('submitted', true)
        ->assertSet('thanksMessage', fn ($v) => is_string($v) && $v !== '');

    expect(Testimonial::where('space_id', $space->id)->count())->toBe(1);

    $row = DB::table('testimonials')->where('space_id', $space->id)->first();
    expect($row->name)->toBe('Priya Sharma');
    expect($row->consent_given)->toBe(0);
    expect($row->is_wall_of_love)->toBe(0);
    expect($row->is_hidden)->toBe(0);

    // is_public is a STORED generated column. Without consent+WoL it
    // must be 0, read from the database (NOT the in-memory model).
    expect((int) DB::table('testimonials')->where('id', $row->id)->value('is_public'))->toBe(0);
});

test('the rotating thanks message comes from config(messages.submission_thanks)', function () {
    $space = Space::factory()->create(['rating_enabled' => false]);
    $messages = (array) config('messages.submission_thanks');
    expect($messages)->not->toBeEmpty();

    Livewire::test(PublicSubmission::class, ['slug' => $space->slug])
        ->set('name', 'A')
        ->set('email', 'a@example.test')
        ->set('address', '1 St')
        ->set('testimonial', 'A great testimonial, very nice.')
        ->call('submit')
        ->assertSet('thanksMessage', fn ($v) => in_array($v, $messages, true));
});
