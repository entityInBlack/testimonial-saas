<?php

use App\Livewire\Privacy\DeletionRequestForm;
use App\Models\DeletionRequest;
use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;
use Livewire\Livewire;

/**
 * Part C — `/privacy/request-deletion` form (Step 8 / task 8.3).
 *
 * The form is a Livewire component. Each test below exercises the
 * REAL HTTP path: it builds a Livewire snapshot via the testable's
 * InitialRender (which does NOT disable exception handling — see
 * tests/Feature/Inbox/InboxAuthorizationHttpTest for the rationale),
 * then POSTs a `/livewire/update` payload with the `submit` method
 * call. That gives a true end-to-end test of the throttle, the
 * honeypot, the validation, the withTrashed() lookup, and the row
 * write.
 *
 * The submit/render plumbing lives in the Pest global functions at
 * the bottom of this file: `submitForm($payload)` and
 * `rawSubmitForm($payload)`. They build a real Livewire snapshot
 * of `DeletionRequestForm` and POST it to /livewire/update. Use
 * `submitForm()` when you only care about side effects (row
 * count, model state) and `rawSubmitForm()` when you also need
 * the HTTP response object.
 *
 * The literal "Thanks — your request has been recorded." heading
 * is asserted via the HTML payload of the JSON envelope (the
 * `effects.html` field of the Livewire response).
 */

beforeEach(function () {
    // Reset the named limiter between tests so the 5-per-hour cap
    // does not bleed across tests (each test runs in a fresh
    // RefreshDatabase transaction but the rate-limit cache lives in
    // a separate store).
    $ip = request()->ip() ?: '127.0.0.1';
    foreach (['127.0.0.1', '::1', $ip] as $candidate) {
        \Illuminate\Support\Facades\RateLimiter::clear('deletion-request|ip|'.$candidate);
    }
});

/**
 * The exact thank-you heading text the form renders for EVERY
 * positive outcome. All "nothing-revealed" responses, the throttle
 * response, and the post-validation pass all contain this string
 * byte-for-byte. The control (missing email) MUST NOT contain it.
 */
const THANKS_HEADING = 'Thanks — your request has been recorded.';

/**
 * Pull the rendered HTML out of a Livewire /livewire/update JSON
 * envelope. The envelope has a `components[].effects.html` field;
 * if it is absent we fall back to the raw body so string-contains
 * checks still work.
 */
function extractHtml(\Illuminate\Testing\TestResponse $resp): string
{
    $payload = $resp->json();
    $html = '';
    if (is_array($payload) && isset($payload['components']) && is_array($payload['components'])) {
        foreach ($payload['components'] as $component) {
            if (isset($component['effects']['html']) && is_string($component['effects']['html'])) {
                $html .= $component['effects']['html'];
            }
        }
    }
    // Fall back to the raw body so any escaped JSON string is also
    // searchable.
    $raw = (string) $resp->getContent();

    return $html !== '' ? $html : $raw;
}

test('valid slug + testimonial that belongs to it writes one row with both ids and the typed slug', function () {
    $owner = User::factory()->create();
    $space = Space::factory()->for($owner)->create(['slug' => 'valid-slug-1']);
    $row = Testimonial::factory()->for($space)->create([
        'email' => 't@example.test',
        'consent_given' => true,
        'is_wall_of_love' => true,
    ]);

    submitForm([
        'email' => 't@example.test',
        'spaceSlug' => 'valid-slug-1',
        'testimonialId' => (string) $row->id,
    ]);

    expect(DeletionRequest::count())->toBe(1);

    $r = DeletionRequest::first();
    expect((int) $r->space_id)->toBe((int) $space->id)
        ->and((int) $r->testimonial_id)->toBe((int) $row->id)
        ->and($r->space_slug)->toBe('valid-slug-1')
        ->and($r->email)->toBe('t@example.test')
        ->and($r->status)->toBe(DeletionRequest::STATUS_OPEN)
        ->and($r->acted_at)->toBeNull();
});

test('valid slug + testimonial_id belonging to ANOTHER Space writes one row with testimonial_id NULL', function () {
    $owner = User::factory()->create();
    $spaceA = Space::factory()->for($owner)->create(['slug' => 'space-a']);
    $spaceB = Space::factory()->for($owner)->create(['slug' => 'space-b']);
    $rowA = Testimonial::factory()->for($spaceA)->create();
    // The form says slug=space-b but the testimonial id belongs to space-a.
    submitForm([
        'email' => 't@example.test',
        'spaceSlug' => 'space-b',
        'testimonialId' => (string) $rowA->id,
    ]);

    expect(DeletionRequest::count())->toBe(1);

    $r = DeletionRequest::first();
    expect((int) $r->space_id)->toBe((int) $spaceB->id)
        ->and($r->testimonial_id)->toBeNull()
        ->and($r->space_slug)->toBe('space-b');
});

test('soft-deleted Space slug: row has space_id of that Space (withTrashed proof)', function () {
    $owner = User::factory()->create();
    $space = Space::factory()->for($owner)->create(['slug' => 'soft-deleted-slug']);
    $space->delete();

    submitForm([
        'email' => 't@example.test',
        'spaceSlug' => 'soft-deleted-slug',
    ]);

    expect(DeletionRequest::count())->toBe(1);
    $r = DeletionRequest::first();
    expect((int) $r->space_id)->toBe((int) $space->id)
        ->and($r->space_slug)->toBe('soft-deleted-slug');
});

test('unknown slug: row written with space_id NULL and the typed slug retained', function () {
    submitForm([
        'email' => 't@example.test',
        'spaceSlug' => 'no-such-space',
    ]);

    expect(DeletionRequest::count())->toBe(1);
    $r = DeletionRequest::first();
    expect($r->space_id)->toBeNull()
        ->and($r->space_slug)->toBe('no-such-space');
});

test('honeypot filled: zero rows', function () {
    submitForm([
        'email' => 't@example.test',
        'spaceSlug' => 'any-slug',
        DeletionRequestForm::HONEYPOT_FIELD => 'http://spam.example.test',
    ]);

    expect(DeletionRequest::count())->toBe(0);
});

test('throttle: attempts 1..5 write rows 1..5, attempt 6 shows the thank-you and row count stays 5', function () {
    for ($i = 1; $i <= 5; $i++) {
        submitForm([
            'email' => "t{$i}@example.test",
            'spaceSlug' => 'any-slug',
        ]);
    }
    expect(DeletionRequest::count())->toBe(5);

    // 6th attempt from the same IP. submitForm() uses one HTTP POST
    // per call. We assert the thank-you heading is in the response
    // — the component flips $submitted = true on the over-throttle
    // path, so the response is a 200 with the thank-you view, AND
    // no new row is written.
    $resp6 = rawSubmitForm([
        'email' => 't6@example.test',
        'spaceSlug' => 'any-slug',
    ]);

    expect($resp6->getStatusCode())->toBe(200);
    expect(extractHtml($resp6))->toContain(THANKS_HEADING);
    expect(DeletionRequest::count())->toBe(5);
});

test('NOTHING-REVEALED: scenarios 1, 2, 3, 4, 5 all return the same response and the thank-you heading; the missing-email control differs', function () {
    // Scenario 1: valid live slug + matching testimonial.
    $owner = User::factory()->create();
    $s1 = Space::factory()->for($owner)->create(['slug' => 'sc1']);
    $r1 = Testimonial::factory()->for($s1)->create(['email' => 'p@example.test']);

    // Scenario 2: valid slug + foreign testimonial.
    $s2 = Space::factory()->for($owner)->create(['slug' => 'sc2']);
    $r2foreign = Testimonial::factory()->for($s2)->create();
    $r2other = Testimonial::factory()->for($s1)->create();

    // Scenario 3: soft-deleted Space.
    $s3 = Space::factory()->for($owner)->create(['slug' => 'sc3']);
    $s3->delete();

    // Scenario 4: unknown slug.
    // (no Space created with slug 'sc4-unknown')

    // Scenario 5: honeypot filled.

    $reset = function () {
        // Reset the named limiter so each scenario counts from 0.
        // The named limiter's key shape is `deletion-request|ip|{ip}`
        // (see AppServiceProvider). The Livewire component resolves
        // the key via RateLimiter::limiter('deletion-request') so
        // this string MUST match the named limiter's `by(...)`
        // expression. We try the loopback addresses plus the
        // current request IP to be safe across platforms.
        $ip = request()->ip() ?: '127.0.0.1';
        foreach (['127.0.0.1', '::1', $ip] as $candidate) {
            \Illuminate\Support\Facades\RateLimiter::clear('deletion-request|ip|'.$candidate);
        }
    };

    // Scenario 1
    $reset();
    $resp1 = rawSubmitForm([
        'email' => 'p@example.test',
        'spaceSlug' => 'sc1',
        'testimonialId' => (string) $r1->id,
    ]);
    expect(DeletionRequest::count())->toBe(1);

    // Scenario 2
    $reset();
    $resp2 = rawSubmitForm([
        'email' => 'p@example.test',
        'spaceSlug' => 'sc2',
        'testimonialId' => (string) $r2other->id, // foreign to sc2
    ]);
    expect(DeletionRequest::count())->toBe(2);

    // Scenario 3
    $reset();
    $resp3 = rawSubmitForm([
        'email' => 'p@example.test',
        'spaceSlug' => 'sc3',
    ]);
    expect(DeletionRequest::count())->toBe(3);

    // Scenario 4 (unknown slug)
    $reset();
    $resp4 = rawSubmitForm([
        'email' => 'p@example.test',
        'spaceSlug' => 'sc4-unknown',
    ]);
    expect(DeletionRequest::count())->toBe(4);

    // Scenario 5 (honeypot)
    $reset();
    $resp5 = rawSubmitForm([
        'email' => 'p@example.test',
        'spaceSlug' => 'sc1',
        DeletionRequestForm::HONEYPOT_FIELD => 'http://spam.example.test',
    ]);
    // Honeypot does NOT write a row.
    expect(DeletionRequest::count())->toBe(4);

    // All five status codes must be 200.
    expect($resp1->getStatusCode())->toBe(200);
    expect($resp2->getStatusCode())->toBe(200);
    expect($resp3->getStatusCode())->toBe(200);
    expect($resp4->getStatusCode())->toBe(200);
    expect($resp5->getStatusCode())->toBe(200);

    // All five responses must contain the exact thank-you heading.
    $h1 = extractHtml($resp1);
    $h2 = extractHtml($resp2);
    $h3 = extractHtml($resp3);
    $h4 = extractHtml($resp4);
    $h5 = extractHtml($resp5);
    expect($h1)->toContain(THANKS_HEADING);
    expect($h2)->toContain(THANKS_HEADING);
    expect($h3)->toContain(THANKS_HEADING);
    expect($h4)->toContain(THANKS_HEADING);
    expect($h5)->toContain(THANKS_HEADING);

    // And the bodies must be byte-equal (the normalize step is
    // still needed because Livewire per-mount snapshot fields
    // differ between calls). If any one differs, the form would
    // be revealing something.
    $normalize = function (string $body): string {
        $decoded = json_decode($body, true);
        if (!is_array($decoded) || !isset($decoded['components']) || !is_array($decoded['components'])) {
            return $body;
        }
        foreach ($decoded['components'] as $i => $component) {
            if (isset($component['snapshot'])) {
                $snap = is_string($component['snapshot'])
                    ? json_decode($component['snapshot'], true)
                    : $component['snapshot'];
                if (is_array($snap) && isset($snap['memo']) && is_array($snap['memo'])) {
                    $snap['memo']['id'] = 'WID';
                    $snap['memo']['path'] = 'PATH';
                    $snap['checksum'] = 'CHK';
                    $decoded['components'][$i]['snapshot'] = json_encode($snap);
                }
            }
            if (isset($component['effects']['html']) && is_string($component['effects']['html'])) {
                $decoded['components'][$i]['effects']['html'] = preg_replace(
                    '/wire:id="[A-Za-z0-9]+"/',
                    'wire:id="WID"',
                    $component['effects']['html'],
                );
            }
        }

        $encoded = json_encode($decoded, JSON_UNESCAPED_SLASHES);

        return $encoded ?: $body;
    };

    $b1 = $normalize((string) $resp1->getContent());
    $b2 = $normalize((string) $resp2->getContent());
    $b3 = $normalize((string) $resp3->getContent());
    $b4 = $normalize((string) $resp4->getContent());
    $b5 = $normalize((string) $resp5->getContent());

    $hashed1 = md5($b1);
    expect(md5($b2))->toBe($hashed1, "sc2 body differs from sc1");
    expect(md5($b3))->toBe($hashed1, "sc3 body differs from sc1");
    expect(md5($b4))->toBe($hashed1, "sc4 body differs from sc1");
    expect(md5($b5))->toBe($hashed1, "sc5 (honeypot) body differs from sc1");

    // CONTROL — submission with a missing email MUST differ. It
    // must NOT contain the thank-you heading (the form stays
    // visible with field-level errors), and it MUST contain the
    // email-required message. The Livewire error envelope escapes
    // the message with backslash-double-quote (e.g.
    //   "errors":{"email":["Email is required."]}
    // appears in the raw body as
    //   \"errors\":{\"email\":[\"Email is required.\"]}
    // so we look for the unescaped form in the decoded HTML and
    // the escaped form in the raw body — the union proves the
    // error was rendered AND present in the response.
    $reset();
    $respBad = rawSubmitForm([
        'email' => '',
        'spaceSlug' => 'sc1',
    ]);
    $badHtml = extractHtml($respBad);
    $badBody = (string) $respBad->getContent();
    expect($badHtml)->not->toContain(THANKS_HEADING);
    expect($badHtml)->toContain('Email is required.');
    expect($badBody)->toContain('Email is required.');
});

test('validation: missing email, malformed email, email of 181 chars, slug of 61 chars: field errors and zero rows', function () {
    $reset = function () {
        $ip = request()->ip() ?: '127.0.0.1';
        foreach (['127.0.0.1', '::1', $ip] as $candidate) {
            \Illuminate\Support\Facades\RateLimiter::clear('deletion-request|ip|'.$candidate);
        }
    };

    // 1. Missing email -> "Email is required."
    $reset();
    $r = rawSubmitForm(['email' => '', 'spaceSlug' => 'any']);
    $r->assertStatus(200); // 200 with field errors
    expect(extractHtml($r))->toContain('Email is required.');
    expect(DeletionRequest::count())->toBe(0);

    // 2. Malformed email -> "Please enter a valid email address."
    $reset();
    $r = rawSubmitForm(['email' => 'not-an-email', 'spaceSlug' => 'any']);
    expect(extractHtml($r))->toContain('Please enter a valid email address.');
    expect(DeletionRequest::count())->toBe(0);

    // 3. Email of 181 chars -> "Email is too long."
    // 170 a's + '@x.test' = 177, plus 4 x's = 181.
    $longEmail = str_repeat('a', 170).'@x.test'.str_repeat('x', 4);
    expect(strlen($longEmail))->toBe(181);
    $reset();
    $r = rawSubmitForm(['email' => $longEmail, 'spaceSlug' => 'any']);
    expect(extractHtml($r))->toContain('Email is too long.');
    expect(DeletionRequest::count())->toBe(0);

    // 4. Slug of 61 chars -> "Space slug is too long."
    $longSlug = str_repeat('s', 61);
    $reset();
    $r = rawSubmitForm(['email' => 'ok@example.test', 'spaceSlug' => $longSlug]);
    expect(extractHtml($r))->toContain('Space slug is too long.');
    expect(DeletionRequest::count())->toBe(0);
});

/**
 * Submit the deletion-request form via the real Livewire
 * /livewire/update HTTP endpoint and discard the response. Use
 * this when you only care about side effects (row count, model
 * state). For asserting on the HTTP response, use
 * `rawSubmitForm()` instead.
 */
function submitForm(array $payload): void
{
    rawSubmitForm($payload);
}

/**
 * Submit the deletion-request form via the real Livewire
 * /livewire/update HTTP endpoint. Returns the live HTTP response
 * so the caller can assert on status / body / redirect.
 *
 * The Livewire component serializes its public properties into a
 * snapshot, and `submit()` is invoked through /livewire/update.
 * The shape of the payload matches what the front-controller
 * expects (see InboxAuthorizationHttpTest for the same pattern).
 */
function rawSubmitForm(array $payload): \Illuminate\Testing\TestResponse
{
    /** @var \Tests\TestCase $test */
    $test = test();

    $mounted = Livewire::test(DeletionRequestForm::class);

    if (array_key_exists('email', $payload)) {
        $mounted->set('email', (string) $payload['email']);
    }
    if (array_key_exists('spaceSlug', $payload)) {
        $mounted->set('spaceSlug', (string) $payload['spaceSlug']);
    }
    if (array_key_exists('testimonialId', $payload) && $payload['testimonialId'] !== null) {
        $mounted->set('testimonialId', (string) $payload['testimonialId']);
    }
    if (array_key_exists(DeletionRequestForm::HONEYPOT_FIELD, $payload)) {
        $mounted->set(DeletionRequestForm::HONEYPOT_FIELD, (string) $payload[DeletionRequestForm::HONEYPOT_FIELD]);
    }

    // Reach into the testable's lastState to extract the snapshot.
    $reflection = new ReflectionObject($mounted);
    $lastStateProp = $reflection->getProperty('lastState');
    $lastStateProp->setAccessible(true);
    $lastState = $lastStateProp->getValue($mounted);
    $snapshot = $lastState->getSnapshot();

    $payloadJson = [
        'components' => [
            [
                'snapshot' => json_encode($snapshot),
                'calls' => [
                    [
                        'path' => '',
                        'method' => 'submit',
                        'params' => [],
                    ],
                ],
                'updates' => [],
            ],
        ],
    ];

    return $test->withHeaders(['X-Livewire' => '1'])
        ->post('/livewire/update', $payloadJson);
}
