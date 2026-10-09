<?php

use App\Livewire\PublicSubmission;
use App\Models\Space;
use App\Models\Testimonial;
use App\Support\SubmissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/*
 * Dedupe. Same email + same testimonial text + same Space within
 * 5 minutes → thank-you only, no row, no cap counted.
 */

function serviceReq(string $ip = '203.0.113.50', array $post = []): Request
{
    $r = Request::create('/s/x', 'POST', $post, [], [], ['REMOTE_ADDR' => $ip]);
    $r->setLaravelSession(app('session.store'));

    return $r;
}

test('a duplicate within 5 minutes is silently dropped (no row, no count)', function () {
    $space = Space::factory()->create(['rating_enabled' => false]);
    $svc = app(SubmissionService::class);

    $payload = [
        'name' => 'Priya',
        'email' => 'priya@example.test',
        'address' => '1 St',
        'testimonial' => 'This is a great product, I really enjoyed it.',
    ];

    $first = $svc->store($space, $payload, serviceReq());
    expect($first['ok'])->toBeTrue();
    expect(Testimonial::count())->toBe(1);

    $second = $svc->store($space, $payload, serviceReq());
    expect($second['ok'])->toBeFalse();
    expect($second['reason'])->toBe('duplicate');
    expect(Testimonial::count())->toBe(1);
    expect($space->testimonials()->count())->toBe(1); // cap not counted
});

test('a different testimonial for the same email is allowed (no false-positive dedupe)', function () {
    $space = Space::factory()->create(['rating_enabled' => false]);
    $svc = app(SubmissionService::class);

    $svc->store($space, [
        'name' => 'A', 'email' => 'a@example.test', 'address' => '1 St',
        'testimonial' => 'first text, lots of words.',
    ], serviceReq());

    $second = $svc->store($space, [
        'name' => 'A', 'email' => 'a@example.test', 'address' => '1 St',
        'testimonial' => 'second text, completely different words here.',
    ], serviceReq());

    expect($second['ok'])->toBeTrue();
    expect(Testimonial::count())->toBe(2);
});

test('a duplicate in a different Space is allowed (dedupe is per-Space)', function () {
    $space1 = Space::factory()->create(['rating_enabled' => false]);
    $space2 = Space::factory()->create(['rating_enabled' => false]);
    $svc = app(SubmissionService::class);

    $payload = [
        'name' => 'A', 'email' => 'a@example.test', 'address' => '1 St',
        'testimonial' => 'same words, same email, different space.',
    ];

    $svc->store($space1, $payload, serviceReq());
    $result = $svc->store($space2, $payload, serviceReq());

    expect($result['ok'])->toBeTrue();
    expect(Testimonial::count())->toBe(2);
});

test('after 5 minutes the same submission is allowed (window expired)', function () {
    $space = Space::factory()->create(['rating_enabled' => false]);
    $svc = app(SubmissionService::class);

    $payload = [
        'name' => 'A', 'email' => 'a@example.test', 'address' => '1 St',
        'testimonial' => 'text that would normally be deduped shortly.',
    ];

    $svc->store($space, $payload, serviceReq());

    // Backdate the first row 6 minutes so the 5-min window has expired.
    DB::table('testimonials')
        ->where('space_id', $space->id)
        ->update(['created_at' => now()->subMinutes(6)]);

    $second = $svc->store($space, $payload, serviceReq());
    expect($second['ok'])->toBeTrue();
    expect(Testimonial::count())->toBe(2);
});
