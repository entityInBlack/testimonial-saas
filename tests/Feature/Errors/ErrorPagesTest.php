<?php

use Illuminate\Support\Facades\Route;

/**
 * Error pages — Part G polish.
 *
 * Laravel 11 serves `resources/views/errors/{code}.blade.php` for
 * the matching HTTP status. The 403/404/500 pages must:
 *
 *   - Render the right HTTP status (Laravel sets this from the
 *     exception's status, not from a route handler).
 *   - Surface the code in a stable, testable element.
 *   - Link back to a known-good page (landing, privacy).
 *   - Not leak exception messages or stack traces on 500 when
 *     `app.debug` is false.
 *   - NOT mention a paid tier or any Step-8-banned words in
 *     the visible text.
 *
 * The 500 and 403 paths are exercised via test-only routes
 * registered inside the test (so the assertion is a real HTTP
 * round-trip, not a `view('errors.500')->render()` snapshot — the
 * snapshot would skip the Laravel exception handler that swaps
 * in the 500 view in production).
 */

test('a real GET to a nonexistent URL returns 404 with the exact 404 heading', function () {
    $response = $this->get('/not-a-real-page-12345');

    $response->assertStatus(404);

    // The heading text is the exact text inside the <h1
    // data-testid="error-title"> element — checked via regex
    // so assertSee('404') is not vacuous.
    $body = (string) $response->getContent();
    expect((bool) preg_match('#<h1[^>]*data-testid="error-title"[^>]*>\s*We could not find that page\.\s*</h1>#s', $body))
        ->toBeTrue('404 page must render the exact heading "We could not find that page."');
});

test('abort(403) from a test-only route returns 403 with the exact 403 heading', function () {
    Route::get('/__test-403__', function () {
        abort(403);
    });

    $response = $this->get('/__test-403__');

    $response->assertStatus(403);

    $body = (string) $response->getContent();
    expect((bool) preg_match('#<h1[^>]*data-testid="error-title"[^>]*>\s*You do not have access to that page\.\s*</h1>#s', $body))
        ->toBeTrue('403 page must render the exact heading "You do not have access to that page."');
});

test('a thrown RuntimeException with app.debug=false returns 500 with the exact 500 heading and never leaks the exception message or Whoops', function () {
    config(['app.debug' => false]);

    Route::get('/__test-500__', function () {
        throw new \RuntimeException('secret-boom-123');
    });

    // By default the Pest test harness DOES render the
    // exception into the matching error view (it does not
    // call withoutExceptionHandling). A real GET to the
    // throwing route therefore returns the 500 page that
    // Laravel's exception handler produced.
    $response = $this->get('/__test-500__');

    $response->assertStatus(500);

    $body = (string) $response->getContent();
    expect((bool) preg_match('#<h1[^>]*data-testid="error-title"[^>]*>\s*Something went wrong on our end\.\s*</h1>#s', $body))
        ->toBeTrue('500 page must render the exact heading "Something went wrong on our end."');

    // The exception message MUST NOT appear in the body. The
    // debug=false contract is what we're proving.
    $response->assertDontSee('secret-boom-123');
    $response->assertDontSee('Whoops');
});

test('error pages do not mention pro / stripe / upgrade / billing in the visible text', function () {
    $pages = [
        403 => 'You do not have access to that page.',
        404 => 'We could not find that page.',
        500 => 'Something went wrong on our end.',
    ];

    foreach ($pages as $code => $expectedTitle) {
        $body = (string) view('errors.'.$code)->render();

        // Strip <script> and <style> contents and all tags so
        // the check is a true word-boundary regex on what a
        // visitor sees (matches the Landing/Privacy method).
        $clean = preg_replace('#<script\b[^>]*>.*?</script>#is', ' ', $body) ?? $body;
        $clean = preg_replace('#<style\b[^>]*>.*?</style>#is', ' ', $clean) ?? $clean;
        $clean = strip_tags($clean);
        $clean = html_entity_decode($clean, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $visible = preg_replace('/\s+/u', ' ', $clean) ?? $clean;

        expect($body)->toContain($expectedTitle);

        foreach (['pro', 'stripe', 'upgrade', 'billing'] as $banned) {
            $pattern = '/(?<![A-Za-z0-9])'.preg_quote($banned, '/').'(?![A-Za-z0-9])/i';
            expect((bool) preg_match($pattern, $visible))->toBeFalse(
                "error {$code} must not contain '{$banned}' as a whole word in the visible text"
            );
        }
    }
});
