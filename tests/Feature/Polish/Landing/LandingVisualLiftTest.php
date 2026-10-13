<?php

use App\Models\User;

/**
 * Part 1a — visual lift tests for the public landing page.
 *
 * Every helper function is prefixed with `lvl` because Pest helpers
 * are global and other test files already define
 * `landingElementVisible`, `landingVisibleText`, etc.
 *
 * Each test has a one-line "Would fail if" comment that describes
 * what change would break the assertion.
 */

/**
 * Visible text of the element carrying $testid, after stripping tags,
 * scripts, styles, decoding entities, and collapsing whitespace.
 */
function lvlElementVisible(string $body, string $testid): string
{
    $needle = preg_quote('data-testid="'.$testid.'"', '/');

    $patterns = [
        '/<p\b[^>]*'.$needle.'[^>]*>(.*?)<\/p>/is',
        '/<div\b[^>]*'.$needle.'[^>]*>(.*?)<\/div>/is',
        '/<h1\b[^>]*'.$needle.'[^>]*>(.*?)<\/h1>/is',
        '/<h2\b[^>]*'.$needle.'[^>]*>(.*?)<\/h2>/is',
        '/<h3\b[^>]*'.$needle.'[^>]*>(.*?)<\/h3>/is',
        '/<ul\b[^>]*'.$needle.'[^>]*>(.*?)<\/ul>/is',
        '/<li\b[^>]*'.$needle.'[^>]*>(.*?)<\/li>/is',
        '/<span\b[^>]*'.$needle.'[^>]*>(.*?)<\/span>/is',
        '/<section\b[^>]*'.$needle.'[^>]*>(.*?)<\/section>/is',
        '/<pre\b[^>]*'.$needle.'[^>]*>(.*?)<\/pre>/is',
        '/<figure\b[^>]*'.$needle.'[^>]*>(.*?)<\/figure>/is',
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
    $clean = preg_replace('/\s+/u', ' ', $clean) ?? $clean;

    return trim($clean);
}

test('TL1: landing exposes the collage, mobile-card fallback, four chips, and the hero caption', function () {
    // Would fail if: the collage block or the mobile fallback is missing,
    // any chip is missing or duplicated, or the hero caption text drifts.
    $body = (string) $this->get('/')->getContent();

    expect(substr_count($body, 'data-testid="landing-collage"'))->toBe(1);
    expect(substr_count($body, 'data-testid="landing-collage-mobile"'))->toBe(1);
    expect(substr_count($body, 'data-testid="landing-chip"'))->toBe(4);

    $caption = lvlElementVisible($body, 'landing-hero-caption');
    expect($caption)->toBe('Free plan, no card needed.');
});

test('TL2: there are exactly six sample cards and six Sample labels', function () {
    // Would fail if: any sample card is missing or duplicated, or any
    // Sample label is missing/duplicated/wrong text.
    $body = (string) $this->get('/')->getContent();

    expect(substr_count($body, 'data-testid="landing-sample-card"'))->toBe(6);
    expect(substr_count($body, 'data-testid="landing-sample-label"'))->toBe(6);

    preg_match_all('/<span\b[^>]*data-testid="landing-sample-label"[^>]*>(.*?)<\/span>/is', $body, $matches);
    foreach ($matches[1] as $raw) {
        $clean = strip_tags($raw);
        $clean = html_entity_decode($clean, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $clean = trim(preg_replace('/\s+/u', ' ', $clean) ?? $clean);
        expect($clean)->toBe('Sample');
    }
});

test('TL3: the embed snippet is exactly the two lines from EmbedBuilder, and the markup is escaped', function () {
    // Would fail if: the snippet text drifts from the embed builder
    // format, or the markup is emitted raw instead of escaped.
    $body = (string) $this->get('/')->getContent();

    $expected = '<div data-testimonial-space="YOUR_SPACE_ID"></div>'."\n"
        .'<script src="'.rtrim((string) config('app.url'), '/').'/embed.js" async></script>';

    // Extract the <pre data-testid="landing-embed-snippet"> inner text,
    // stripping tags + entities, and PRESERVING line breaks (the helper
    // lvlElementVisible collapses whitespace).
    $needle = preg_quote('data-testid="landing-embed-snippet"', '/');
    $pattern = '/<pre\b[^>]*'.$needle.'[^>]*>(.*?)<\/pre>/is';
    expect((bool) preg_match($pattern, $body, $m))->toBeTrue();
    $raw = $m[1];

    $raw = preg_replace('#<script\b[^>]*>.*?</script>#is', ' ', $raw) ?? $raw;
    $raw = preg_replace('#<style\b[^>]*>.*?</style>#is', ' ', $raw) ?? $raw;
    $raw = strip_tags($raw);
    $raw = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $raw = preg_replace("/\r\n?/", "\n", $raw) ?? $raw;
    $lines = array_map('trim', explode("\n", $raw));
    $normalised = implode("\n", $lines);

    expect($normalised)->toBe($expected);

    // The raw, unescaped tag MUST NOT appear in the rendered HTML.
    expect($body)->not->toContain('<div data-testimonial-space="YOUR_SPACE_ID"></div>');
});

test('TL4: the free plan line shows both limits from config', function () {
    // Would fail if: either number is hardcoded, or the line text drifts.
    config(['limits.max_spaces' => 7, 'limits.max_testimonials_per_space' => 250]);

    $body = (string) $this->get('/')->getContent();
    $line = lvlElementVisible($body, 'landing-free-plan-line');

    expect($line)->toBe('7 Spaces · 250 testimonials per Space');
});

test('TL5: the closing band is a section with exactly one register and one privacy href', function () {
    // Would fail if: the closing band is not a <section>, or the band
    // contains more than one of each link, or the testids are missing.
    $body = (string) $this->get('/')->getContent();

    expect(substr_count($body, 'data-testid="landing-final-cta"'))->toBe(1);

    preg_match_all('/<section\b[^>]*data-testid="landing-final-cta"[^>]*>(.*?)<\/section>/is', $body, $matches);
    expect($matches[1])->toHaveCount(1);
    $inner = $matches[1][0];

    $registerHref = 'href="'.route('register').'"';
    $privacyHref = 'href="'.route('privacy.show').'"';

    expect(substr_count($inner, $registerHref))->toBe(1);
    expect(substr_count($inner, $privacyHref))->toBe(1);
    expect($inner)->toContain('data-testid="landing-final-cta-register"');
    expect($inner)->toContain('data-testid="landing-final-privacy"');
});

test('TL6: exactly five opening tags carry the data-reveal attribute, on the right elements', function () {
    // Would fail if: data-reveal is missing on any of the five reveal
    // targets, applied to a wrong element, or counted from inside a
    // <script> string.
    $body = (string) $this->get('/')->getContent();

    $pattern = '/<[a-z0-9]+\b[^>]*\sdata-reveal(?=[\s>=\/])[^>]*>/i';
    preg_match_all($pattern, $body, $matches);
    expect(count($matches[0]))->toBe(5);

    $ids = [
        'landing-embed-section',
        'landing-wall-section',
        'landing-features-list',
        'landing-free-plan',
        'landing-final-cta',
    ];
    foreach ($ids as $id) {
        $needle = preg_quote('data-testid="'.$id.'"', '/');
        $tagPattern = '/<[a-z0-9]+\b[^>]*'.$needle.'[^>]*\sdata-reveal(?=[\s>=\/])[^>]*>/i';
        expect((bool) preg_match($tagPattern, $body))->toBeTrue(
            "Expected the opening tag of {$id} to carry data-reveal."
        );
    }
});

test('TL7: the landing body has no remote resources and no uppercase / dark classes', function () {
    // Would fail if: someone adds a remote font, CDN, image, or
    // accidentally re-introduces uppercase/tracking-widest/dark: classes.
    $body = (string) $this->get('/')->getContent();

    $forbidden = [
        '<img',
        '@import',
        'url(http',
        'fonts.googleapis',
        'fonts.gstatic',
        'fonts.bunny',
        'cdn.',
        'unpkg',
        'jsdelivr',
        'cdnjs',
        'uppercase',
        'tracking-widest',
        'dark:',
    ];
    foreach ($forbidden as $token) {
        expect($body)->not->toContain($token, "Landing body must not contain: {$token}");
    }
});

test('TL8: header and footer test ids match the public spec, guest vs logged-in', function () {
    // Would fail if: the shared public header loses its testids, the
    // dashboard link leaks for guests, the login link leaks for
    // logged-in users, or the footer testids drift.
    $guestBody = (string) $this->get('/')->getContent();

    foreach ([
        'landing-header',
        'landing-brand',
        'landing-nav',
        'landing-privacy-link',
        'landing-request-deletion-link',
        'landing-login-link',
        'landing-register-link',
    ] as $id) {
        expect(substr_count($guestBody, 'data-testid="'.$id.'"'))->toBe(1);
    }
    expect(substr_count($guestBody, 'data-testid="landing-dashboard-link"'))->toBe(0);

    // Footer ids (guest).
    expect(substr_count($guestBody, 'data-testid="landing-footer-"'))->toBe(1);
    expect(substr_count($guestBody, 'data-testid="landing-footer-copy"'))->toBe(1);
    expect(substr_count($guestBody, 'data-testid="landing-footer-privacy"'))->toBe(1);
    expect(substr_count($guestBody, 'data-testid="landing-footer-request-deletion"'))->toBe(1);

    $user = User::factory()->create();
    $authBody = (string) $this->actingAs($user)->get('/')->getContent();

    expect(substr_count($authBody, 'data-testid="landing-dashboard-link"'))->toBe(1);
    expect(substr_count($authBody, 'data-testid="landing-login-link"'))->toBe(0);
    expect(substr_count($authBody, 'data-testid="landing-register-link"'))->toBe(0);

    // Footer still once each when logged in.
    expect(substr_count($authBody, 'data-testid="landing-footer-"'))->toBe(1);
    expect(substr_count($authBody, 'data-testid="landing-footer-copy"'))->toBe(1);
    expect(substr_count($authBody, 'data-testid="landing-footer-privacy"'))->toBe(1);
    expect(substr_count($authBody, 'data-testid="landing-footer-request-deletion"'))->toBe(1);
});

test('TL9: every locked id is present exactly once, on the right kind of element', function () {
    // Would fail if: any locked id is missing, renamed, or attached to
    // a wrong element (e.g. landing-features-title is not an <h2>).
    $body = (string) $this->get('/')->getContent();

    foreach ([
        'landing-hero-title',
        'landing-hero-tagline',
        'landing-cta',
        'landing-privacy-cta',
        'landing-free-plan',
        'landing-free-plan-heading',
        'landing-free-plan-line',
        'landing-features-title',
        'landing-features-list',
    ] as $id) {
        expect(substr_count($body, 'data-testid="'.$id.'"'))->toBe(1);
    }

    // Element-kind checks.
    $heroTitleTag = '/<h1\b[^>]*data-testid="landing-hero-title"[^>]*>/i';
    expect((bool) preg_match($heroTitleTag, $body))->toBeTrue();

    $featuresTitleTag = '/<h2\b[^>]*data-testid="landing-features-title"[^>]*>/i';
    expect((bool) preg_match($featuresTitleTag, $body))->toBeTrue();

    $featuresListTag = '/<ul\b[^>]*data-testid="landing-features-list"[^>]*>/i';
    expect((bool) preg_match($featuresListTag, $body))->toBeTrue();

    // Exactly six feature list items.
    expect(substr_count($body, 'data-testid="landing-feature-'))->toBe(6);

    $featureIds = [
        'landing-feature-spaces',
        'landing-feature-inbox',
        'landing-feature-consent',
        'landing-feature-embed',
        'landing-feature-deletion',
        'landing-feature-purge',
    ];
    foreach ($featureIds as $id) {
        expect(substr_count($body, 'data-testid="'.$id.'"'))->toBe(1);
    }
});

test('TL10: the prefers-reduced-motion guard is present in the landing body', function () {
    // Would fail if: the reduced-motion media query is removed from
    // the inline <style> block. (The real check is the manual browser
    // test in section 10; this is a weak guard.)
    $body = (string) $this->get('/')->getContent();

    expect($body)->toContain('prefers-reduced-motion');
});

test('TL11: responsive pins for the collage and the mobile card', function () {
    // Would fail if: the collage stops being hidden below lg, the
    // mobile card is hidden at lg or never shown below it, or the
    // mobile block is nested inside the collage block (where it
    // would itself be hidden on phones).
    $body = (string) $this->get('/')->getContent();

    // (a) Collage: hidden below lg, shown at lg and up.
    $collagePattern = '/<[a-z0-9]+\b[^>]*data-testid="landing-collage"[^>]*>/i';
    expect((bool) preg_match($collagePattern, $body, $m))->toBeTrue();
    $collageTag = $m[0];
    $collageTokens = preg_split('/\s+/', (string) preg_match('/class="([^"]*)"/', $collageTag, $cm) ? $cm[1] : '', -1, PREG_SPLIT_NO_EMPTY);
    expect($collageTokens)->toContain('hidden');
    expect($collageTokens)->toContain('lg:block');

    // (b) Mobile card: shown below lg, hidden at lg and up, and must
    // not be hidden unconditionally.
    $mobilePattern = '/<[a-z0-9]+\b[^>]*data-testid="landing-collage-mobile"[^>]*>/i';
    expect((bool) preg_match($mobilePattern, $body, $m2))->toBeTrue();
    $mobileTag = $m2[0];
    $mobileTokens = preg_split('/\s+/', (string) preg_match('/class="([^"]*)"/', $mobileTag, $cm2) ? $cm2[1] : '', -1, PREG_SPLIT_NO_EMPTY);
    expect($mobileTokens)->toContain('lg:hidden');
    expect($mobileTokens)->not->toContain('hidden');

    // (c) Nesting guard: between the collage opening tag and the
    // mobile opening tag, every <div ...> has a matching </div>.
    $collageStart = strpos($body, $collageTag);
    expect($collageStart)->toBeInt()->not->toBeFalse();
    $mobileStart = strpos($body, $mobileTag, $collageStart);
    expect($mobileStart)->toBeInt()->not->toBeFalse();
    $between = substr($body, $collageStart, $mobileStart - $collageStart);

    $openCount = preg_match_all('/<div\b/', $between);
    $closeCount = preg_match_all('/<\/div>/', $between);
    expect($openCount)->toBe($closeCount);
});

test('TL12: reveal script structure', function () {
    // Would fail if: the catch becomes empty or loses console.error,
    // or the hidden class is added before the observer exists. (Weak
    // static guard; the real check is the manual browser test.)
    $body = (string) $this->get('/')->getContent();

    // Grab the inline <script> that has no src attribute and contains
    // "IntersectionObserver".
    $pattern = '/<script\b(?![^>]*\bsrc=)[^>]*>.*?IntersectionObserver.*?<\/script>/is';
    expect((bool) preg_match($pattern, $body, $m))->toBeTrue();
    $script = $m[0];

    // (a) Must call console.error.
    expect($script)->toContain('console.error');

    // (b) Must have NO empty catch block (tolerates whitespace, single-
    // line // comments and /* … */ comments between { and }).
    $emptyCatch = '/catch\s*\(\s*\w+\s*\)\s*\{\s*(?:\/\*.*?\*\/\s*|\/\/[^\n]*\s*)*\}/s';
    expect((bool) preg_match($emptyCatch, $script))->toBeFalse();

    // (c) Observer is constructed BEFORE lvl-reveal is added, and
    // both exist in the script.
    $observerPos = strpos($script, 'new IntersectionObserver');
    $addPos = preg_match('/classList\.add\(\s*[\'"]lvl-reveal[\'"]\s*\)/', $script, $am, PREG_OFFSET_CAPTURE) === 1
        ? $am[0][1]
        : false;
    expect($observerPos)->toBeInt()->not->toBeFalse();
    expect($addPos)->toBeInt()->not->toBeFalse();
    expect($observerPos)->toBeLessThan($addPos);

    // (d) Must contain at least one indexed `for (` loop (FIX 1
    // requires an indexed loop over [data-reveal]).
    expect((bool) preg_match('/for\s*\(/', $script))->toBeTrue();
});
