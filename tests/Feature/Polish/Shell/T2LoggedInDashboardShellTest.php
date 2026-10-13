<?php

use App\Models\User;

/**
 * T2 — logged-in /dashboard shell.
 *
 * The app navigation (livewire/layout/navigation.blade.php) replaces
 * the Laravel logo with the brand wordmark. A logged-in user on the
 * dashboard must see the wordmark (an <a> with
 * data-testid="app-nav-wordmark" that links to the dashboard route
 * and whose inner text is the app name) and must NOT see the Laravel
 * logo or a remote font link.
 *
 * Would fail if:
 *   - the Laravel logo is still rendered in the nav (or anywhere in
 *     the layout chain),
 *   - the fonts.bunny.net link is still in the layout,
 *   - the wordmark is removed, its link target changes, or its inner
 *     text no longer contains the app name.
 *
 * Wave 0b: T2 is tightened to find the anchor by regex on its
 * data-testid, the way tests/Feature/Submission/BackHomeLinkTest.php
 * does, instead of the previous whole-page "contains the URL / app
 * name" assertions that also passed because of the <title> tag and
 * other links.
 */

test('T2: logged-in /dashboard renders the wordmark anchor pointing to route(dashboard) with the app name, no logo svg, no remote font', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertStatus(200);

    $body = (string) $response->getContent();

    // No remote font link.
    expect($body)->not->toContain('fonts.bunny.net');

    // No Laravel logo.
    expect($body)->not->toContain('viewBox="0 0 316 316"');

    // Find the <a data-testid="app-nav-wordmark" ...> opening tag
    // by regex. The match count must be exactly 1, and the matched
    // tag's href must equal route('dashboard').
    $pattern = '/<a\b[^>]*\bdata-testid="app-nav-wordmark"[^>]*>/i';
    $matches = [];
    $found = preg_match_all($pattern, $body, $matches);
    expect($found)->toBe(1, "Expected to find exactly 1 <a data-testid=\"app-nav-wordmark\">, found {$found}.");

    $tag = $matches[0][0];

    // href must equal route('dashboard').
    $hrefPattern = '/\bhref=(["\'])(.*?)\1/i';
    $hrefMatches = [];
    $hasHref = preg_match($hrefPattern, $tag, $hrefMatches);
    expect($hasHref)->toBe(1, "app-nav-wordmark <a> has no href attribute. Tag was: {$tag}");
    expect($hrefMatches[2])->toBe(route('dashboard'));

    // The inner text of the matching anchor must contain the app name.
    $innerPattern = '/<a\b[^>]*\bdata-testid="app-nav-wordmark"[^>]*>(.*?)<\/a>/is';
    $innerMatches = [];
    $hasInner = preg_match($innerPattern, $body, $innerMatches);
    expect($hasInner)->toBe(1, "Could not capture the inner text of app-nav-wordmark.");
    $innerText = trim(strip_tags(html_entity_decode($innerMatches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    expect($innerText)->toContain(config('app.name'));
});
