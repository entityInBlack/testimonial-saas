<?php

/**
 * Part B — `/privacy` static page (Step 8 / task 8.2).
 *
 * What would make this file FAIL:
 *   - `/privacy` not returning 200.
 *   - Any of the five required headings missing as a full <h2>
 *     (assertSee on a substring like "Why" is vacuous — many
 *     sentences contain the word "Why").
 *   - The retention number being hardcoded instead of coming from
 *     `config('purge.retention_days')`.
 *   - The deletion-request link missing.
 *   - The consent-versions section not labelling `config('consent.current')`
 *     as "Current" (or labelling it as "Previous").
 *   - The page not listing the data the deletion-request form
 *     stores (email, Space slug, optional testimonial id).
 *   - The page mentioning "GDPR compliant" or other legal-compliance
 *     claims (build-order spec: "No legal-compliance claims").
 */

/**
 * Extract the inner text of every <h2> on the page. Used by the
 * headings test so a partial-string match like assertSee('Why') is
 * not vacuous.
 */
function privacyH2Texts(string $body): array
{
    if (preg_match_all('#<h2\b[^>]*>(.*?)</h2>#is', $body, $matches) === false) {
        return [];
    }

    $out = [];
    foreach ($matches[1] as $raw) {
        $clean = strip_tags($raw);
        $clean = html_entity_decode($clean, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $clean = preg_replace('/\s+/u', ' ', $clean) ?? $clean;
        $out[] = trim($clean);
    }

    return $out;
}

/**
 * Extract the visible text of the element with a given data-testid,
 * stripping nested tags / scripts / styles / entities. Returns
 * an empty string when the element is not present.
 */
function privacyElementVisible(string $body, string $testid): string
{
    $needle = preg_quote('data-testid="'.$testid.'"', '/');

    $patterns = [
        '/<span\b[^>]*'.$needle.'[^>]*>(.*?)<\/span>/is',
        '/<p\b[^>]*'.$needle.'[^>]*>(.*?)<\/p>/is',
        '/<div\b[^>]*'.$needle.'[^>]*>(.*?)<\/div>/is',
        '/<section\b[^>]*'.$needle.'[^>]*>(.*?)<\/section>/is',
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

    $clean = preg_replace('#<script\b[^>]*>.*?</script>#is', ' ', $inner) ?? $inner;
    $clean = preg_replace('#<style\b[^>]*>.*?</style>#is', ' ', $clean) ?? $clean;
    $clean = strip_tags($clean);
    $clean = html_entity_decode($clean, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    return trim(preg_replace('/\s+/u', ' ', $clean) ?? $clean);
}

test('privacy GET /privacy returns 200', function () {
    $this->get('/privacy')->assertStatus(200);
});

test('privacy page shows all five required headings as full <h2> text', function () {
    $body = (string) $this->get('/privacy')->getContent();
    $h2s = privacyH2Texts($body);

    expect($h2s)->toContain('What we collect')
        ->and($h2s)->toContain('Why')
        ->and($h2s)->toContain('How long')
        ->and($h2s)->toContain('How to request removal')
        ->and($h2s)->toContain('Consent wording');
});

test('privacy retention number comes from config(purge.retention_days)', function () {
    config(['purge.retention_days' => 45]);

    $body = (string) $this->get('/privacy')->getContent();

    // The element must contain exactly 45, and the value must
    // be followed by "days" in the rendered HTML (a bare
    // assertSee('45') is vacuous — the number 45 is in many
    // other places). Look for the testid element, its inner
    // value (45), and the literal "days" word right after.
    $pattern = '/data-testid="privacy-retention-days">\s*45\s*<\/span>\s*days/';

    expect((bool) preg_match($pattern, $body))->toBeTrue(
        'privacy-retention-days element should contain "45" immediately followed by "days" in the rendered HTML.'
    );
});

test('privacy consent section lists every texts[] entry and labels current as Current, others as Previous', function () {
    config([
        'consent.current' => 'v2',
        'consent.texts' => [
            'v1' => 'Old text — please ignore',
            'v2' => 'New text — in effect',
        ],
    ]);

    $response = $this->get('/privacy');

    $response->assertStatus(200);
    $response->assertSee('Old text — please ignore', false);
    $response->assertSee('New text — in effect', false);

    // v2 is the current version — labelled "Current" and NOT "Previous".
    $v2Item = getConsentItem($response, 'v2');
    expect($v2Item)->not->toBeNull();
    expect(str_contains($v2Item, 'data-testid="privacy-consent-current"'))->toBeTrue();
    expect(str_contains($v2Item, 'data-testid="privacy-consent-previous"'))->toBeFalse();

    // v1 is a previous version — labelled "Previous" and NOT "Current".
    $v1Item = getConsentItem($response, 'v1');
    expect($v1Item)->not->toBeNull();
    expect(str_contains($v1Item, 'data-testid="privacy-consent-previous"'))->toBeTrue();
    expect(str_contains($v1Item, 'data-testid="privacy-consent-current"'))->toBeFalse();
});

/**
 * Helper: extract the <li data-testid="privacy-consent-item" ...>
 * for a given consent version key from the response body. Returns
 * null when the key is not present.
 */
function getConsentItem(\Illuminate\Testing\TestResponse $response, string $key): ?string
{
    $body = (string) $response->getContent();
    $pattern = '/<li[^>]*data-testid="privacy-consent-item"[^>]*data-version="'.preg_quote($key, '/').'"[^>]*>(.*?)<\/li>/s';
    if (preg_match($pattern, $body, $m) !== 1) {
        return null;
    }

    return $m[1];
}

test('privacy page lists the data the deletion-request form stores (email, slug, optional testimonial id)', function () {
    $body = (string) $this->get('/privacy')->getContent();

    // The "What we collect" section must mention all three fields
    // the deletion-requests table stores from the public form.
    $collect = privacyElementVisible($body, 'privacy-section-collect');

    expect(stripos($collect, 'email'))->not->toBeFalse('collect section must mention email')
        ->and(stripos($collect, 'slug'))->not->toBeFalse('collect section must mention the Space slug')
        ->and(stripos($collect, 'testimonial id'))->not->toBeFalse('collect section must mention the optional testimonial id');
});

test('privacy page links to the deletion-request form', function () {
    $this->get('/privacy')
        ->assertStatus(200)
        ->assertSee(route('privacy.request-deletion'), false);
});

test('privacy page does NOT claim legal compliance (no "GDPR compliant" or similar)', function () {
    $body = (string) $this->get('/privacy')->getContent();

    foreach (['gdpr compliant', 'hipaa', 'soc 2', 'iso 27001', 'pci dss'] as $banned) {
        expect(stripos($body, $banned))->toBeFalse(
            "Privacy page must not claim '{$banned}' compliance (legal review is a v2 item)."
        );
    }
});
