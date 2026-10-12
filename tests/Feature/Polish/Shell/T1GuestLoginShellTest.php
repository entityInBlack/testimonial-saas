<?php

/**
 * T1 — guest page /login shell.
 *
 * Wave 0a: the shared public header + footer live in the guest layout
 * (layouts/guest.blade.php). The login page uses that layout, so a
 * guest visiting /login must see the wordmark (an <a> with
 * data-testid="public-header-brand" that links to the landing route
 * and whose inner text is the app name) and must NOT see the Laravel
 * logo or a remote font link.
 *
 * Would fail if:
 *   - the Laravel logo svg leaks into the guest layout (its viewBox
 *     would appear in the body),
 *   - the fonts.bunny.net link is still in the layout,
 *   - the wordmark link to route('landing') disappears, is duplicated,
 *     or its inner text no longer contains the app name.
 *
 * Wave 0b: T1 is tightened to find the anchor by regex on its
 * data-testid, the way tests/Feature/Submission/BackHomeLinkTest.php
 * does, instead of the previous whole-page "contains the URL / app
 * name" assertions that also passed because of the <title> tag and
 * other links.
 */

test('T1: guest /login renders the shared shell: wordmark anchor points to route(landing) and carries the app name, no logo svg, no remote font', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);

    $body = (string) $response->getContent();

    // No remote font link.
    expect($body)->not->toContain('fonts.bunny.net');

    // No Laravel logo (the SVG with viewBox 0 0 316 316).
    expect($body)->not->toContain('viewBox="0 0 316 316"');

    // Find the <a data-testid="public-header-brand" ...> opening tag
    // by regex (like BackHomeLinkTest does). The match count must be
    // exactly 1, and the matched tag's href must equal route('landing').
    $pattern = '/<a\b[^>]*\bdata-testid="public-header-brand"[^>]*>/i';
    $matches = [];
    $found = preg_match_all($pattern, $body, $matches);
    expect($found)->toBe(1, "Expected to find exactly 1 <a data-testid=\"public-header-brand\">, found {$found}.");

    $tag = $matches[0][0];

    // href must equal route('landing').
    $hrefPattern = '/\bhref=(["\'])(.*?)\1/i';
    $hrefMatches = [];
    $hasHref = preg_match($hrefPattern, $tag, $hrefMatches);
    expect($hasHref)->toBe(1, "public-header-brand <a> has no href attribute. Tag was: {$tag}");
    expect($hrefMatches[2])->toBe(route('landing'));

    // The inner text of the matching anchor must contain the app name.
    // Capture the contents between the matched opening tag and the
    // first </a> after it.
    $innerPattern = '/<a\b[^>]*\bdata-testid="public-header-brand"[^>]*>(.*?)<\/a>/is';
    $innerMatches = [];
    $hasInner = preg_match($innerPattern, $body, $innerMatches);
    expect($hasInner)->toBe(1, "Could not capture the inner text of public-header-brand.");
    $innerText = trim(strip_tags(html_entity_decode($innerMatches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    expect($innerText)->toContain(config('app.name'));
});
