<?php

use App\Models\Space;

/**
 * T9 — the guest layout draws ONE card and only when not cardless.
 *
 * Wave 0b: data-testid="guest-card" is added to the white card
 * element the guest layout draws in its DEFAULT (not cardless)
 * branch. The cardless pages (the public submission form and the
 * deletion request form) opt out via the #[Layout] attribute, so
 * the layout's default card is suppressed and the page draws its
 * own card instead.
 *
 * What this test pins:
 *   - /login                                  -> guest-card exactly once
 *   - /s/<slug of a factory Space>            -> guest-card ZERO times
 *                                              (the shared public-header
 *                                               is still present)
 *   - /privacy/request-deletion               -> guest-card ZERO times
 *                                              (the shared public-header
 *                                               is still present)
 *
 * Would fail if:
 *   - the layout's default card stops carrying data-testid="guest-card"
 *     and an outer layer silently adds one,
 *   - the cardless opt-out is dropped (so /s/<slug> shows two cards),
 *   - the shared public header is removed from a guest form page
 *     (it must still be there).
 */

test('T9a: /login contains data-testid="guest-card" exactly once', function () {
    $response = $this->get('/login');
    $response->assertStatus(200);
    $body = (string) $response->getContent();

    $count = substr_count($body, 'data-testid="guest-card"');
    expect($count)->toBe(1, "Expected exactly 1 data-testid=\"guest-card\" on /login, got {$count}.");
});

test('T9b: /s/{slug} contains no guest-card but the shared public header is present', function () {
    $space = Space::factory()->create(['rating_enabled' => false]);
    $response = $this->get('/s/'.$space->slug);
    $response->assertStatus(200);
    $body = (string) $response->getContent();

    // No layout card (cardless opt-out in effect).
    $cardCount = substr_count($body, 'data-testid="guest-card"');
    expect($cardCount)->toBe(0, "Expected 0 data-testid=\"guest-card\" on /s/{slug}, got {$cardCount}.");

    // The shared public header is still there, with its public-header-header
    // test id (default testIdPrefix on the guest layout's <x-public-header>).
    $headerCount = substr_count($body, 'data-testid="public-header-header"');
    expect($headerCount)->toBe(1, "Expected 1 data-testid=\"public-header-header\" on /s/{slug}, got {$headerCount}.");
});

test('T9c: /privacy/request-deletion contains no guest-card but the shared public header is present', function () {
    $response = $this->get('/privacy/request-deletion');
    $response->assertStatus(200);
    $body = (string) $response->getContent();

    $cardCount = substr_count($body, 'data-testid="guest-card"');
    expect($cardCount)->toBe(0, "Expected 0 data-testid=\"guest-card\" on /privacy/request-deletion, got {$cardCount}.");

    $headerCount = substr_count($body, 'data-testid="public-header-header"');
    expect($headerCount)->toBe(1, "Expected 1 data-testid=\"public-header-header\" on /privacy/request-deletion, got {$headerCount}.");
});