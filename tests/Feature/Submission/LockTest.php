<?php

use App\Livewire\PublicSubmission;
use App\Models\Space;
use App\Models\Testimonial;
use App\Support\SubmissionService;
use Illuminate\Http\Request;

/*
 * Lock at cap. The exact copy "This Space is not accepting responses
 * right now." is shown on GET and rejected on submit. Soft-deleted
 * rows do NOT count toward the cap.
 */

test('GET /s/{slug} shows the locked message when at cap', function () {
    $space = Space::factory()->create(['rating_enabled' => false]);
    // Fill to cap with 100 LIVE rows
    Testimonial::factory()->for($space)->count(100)->create();

    $response = $this->get("/s/{$space->slug}");
    $response->assertOk();
    $response->assertSee('This Space is not accepting responses right now.', false);
});

test('submit at cap silently no-ops with the thank-you page', function () {
    $space = Space::factory()->create(['rating_enabled' => false]);
    Testimonial::factory()->for($space)->count(100)->create();

    $svc = app(SubmissionService::class);
    $request = Request::create('/s/x', 'POST', [], [], [], ['REMOTE_ADDR' => '203.0.113.5']);
    $request->setLaravelSession(app('session.store'));

    $result = $svc->store($space, [
        'name' => 'A', 'email' => 'a@example.test', 'address' => '1 St',
        'testimonial' => 'too late, the space is full.',
    ], $request);

    expect($result['ok'])->toBeFalse();
    expect($result['reason'])->toBe('locked');
    expect(Testimonial::where('space_id', $space->id)->count())->toBe(100);
});

test('soft-deleted rows do NOT count toward the cap', function () {
    $space = Space::factory()->create(['rating_enabled' => false]);
    Testimonial::factory()->for($space)->count(99)->create();
    // 100th is soft-deleted — should not count.
    Testimonial::factory()->for($space)->create([
        'deleted_at' => now(),
    ]);

    expect($space->testimonials()->count())->toBe(99);

    // The next submit should be allowed.
    Livewire::test(PublicSubmission::class, ['slug' => $space->slug])
        ->set('name', 'A')
        ->set('email', 'a@example.test')
        ->set('address', '1 St')
        ->set('testimonial', 'great product, would buy again.')
        ->call('submit')
        ->assertHasNoErrors();

    expect($space->testimonials()->count())->toBe(100);
});
