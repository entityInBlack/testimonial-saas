<?php

use App\Models\User;

/**
 * T3 — landing page auth links.
 *
 * The shared public header on the landing page shows Login for guests
 * and Dashboard for logged-in users. The two must not appear at the
 * same time.
 *
 * Would fail if:
 *   - the guest landing page keeps showing the "Dashboard" link or
 *     loses the "Log in" link,
 *   - the logged-in landing page shows "Log in" / "Create account"
 *     in the public header (the user is already in). The hero
 *     "Get started" CTA still links to /register, so the register
 *     href is NOT asserted in this test.
 */

test('T3a: guest on landing sees a Log in link and no Dashboard link', function () {
    $response = $this->get(route('landing'));

    $response->assertStatus(200);

    $body = (string) $response->getContent();

    // Guest sees Login.
    expect($body)->toContain('href="'.route('login').'"');

    // Guest does not see Dashboard in the public header.
    expect($body)->not->toContain('href="'.route('dashboard').'"');
});

test('T3b: logged-in user on landing sees a Dashboard link and no Log in link in the public header', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('landing'));

    $response->assertStatus(200);

    $body = (string) $response->getContent();

    // Logged-in sees Dashboard.
    expect($body)->toContain('href="'.route('dashboard').'"');

    // Logged-in does not see Login in the public header.
    // (The hero CTA still links to /register; that is by design.)
    expect($body)->not->toContain('href="'.route('login').'"');
});
