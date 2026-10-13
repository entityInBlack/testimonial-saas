<?php

use App\Livewire\Privacy\DeletionRequestForm;
use App\Livewire\PublicSubmission;
use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;
use Livewire\Livewire;

/**
 * Part 1b — visual lift tests for every PUBLIC (guest) page.
 *
 * The whole public side of the product must look like one product
 * (matching landing.blade.php). These tests guard the visible
 * shape of the canvas, the one-h1 invariant, the new icon chips, the
 * privacy-page structure, the contrast, the absence of remote
 * assets, and the reduced-motion fail-safe.
 *
 * Every helper function in this file is prefixed with `gvl` because
 * Pest helpers are global.
 *
 * Each test has a "Would fail if" comment that explains what change
 * would break the assertion. No assertion is allowed to be weakened
 * later to make it pass.
 */

/**
 * Set of "GUEST9" pages the visual-lift tests cover. Each tuple
 * is [path, options]. `authed` marks the two pages that need an
 * authenticated user; `unverified` marks /verify-email.
 */
function gvlGuestPages(): array
{
    return [
        ['/login', []],
        ['/register', []],
        ['/forgot-password', []],
        ['/reset-password/anytoken', []],
        ['/confirm-password', ['authed' => true]],
        ['/verify-email', ['unverified' => true]],
        ['/privacy', []],
        ['/privacy/request-deletion', []],
    ];
}

/**
 * Get the body for a single page, optionally as an authed or
 * unverified user. Authed-only pages (/confirm-password) and
 * unverified-only pages (/verify-email) MUST be requested with
 * the matching user state; for everything else the default
 * guest client is fine.
 */
function gvlBody(string $path, array $options = []): string
{
    return gvlResponse($path, $options)->getContent();
}

/**
 * Same as gvlBody but returns the full TestResponse so callers
 * can assert on the status code.
 */
function gvlResponse(string $path, array $options = []): \Illuminate\Testing\TestResponse
{
    $test = test();

    if (! empty($options['authed'])) {
        $user = User::factory()->create();
        $test = $test->actingAs($user);
    } elseif (! empty($options['unverified'])) {
        $user = User::factory()->unverified()->create();
        $test = $test->actingAs($user);
    }

    return $test->get($path);
}

/**
 * Render the PublicSubmission component, optionally after a valid
 * submit, and return its rendered HTML. Centralised so the thanks
 * test stays one-liner at the call site.
 */
function gvlPublicHtml(Space $space, bool $submit = false): string
{
    $tester = Livewire::test(PublicSubmission::class, ['slug' => $space->slug]);
    if ($submit) {
        $tester->set('name', 'Priya Sharma')
            ->set('email', 'priya@example.test')
            ->set('address', '42 Galaxy Way, Bengaluru')
            ->set('testimonial', 'A great product, highly recommend.')
            ->set('consentGiven', true)
            ->call('submit');
    }

    return (string) $tester->html();
}

/**
 * Submit the DeletionRequestForm via the real /livewire/update
 * endpoint and return the full TestResponse. Mirrors
 * DeletionRequestTest::rawSubmitForm so the test runs through the
 * real HTTP plumbing.
 */
function gvlRawSubmitForm(array $payload): \Illuminate\Testing\TestResponse
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

/**
 * Extract the rendered HTML out of a Livewire /livewire/update
 * JSON envelope. Mirrors DeletionRequestTest::extractHtml.
 */
function gvlExtractHtml(\Illuminate\Testing\TestResponse $resp): string
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
    $raw = (string) $resp->getContent();

    return $html !== '' ? $html : $raw;
}

/**
 * Extract the inner HTML of the <... data-testid="$testid" ...> element.
 * Returns the raw inner content (NOT stripped) so the caller can
 * inspect it for nested tags. Returns an empty string when the
 * testid is not present.
 */
function gvlElementInner(string $body, string $testid): string
{
    $needle = preg_quote('data-testid="'.$testid.'"', '/');
    $patterns = [
        '/<div\b[^>]*'.$needle.'[^>]*>(.*?)<\/div>/is',
        '/<section\b[^>]*'.$needle.'[^>]*>(.*?)<\/section>/is',
        '/<header\b[^>]*'.$needle.'[^>]*>(.*?)<\/header>/is',
        '/<article\b[^>]*'.$needle.'[^>]*>(.*?)<\/article>/is',
        '/<main\b[^>]*'.$needle.'[^>]*>(.*?)<\/main>/is',
        '/<h1\b[^>]*'.$needle.'[^>]*>(.*?)<\/h1>/is',
        '/<p\b[^>]*'.$needle.'[^>]*>(.*?)<\/p>/is',
        '/<span\b[^>]*'.$needle.'[^>]*>(.*?)<\/span>/is',
        '/<ul\b[^>]*'.$needle.'[^>]*>(.*?)<\/ul>/is',
    ];
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $body, $m) === 1) {
            return $m[1];
        }
    }

    return '';
}

test('GVL1: every guest page exposes the canvas exactly once and has no flat gray-100 background', function () {
    // Would fail if: a page is missing the canvas, has two, or still
    // uses the old flat gray-100 (now indigo-50 -> white gradient).
    $pages = [
        ['/login', []],
        ['/register', []],
        ['/forgot-password', []],
        ['/reset-password/anytoken', []],
        ['/confirm-password', ['authed' => true]],
        ['/verify-email', ['unverified' => true]],
        ['/privacy', []],
        ['/privacy/request-deletion', []],
    ];
    $space = Space::factory()->create(['rating_enabled' => false]);
    $pages[] = ['/s/'.$space->slug, []];

    foreach ($pages as [$path, $opts]) {
        $response = gvlResponse($path, $opts);
        $body = (string) $response->getContent();
        $status = $response->getStatusCode();

        expect($status)->toBe(200, "{$path}: expected 200, got {$status}.");
        $canvasCount = substr_count($body, 'data-testid="guest-canvas"');
        expect($canvasCount)->toBe(1, "{$path}: expected exactly 1 guest-canvas, got {$canvasCount}.");
        expect($body)->not->toContain('bg-gray-100');
    }
});

test('GVL2: every guest page has exactly one <h1>', function () {
    // Would fail if: an icon chip is wrapped in a heading (a second
    // h1) or a page loses its h1.
    $pages = [
        ['/login', []],
        ['/register', []],
        ['/forgot-password', []],
        ['/reset-password/anytoken', []],
        ['/confirm-password', ['authed' => true]],
        ['/verify-email', ['unverified' => true]],
        ['/privacy', []],
        ['/privacy/request-deletion', []],
    ];
    $space = Space::factory()->create(['rating_enabled' => false]);
    $pages[] = ['/s/'.$space->slug, []];

    foreach ($pages as [$path, $opts]) {
        $body = gvlBody($path, $opts);
        $count = preg_match_all('/<h1\b/i', $body);
        expect($count)->toBe(1, "{$path}: expected exactly 1 <h1>, got {$count}.");
    }
});

test('GVL3: every auth page has the icon chip before the h1 and the h1 is text-only', function () {
    // Would fail if: the auth icon is missing, duplicated, nested
    // inside the h1, or placed after the heading; or the h1 contains
    // any markup beyond its text.
    $authPages = [
        '/login' => 'Log in',
        '/register' => 'Create account',
        '/forgot-password' => 'Forgot your password',
        '/reset-password/anytoken' => 'Set a new password',
        '/confirm-password' => 'Confirm your password',
        '/verify-email' => 'Verify your email',
    ];
    foreach ($authPages as $path => $expectedHeading) {
        $opts = $path === '/confirm-password' ? ['authed' => true]
            : ($path === '/verify-email' ? ['unverified' => true] : []);
        $body = gvlBody($path, $opts);

        // Exactly one auth-icon.
        $iconCount = substr_count($body, 'data-testid="auth-icon"');
        expect($iconCount)->toBe(1, "{$path}: expected exactly 1 auth-icon, got {$iconCount}.");

        // auth-heading exists and is text-only.
        $headingInner = gvlElementInner($body, 'auth-heading');
        expect($headingInner)->not->toBe('', "{$path}: auth-heading not found.");
        expect($headingInner)->not->toContain('<');
        expect(trim(strip_tags(html_entity_decode($headingInner, ENT_QUOTES | ENT_HTML5, 'UTF-8'))))->toBe($expectedHeading);

        // The icon's position in the body is BEFORE the heading's.
        $iconPos = strpos($body, 'data-testid="auth-icon"');
        $headingPos = strpos($body, 'data-testid="auth-heading"');
        expect($iconPos)->not->toBeFalse("{$path}: auth-icon not found.");
        expect($headingPos)->not->toBeFalse("{$path}: auth-heading not found.");
        expect($iconPos)->toBeLessThan($headingPos, "{$path}: auth-icon must come before auth-heading in the rendered HTML.");
    }
});

test('GVL4: public submission thanks state shows the new check-circle icon chip, the form state does not', function () {
    // Would fail if: the chip appears in the wrong state, is
    // duplicated, or the thanks page loses its back-home link.
    $space = Space::factory()->create(['rating_enabled' => false]);

    $formHtml = gvlPublicHtml($space, false);
    expect($formHtml)->not->toContain('data-testid="thanks-icon"');
    expect($formHtml)->toContain('data-testid="submission-form"');

    $thanksHtml = gvlPublicHtml($space, true);
    expect(substr_count($thanksHtml, 'data-testid="thanks-icon"'))->toBe(1);
    expect(substr_count($thanksHtml, 'data-testid="thanks-page"'))->toBe(1);
    expect(substr_count($thanksHtml, 'data-testid="back-home-link"'))->toBe(1);
});

test('GVL5: deletion form thanks card has the new check-circle icon chip and the same card across two outcomes', function () {
    // Would fail if: the icon is missing, the thanks card drifts
    // between outcomes (anything per-outcome leaking), or the form
    // state accidentally includes the thanks icon.
    $formHtml = (string) Livewire::test(DeletionRequestForm::class)->html();
    expect($formHtml)->not->toContain('deletion-thanks-icon');

    // Two distinct outcomes: unknown slug, and a filled honeypot.
    $reset = function () {
        $ip = request()->ip() ?: '127.0.0.1';
        foreach (['127.0.0.1', '::1', $ip] as $candidate) {
            \Illuminate\Support\Facades\RateLimiter::clear('deletion-request|ip|'.$candidate);
        }
    };
    $reset();
    $r1 = gvlRawSubmitForm([
        'email' => 'a@example.test',
        'spaceSlug' => 'no-such-space-1',
    ]);
    $h1 = gvlExtractHtml($r1);

    $reset();
    $r2 = gvlRawSubmitForm([
        'email' => 'b@example.test',
        'spaceSlug' => 'any-slug',
        DeletionRequestForm::HONEYPOT_FIELD => 'http://spam.example.test',
    ]);
    $h2 = gvlExtractHtml($r2);

    // Both must contain the thanks icon exactly once.
    expect(substr_count($h1, 'data-testid="deletion-thanks-icon"'))->toBe(1);
    expect(substr_count($h2, 'data-testid="deletion-thanks-icon"'))->toBe(1);

    // Extract the deletion-thanks block from each response and
    // compare them byte-for-byte. Livewire per-mount snapshot fields
    // differ between calls, so we strip wire:id before comparing.
    $normalize = function (string $body): string {
        return preg_replace('/wire:id="[A-Za-z0-9]+"/', 'wire:id="WID"', $body) ?? $body;
    };

    $block1 = gvlElementInner($normalize($h1), 'deletion-thanks');
    $block2 = gvlElementInner($normalize($h2), 'deletion-thanks');
    expect($block1)->not->toBe('', 'outcome 1 must include the deletion-thanks element.');
    expect($block2)->not->toBe('', 'outcome 2 must include the deletion-thanks element.');
    expect($block1)->toBe($block2);
});

test('GVL6: privacy page lists the five sections, the title, the consent list, and the right number of consent items', function () {
    // Would fail if: a section id is lost, the title disappears, the
    // consent list disappears, or the consent-item count drifts.
    $body = gvlBody('/privacy');

    $sections = [
        'privacy-section-collect',
        'privacy-section-why',
        'privacy-section-howlong',
        'privacy-section-removal',
        'privacy-section-consent',
    ];
    foreach ($sections as $id) {
        expect(substr_count($body, 'data-testid="'.$id.'"'))->toBe(1);
    }

    expect(substr_count($body, 'data-testid="privacy-title"'))->toBe(1);
    expect(substr_count($body, 'data-testid="privacy-consent-list"'))->toBe(1);

    config([
        'consent.current' => 'v2',
        'consent.texts' => [
            'v1' => 'Old text',
            'v2' => 'New text',
        ],
    ]);
    $body2 = gvlBody('/privacy');
    expect(substr_count($body2, 'data-testid="privacy-consent-item"'))->toBe(2);
});

test('GVL7: no low-contrast gray-500/gray-400 on privacy, deletion-request, public submission, or the thanks / locked states', function () {
    // Would fail if: a low-contrast gray-500 or gray-400 returns on
    // any of the listed pages. If the match comes from a shared
    // component I am not allowed to edit, STOP and report which
    // component; do NOT weaken the test.
    $pages = [
        ['/privacy', []],
        ['/privacy/request-deletion', []],
    ];
    $space = Space::factory()->create(['rating_enabled' => false]);
    $pages[] = ['/s/'.$space->slug, []];

    foreach ($pages as [$path, $opts]) {
        $body = gvlBody($path, $opts);
        expect($body)->not->toContain('text-gray-500');
        expect($body)->not->toContain('text-gray-400');
    }

    // Thanks state.
    $thanksHtml = gvlPublicHtml($space, true);
    expect($thanksHtml)->not->toContain('text-gray-500');
    expect($thanksHtml)->not->toContain('text-gray-400');

    // Locked state: 100 LIVE testimonials, same setup as BackHomeLinkTest.
    $lockedSpace = Space::factory()->create(['rating_enabled' => false]);
    Testimonial::factory()->for($lockedSpace)->count(100)->create();
    $lockedHtml = gvlPublicHtml($lockedSpace, false);
    expect($lockedHtml)->not->toContain('text-gray-500');
    expect($lockedHtml)->not->toContain('text-gray-400');
});

test('GVL8: no remote assets and a prefers-reduced-motion block on the four key guest pages', function () {
    // Would fail if: a remote font / image / CSS @import sneaks
    // onto a guest page, or the reduced-motion fail-safe block is
    // dropped from a page. (The reduced-motion check is weak by
    // nature because Pest cannot run CSS — the user verifies in a
    // browser. We assert only that the literal string
    // "prefers-reduced-motion: reduce" is in the page so the
    // block is at least present.)
    $space = Space::factory()->create(['rating_enabled' => false]);
    $pages = [
        '/login' => [],
        '/privacy' => [],
        '/privacy/request-deletion' => [],
        '/s/'.$space->slug => [],
    ];
    foreach ($pages as $path => $opts) {
        $body = gvlBody($path, $opts);
        foreach (['@import', 'url(http', 'fonts.googleapis', 'fonts.bunny', '<img'] as $forbidden) {
            expect($body)->not->toContain($forbidden);
        }
        expect($body)->toContain('prefers-reduced-motion: reduce');
    }
});

test('GVL9: entrance animations rest in a plain state (no leftover opacity/transform)', function () {
    // Would fail if: a base opacity/transform returns on the
    // animated elements (.gst-hero-fade > * and .gst-pop), the
    // animation fill mode goes back to forwards/both, or the
    // reduced-motion block is dropped. This guards the SOURCE of
    // the rules in the rendered page; Pest cannot run CSS, so the
    // real visual check is the user's browser check.
    $space = Space::factory()->create(['rating_enabled' => false]);
    $pages = [
        '/login' => [],
        '/register' => [],
        '/forgot-password' => [],
        '/privacy' => [],
        '/privacy/request-deletion' => [],
        '/s/'.$space->slug => [],
    ];

    foreach ($pages as $path => $opts) {
        $response = gvlResponse($path, $opts);
        $status = $response->getStatusCode();
        $body = (string) $response->getContent();
        expect($status)->toBe(200, "{$path}: expected 200, got {$status}.");

        // Step 6 (must hold on the FULL body before stripping):
        // still contains the reduced-motion media query once overall.
        expect(substr_count($body, 'prefers-reduced-motion: reduce'))->toBe(1, "{$path}: expected exactly 1 prefers-reduced-motion: reduce in the full body.");

        // Step 2: strip the whole @media (prefers-reduced-motion: reduce) { ... }
        // block. It legitimately contains `opacity: 1` and `transform: none`,
        // which would otherwise trip the base-rule checks below.
        $stripped = preg_replace(
            '/@media\s*\(prefers-reduced-motion:\s*reduce\)\s*\{(?:[^{}]*\{[^{}]*\})*[^{}]*\}/s',
            '',
            $body
        );
        expect($stripped)->not->toBeNull("{$path}: regex failure stripping reduced-motion block.");
        $stripped = (string) $stripped;

        // Step 3: base rule `.gst-hero-fade > * { ... }` must exist and
        // its block must contain NEITHER `opacity` NOR `transform`.
        $heroBase = '/\.gst-hero-fade\s*>\s*\*\s*\{([^{}]*)\}/s';
        $heroOk = preg_match($heroBase, $stripped, $heroMatch) === 1;
        expect($heroOk)->toBeTrue("{$path}: base .gst-hero-fade > * rule not found.");
        $heroBlock = $heroMatch[1];
        expect(stripos($heroBlock, 'opacity') === false)
            ->toBeTrue("{$path}: .gst-hero-fade > * base rule must not set opacity.");
        expect(stripos($heroBlock, 'transform') === false)
            ->toBeTrue("{$path}: .gst-hero-fade > * base rule must not set transform.");

        // Step 4: on pages that contain a standalone `.gst-pop {` base
        // rule (the guest-layout pages), the block must likewise contain
        // neither `opacity` nor `transform`. Privacy's own custom style
        // block does not define `.gst-pop`, so we only check when the
        // selector is actually present.
        if (preg_match('/\.gst-pop\s*\{([^{}]*)\}/s', $stripped, $popMatch) === 1) {
            $popBlock = $popMatch[1];
            expect(stripos($popBlock, 'opacity') === false)
                ->toBeTrue("{$path}: .gst-pop base rule must not set opacity.");
            expect(stripos($popBlock, 'transform') === false)
                ->toBeTrue("{$path}: .gst-pop base rule must not set transform.");
        }

        // Step 5: remaining CSS contains `backwards` and does NOT contain
        // `forwards`. The `forwards` word is forbidden everywhere on the
        // page once the reduced-motion block is gone.
        expect(stripos($stripped, 'backwards') !== false)
            ->toBeTrue("{$path}: expected the word `backwards` (fill mode) in the CSS.");
        expect(stripos($stripped, 'forwards') === false)
            ->toBeTrue("{$path}: the word `forwards` must not appear in the CSS outside the reduced-motion block.");
    }
});
