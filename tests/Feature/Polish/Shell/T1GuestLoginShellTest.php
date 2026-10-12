<?php

/**
 * T1 — guest page /login shell.
 *
 * Wave 0a: the shared public header + footer live in the guest layout
 * (layouts/guest.blade.php). The login page uses that layout, so a
 * guest visiting /login must see the wordmark, a Login link (the page
 * itself), and a Register link — and must NOT see the Laravel logo or
 * a remote font link.
 *
 * Would fail if:
 *   - the Laravel logo svg leaks into the guest layout (its viewBox
 *     would appear in the body),
 *   - the fonts.bunny.net link is still in the layout,
 *   - the wordmark link to route('landing') or any of the auth links
 *     disappear.
 *
 * Counts are deliberately NOT asserted: wave 0b adds more links to the
 * login page, so the test must keep passing after that.
 */

test('T1: guest /login renders the shared shell: wordmark, login link, register link, no logo svg, no remote font', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);

    $body = (string) $response->getContent();

    // No remote font link.
    expect($body)->not->toContain('fonts.bunny.net');

    // No Laravel logo (the SVG with viewBox 0 0 316 316).
    expect($body)->not->toContain('viewBox="0 0 316 316"');

    // Wordmark link to landing with the app name as its text.
    expect($body)->toContain('href="'.route('landing').'"');
    expect($body)->toContain(config('app.name'));

    // Login and register links are present.
    expect($body)->toContain('href="'.route('login').'"');
    expect($body)->toContain('href="'.route('register').'"');
});
