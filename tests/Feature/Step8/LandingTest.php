<?php

/**
 * Part A — public `/` landing page (Step 8 / task 8.1).
 *
 * What would make this file FAIL:
 *   - `/` not returning 200 (route missing, controller 500, or
 *     view not found).
 *   - The Free-plan numbers in the copy not coming from
 *     `config('limits.*')` (hardcoded copy is a spec violation).
 *   - The page mentioning "Pro", "Stripe", "Upgrade" or "billing"
 *     anywhere in the visible text (Hard Rule 2 — no upgrade CTA
 *     in v1).
 *   - The register CTA missing or pointing somewhere other than
 *     `route('register')`.
 */

test('landing GET / returns 200 and links to /register via the route name', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
    $response->assertSee(route('register'), false);
});

/**
 * Extract the visible text of the element that carries
 * `data-testid="$testid"`. For a `<p data-testid="X">…</p>` the
 * element's text content is taken. We DO NOT strip tags first
 * because the testid attribute lives INSIDE the opening tag and
 * would be removed by strip_tags. The pattern matches a single
 * element with the testid, taking its inner content.
 */
function landingElementVisible(string $body, string $testid): string
{
    $needle = preg_quote('data-testid="'.$testid.'"', '/');

    $patterns = [
        // <p ... data-testid="X" ...>...</p>
        '/<p\b[^>]*'.$needle.'[^>]*>(.*?)<\/p>/is',
        // <div ... data-testid="X" ...>...</div>
        '/<div\b[^>]*'.$needle.'[^>]*>(.*?)<\/div>/is',
        // <h1 ... data-testid="X" ...>...</h1>
        '/<h1\b[^>]*'.$needle.'[^>]*>(.*?)<\/h1>/is',
        // <h2 ... data-testid="X" ...>...</h2>
        '/<h2\b[^>]*'.$needle.'[^>]*>(.*?)<\/h2>/is',
        // <span ... data-testid="X" ...>...</span>
        '/<span\b[^>]*'.$needle.'[^>]*>(.*?)<\/span>/is',
    ];

    $inner = null;
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $body, $m) === 1) {
            $inner = $m[1];
            break;
        }
    }
    if ($inner === null) {
        return '';
    }

    // Strip nested tags, scripts, styles, and entities.
    $clean = preg_replace('#<script\b[^>]*>.*?</script>#is', ' ', $inner) ?? $inner;
    $clean = preg_replace('#<style\b[^>]*>.*?</style>#is', ' ', $clean) ?? $clean;
    $clean = strip_tags($clean);
    $clean = html_entity_decode($clean, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $clean = preg_replace('/\s+/u', ' ', $clean) ?? $clean;

    return trim($clean);
}

/**
 * Extract the whole visible text of the body (scripts/styles/tags
 * stripped, entities decoded, whitespace collapsed) — used by the
 * banned-word check.
 */
function landingVisibleText(string $body): string
{
    $clean = preg_replace('#<script\b[^>]*>.*?</script>#is', ' ', $body) ?? $body;
    $clean = preg_replace('#<style\b[^>]*>.*?</style>#is', ' ', $clean) ?? $clean;
    $clean = strip_tags($clean);
    $clean = html_entity_decode($clean, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    return trim(preg_replace('/\s+/u', ' ', $clean) ?? $clean);
}

test('landing free-plan line contains both default limits from config', function () {
    $body = (string) $this->get('/')->getContent();

    $line = landingElementVisible($body, 'landing-free-plan-line');

    expect($line)->toContain((string) config('limits.max_spaces').' Spaces')
        ->and($line)->toContain((string) config('limits.max_testimonials_per_space').' testimonials per Space');
});

test('changing limits.max_spaces at runtime updates the landing-free-plan-line (proves the value is config-driven, not hardcoded)', function () {
    config(['limits.max_spaces' => 7]);

    $body = (string) $this->get('/')->getContent();

    $line = landingElementVisible($body, 'landing-free-plan-line');

    expect($line)->toContain('7 Spaces')
        ->and($line)->not->toContain('3 Spaces');
});

test('landing does NOT mention Pro, Stripe, Upgrade or billing in the visible text (case-insensitive, word-boundary)', function () {
    $body = (string) $this->get('/')->getContent();
    $visible = landingVisibleText($body);

    foreach (['pro', 'stripe', 'upgrade', 'billing'] as $banned) {
        $pattern = '/(?<![A-Za-z0-9])'.preg_quote($banned, '/').'(?![A-Za-z0-9])/i';
        expect((bool) preg_match($pattern, $visible))->toBeFalse(
            "Landing page should not contain '{$banned}' as a whole word in the visible text but it does."
        );
    }
});
