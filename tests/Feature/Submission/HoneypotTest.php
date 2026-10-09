<?php

use App\Livewire\PublicSubmission;
use App\Models\Space;
use App\Models\Testimonial;

/*
 * Honeypot. A filled honeypot must silently no-op:
 *   - no row is created
 *   - the user sees the thank-you page (not a validation error)
 *   - the cap is not incremented
 */

test('a filled honeypot silently no-ops — no row, no error, shows thanks', function () {
    $space = Space::factory()->create(['rating_enabled' => false]);

    Livewire::test(PublicSubmission::class, ['slug' => $space->slug])
        ->set('name', 'Bot')
        ->set('email', 'bot@example.test')
        ->set('address', '1 St')
        ->set('testimonial', 'spam content, real words to pass the rule.')
        ->set('website', 'http://spam.example/') // honeypot filled
        ->call('submit')
        ->assertSet('submitted', true)
        ->assertSet('thanksMessage', fn ($v) => is_string($v) && $v !== '')
        ->assertHasNoErrors();

    expect(Testimonial::count())->toBe(0);
    expect($space->testimonials()->count())->toBe(0);
});

test('an empty honeypot lets the submission through normally', function () {
    $space = Space::factory()->create(['rating_enabled' => false]);

    Livewire::test(PublicSubmission::class, ['slug' => $space->slug])
        ->set('name', 'Real user')
        ->set('email', 'real@example.test')
        ->set('address', '1 St')
        ->set('testimonial', 'A genuine testimonial with words.')
        ->set('website', '')
        ->call('submit')
        ->assertSet('submitted', true);

    expect(Testimonial::count())->toBe(1);
});
