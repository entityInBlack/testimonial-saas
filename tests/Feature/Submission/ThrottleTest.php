<?php

use App\Exceptions\SubmissionThrottledException;
use App\Livewire\PublicSubmission;
use App\Models\Space;
use App\Models\Testimonial;
use App\Support\SubmissionService;
use Illuminate\Http\Request;

/*
 * Per-IP and per-email throttles. 5 per hour per Space, per
 * (request->ip()) AND per (email + ip). The 6th attempt is rejected
 * with a generic message; counters are NOT cleared on success.
 *
 * Direct SubmissionService calls avoid Livewire/CSRF noise and let
 * the test set the IP via $request->server('REMOTE_ADDR').
 */

function serviceRequest(?string $ip = '203.0.113.10', array $files = [], array $post = []): Request
{
    $request = Request::create('/s/x', 'POST', $post, [], $files, ['REMOTE_ADDR' => $ip]);
    $request->setLaravelSession(app('session.store'));

    return $request;
}

test('6th submission from the same IP within an hour is rejected', function () {
    $space = Space::factory()->create(['rating_enabled' => false]);
    $svc = app(SubmissionService::class);

    for ($i = 1; $i <= 5; $i++) {
        $result = $svc->store(
            $space,
            [
                'name' => "User $i",
                'email' => "u$i@example.test",
                'address' => '1 St',
                'testimonial' => 'this is a great product, thanks.',
            ],
            serviceRequest('198.51.100.7')
        );
        expect($result['ok'])->toBeTrue("attempt $i failed: ".json_encode($result));
    }

    // 6th attempt — same IP, different email so we don't trigger dedupe
    $threw = false;
    try {
        $svc->store(
            $space,
            [
                'name' => 'User 6',
                'email' => 'u6@example.test',
                'address' => '1 St',
                'testimonial' => 'yet another great testimonial here.',
            ],
            serviceRequest('198.51.100.7')
        );
    } catch (SubmissionThrottledException $e) {
        $threw = true;
        expect($e->getMessage())->toBe('Too many submissions. Try again later.');
    }
    expect($threw)->toBeTrue();
});

test('6th submission of the same email within an hour is rejected', function () {
    $space = Space::factory()->create(['rating_enabled' => false]);
    $svc = app(SubmissionService::class);

    for ($i = 1; $i <= 5; $i++) {
        // Vary IP so we don't hit the IP key; we want the email key to fill
        $result = $svc->store(
            $space,
            [
                'name' => "U $i",
                'email' => 'spammed@example.test',
                'address' => '1 St',
                // Vary the text to avoid dedupe
                'testimonial' => "text $i ".str_repeat('a', $i),
            ],
            serviceRequest("203.0.113.$i")
        );
        expect($result['ok'])->toBeTrue("attempt $i failed: ".json_encode($result));
    }

    $threw = false;
    try {
        $svc->store(
            $space,
            [
                'name' => 'U6',
                'email' => 'spammed@example.test',
                'address' => '1 St',
                'testimonial' => 'text 6 something new',
            ],
            serviceRequest('203.0.113.99')
        );
    } catch (SubmissionThrottledException) {
        $threw = true;
    }
    expect($threw)->toBeTrue();
});

test('different IPs and emails are not throttled by each other', function () {
    $space = Space::factory()->create(['rating_enabled' => false]);
    $svc = app(SubmissionService::class);

    for ($i = 1; $i <= 5; $i++) {
        $result = $svc->store(
            $space,
            [
                'name' => "U $i",
                'email' => "u$i@example.test",
                'address' => '1 St',
                'testimonial' => 'great product, would recommend.',
            ],
            serviceRequest("192.0.2.$i")
        );
        expect($result['ok'])->toBeTrue("attempt $i failed: ".json_encode($result));
    }
    expect(Testimonial::count())->toBe(5);
});
