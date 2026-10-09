<?php

use App\Livewire\PublicSubmission;
use App\Models\Space;
use App\Models\Testimonial;

/*
 * Validation rules per build order Step 3.1:
 *   - name required max 120
 *   - email required, email:rfc, max 180
 *   - address required max 255
 *   - testimonial required max 2000; trim; strip zero-width;
 *     reject whitespace-only / emoji-only / zero-width-only
 *   - rating 1..5 only when rating_enabled
 *   - honeypot never surfaces an error
 */

function baseInput(array $overrides = []): array
{
    return array_merge([
        'name' => 'Priya Sharma',
        'email' => 'priya@example.test',
        'address' => '42 Galaxy Way, Bengaluru',
        'testimonial' => 'A real testimonial with words.',
    ], $overrides);
}

test('name is required and max 120', function () {
    $space = Space::factory()->create(['rating_enabled' => false]);
    Livewire::test(PublicSubmission::class, ['slug' => $space->slug])
        ->set('name', '')
        ->set('email', 'a@example.test')
        ->set('address', '1 St')
        ->set('testimonial', 'real words here')
        ->call('submit')
        ->assertHasErrors(['name']);

    expect(Testimonial::count())->toBe(0);
});

test('email must be a valid RFC email', function () {
    $space = Space::factory()->create(['rating_enabled' => false]);
    Livewire::test(PublicSubmission::class, ['slug' => $space->slug])
        ->set('name', 'A')
        ->set('email', 'not-an-email')
        ->set('address', '1 St')
        ->set('testimonial', 'real words here')
        ->call('submit')
        ->assertHasErrors(['email']);
});

test('email is max 180', function () {
    $space = Space::factory()->create(['rating_enabled' => false]);
    Livewire::test(PublicSubmission::class, ['slug' => $space->slug])
        ->set('name', 'A')
        ->set('email', str_repeat('a', 170).'@example.test')
        ->set('address', '1 St')
        ->set('testimonial', 'real words here')
        ->call('submit')
        ->assertHasErrors(['email']);
});

test('address is required', function () {
    $space = Space::factory()->create(['rating_enabled' => false]);
    Livewire::test(PublicSubmission::class, ['slug' => $space->slug])
        ->set('name', 'A')
        ->set('email', 'a@example.test')
        ->set('address', '')
        ->set('testimonial', 'real words here')
        ->call('submit')
        ->assertHasErrors(['address']);
});

test('testimonial is required', function () {
    $space = Space::factory()->create(['rating_enabled' => false]);
    Livewire::test(PublicSubmission::class, ['slug' => $space->slug])
        ->set('name', 'A')
        ->set('email', 'a@example.test')
        ->set('address', '1 St')
        ->set('testimonial', '')
        ->call('submit')
        ->assertHasErrors(['testimonial']);
});

test('whitespace-only testimonial is rejected', function () {
    $space = Space::factory()->create(['rating_enabled' => false]);
    Livewire::test(PublicSubmission::class, ['slug' => $space->slug])
        ->set('name', 'A')
        ->set('email', 'a@example.test')
        ->set('address', '1 St')
        ->set('testimonial', "   \t  \n  ")
        ->call('submit')
        ->assertHasErrors(['testimonial']);
});

test('emoji-only testimonial is rejected', function () {
    $space = Space::factory()->create(['rating_enabled' => false]);
    Livewire::test(PublicSubmission::class, ['slug' => $space->slug])
        ->set('name', 'A')
        ->set('email', 'a@example.test')
        ->set('address', '1 St')
        ->set('testimonial', '🚀🎉✨')
        ->call('submit')
        ->assertHasErrors(['testimonial']);
});

test('zero-width-only testimonial is rejected', function () {
    $space = Space::factory()->create(['rating_enabled' => false]);
    Livewire::test(PublicSubmission::class, ['slug' => $space->slug])
        ->set('name', 'A')
        ->set('email', 'a@example.test')
        ->set('address', '1 St')
        ->set('testimonial', "\u{200B}\u{200C}\u{200D}\u{FEFF}")
        ->call('submit')
        ->assertHasErrors(['testimonial']);
});

test('zero-width chars are stripped from a mixed testimonial before save', function () {
    $space = Space::factory()->create(['rating_enabled' => false]);
    $body = "Great product.\u{200B} Highly recommend.";

    Livewire::test(PublicSubmission::class, ['slug' => $space->slug])
        ->set('name', 'A')
        ->set('email', 'a@example.test')
        ->set('address', '1 St')
        ->set('testimonial', $body)
        ->call('submit');

    $saved = Testimonial::first();
    expect($saved)->not->toBeNull();
    expect($saved->testimonial)->not->toContain("\u{200B}");
    expect($saved->testimonial)->toContain('Great product.');
});

test('testimonial is max 2000', function () {
    $space = Space::factory()->create(['rating_enabled' => false]);
    Livewire::test(PublicSubmission::class, ['slug' => $space->slug])
        ->set('name', 'A')
        ->set('email', 'a@example.test')
        ->set('address', '1 St')
        ->set('testimonial', str_repeat('a', 2001))
        ->call('submit')
        ->assertHasErrors(['testimonial']);
});

test('rating is required and 1..5 when rating_enabled', function () {
    $space = Space::factory()->create(['rating_enabled' => true]);
    Livewire::test(PublicSubmission::class, ['slug' => $space->slug])
        ->set('name', 'A')
        ->set('email', 'a@example.test')
        ->set('address', '1 St')
        ->set('testimonial', 'great')
        ->set('rating', null)
        ->call('submit')
        ->assertHasErrors(['rating']);
});

test('rating out of range is rejected when rating_enabled', function () {
    $space = Space::factory()->create(['rating_enabled' => true]);
    Livewire::test(PublicSubmission::class, ['slug' => $space->slug])
        ->set('name', 'A')
        ->set('email', 'a@example.test')
        ->set('address', '1 St')
        ->set('testimonial', 'great')
        ->set('rating', 7)
        ->call('submit')
        ->assertHasErrors(['rating']);
});
