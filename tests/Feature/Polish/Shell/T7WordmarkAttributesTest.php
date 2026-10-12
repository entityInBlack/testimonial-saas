<?php

use App\Models\User;

/**
 * T7 — wordmark anchors carry no stray props.
 *
 * Wave 0b: brand-wordmark, public-header, public-footer, and
 * page-header declare their inputs with @props (F1) so the props do
 * not leak as stray HTML attributes onto the rendered tags. The
 * data-testid attribute is rendered EXPLICITLY by each component
 * (`data-testid="..."`) and is not a prop.
 *
 * This test guards three pages:
 *   - /login            (uses public-header, brand-wordmark)
 *   - /dashboard (logged in) (uses the app nav with brand-wordmark)
 *   - /                 (uses public-header + public-footer)
 *
 * On every page, the regex scan over <a ...> opening tags must NOT
 * find an attribute named one of:
 *   context, testid, testidprefix, isauthed
 * in any case (so `Context`, `Testid`, etc. all match). The literal
 * `data-testid` attribute (with the `data-` prefix) is what we render
 * and is not in the forbidden list — it must remain.
 *
 * Would fail if:
 *   - a caller passes the prop without the data- prefix
 *     (e.g. `<x-public-header testIdPrefix="landing-">` is fine, but
 *     a leaked `testidprefix` attribute on the rendered tag would
 *     appear here),
 *   - someone reverts @props to $attributes->merge() style without
 *     filtering these keys, allowing them to leak.
 */

function assertNoStrayPropsOnAnchors(string $body, string $page): void
{
    // Scan every <a ...> opening tag (case-insensitive, no nesting).
    $pattern = '/<a\b([^>]*)>/i';
    $matches = [];
    $found = preg_match_all($pattern, $body, $matches);
    expect($found)->toBeGreaterThan(0, "{$page}: expected at least one <a> on the page.");

    $forbidden = ['context', 'testid', 'testidprefix', 'isauthed'];
    $bad = [];
    foreach ($matches[1] as $i => $attrs) {
        foreach ($forbidden as $name) {
            // Attribute name boundary: name = optional whitespace, the
            // literal name, optional whitespace, then = or end of tag.
            // The case-insensitive flag handles "Context" / "TESTID".
            $attrPattern = '/\b(?:data-)?'.preg_quote($name, '/').'\s*=\s*(["\'])/i';
            if (preg_match($attrPattern, $attrs) === 1) {
                // Special-case: a literal `data-testid="..."` is
                // allowed (that is how we render the test id). The
                // forbidden list above deliberately lists the bare
                // name `testid`; the regex tolerates an optional
                // `data-` prefix. We must therefore ignore the
                // `data-testid` case explicitly.
                $isDataTestid = strtolower($name) === 'testid'
                    && preg_match('/\bdata-testid\s*=\s*(["\'])/i', $attrs) === 1
                    && preg_match('/\b(?<!data-)testid\s*=\s*(["\'])/i', $attrs) === 0;
                if (! $isDataTestid) {
                    $bad[] = ['tag' => '<a '.trim($attrs).'>', 'attr' => $name];
                }
            }
        }
    }

    expect($bad)->toBe([], "{$page}: found stray props on <a> tags: ".json_encode($bad));
}

test('T7a: /login — public-header-brand anchor href is route(landing)', function () {
    $response = $this->get('/login');
    $response->assertStatus(200);
    $body = (string) $response->getContent();

    $pattern = '/<a\b[^>]*\bdata-testid="public-header-brand"[^>]*>/i';
    $matches = [];
    $found = preg_match_all($pattern, $body, $matches);
    expect($found)->toBe(1, "Expected exactly 1 <a data-testid=\"public-header-brand\">, found {$found}.");

    $tag = $matches[0][0];
    $hrefPattern = '/\bhref=(["\'])(.*?)\1/i';
    $hrefMatches = [];
    $hasHref = preg_match($hrefPattern, $tag, $hrefMatches);
    expect($hasHref)->toBe(1, "public-header-brand <a> has no href attribute. Tag was: {$tag}");
    expect($hrefMatches[2])->toBe(route('landing'));
});

test('T7b: logged-in /dashboard — app-nav-wordmark anchor href is route(dashboard)', function () {
    $user = User::factory()->create();
    $response = $this->actingAs($user)->get(route('dashboard'));
    $response->assertStatus(200);
    $body = (string) $response->getContent();

    $pattern = '/<a\b[^>]*\bdata-testid="app-nav-wordmark"[^>]*>/i';
    $matches = [];
    $found = preg_match_all($pattern, $body, $matches);
    expect($found)->toBe(1, "Expected exactly 1 <a data-testid=\"app-nav-wordmark\">, found {$found}.");

    $tag = $matches[0][0];
    $hrefPattern = '/\bhref=(["\'])(.*?)\1/i';
    $hrefMatches = [];
    $hasHref = preg_match($hrefPattern, $tag, $hrefMatches);
    expect($hasHref)->toBe(1, "app-nav-wordmark <a> has no href attribute. Tag was: {$tag}");
    expect($hrefMatches[2])->toBe(route('dashboard'));
});

test('T7c: no a-tag on /login carries a stray context/testid/testidprefix/isauthed attribute', function () {
    $response = $this->get('/login');
    $response->assertStatus(200);
    assertNoStrayPropsOnAnchors((string) $response->getContent(), '/login');
});

test('T7d: no a-tag on /dashboard (logged in) carries a stray context/testid/testidprefix/isauthed attribute', function () {
    $user = User::factory()->create();
    $response = $this->actingAs($user)->get(route('dashboard'));
    $response->assertStatus(200);
    assertNoStrayPropsOnAnchors((string) $response->getContent(), '/dashboard');
});

test('T7e: no a-tag on / carries a stray context/testid/testidprefix/isauthed attribute', function () {
    $response = $this->get('/');
    $response->assertStatus(200);
    assertNoStrayPropsOnAnchors((string) $response->getContent(), '/');
});
