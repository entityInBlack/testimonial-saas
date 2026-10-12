<?php

use App\Models\Space;

/**
 * T11 — guest submit buttons use the shared primary-button style.
 *
 * Wave 0b (G1, G2): the /s/{slug} form and the /privacy/request-deletion
 * form both use the shared <x-primary-button> component for their
 * submit button (instead of hand-writing the indigo style). T11
 * asserts the rendered opening tag of the element with the right
 * test id contains bg-indigo-600 (the primary-button color) and does
 * NOT contain the old "uppercase" class (Breeze's all-caps style).
 *
 * Would fail if:
 *   - someone reverts the submit to a hand-written gray or uppercase
 *     button,
 *   - the shared primary-button component silently drops bg-indigo-600.
 */

test('T11a: /s/{slug} — the submit-button element uses bg-indigo-600 and is not uppercase', function () {
    $space = Space::factory()->create(['rating_enabled' => false]);
    $response = $this->get('/s/'.$space->slug);
    $response->assertStatus(200);
    $body = (string) $response->getContent();

    // Match the opening tag of the element with data-testid="submit-button".
    $pattern = '/<\w+\b[^>]*\bdata-testid="submit-button"[^>]*>/i';
    $matches = [];
    $found = preg_match_all($pattern, $body, $matches);
    expect($found)->toBe(1, "Expected exactly 1 element with data-testid=\"submit-button\" on /s/{slug}, found {$found}.");

    $tag = $matches[0][0];
    expect($tag)->toContain('bg-indigo-600');
    expect($tag)->not->toContain('uppercase');
});

test('T11b: /privacy/request-deletion — the deletion-submit element uses bg-indigo-600 and is not uppercase', function () {
    $response = $this->get('/privacy/request-deletion');
    $response->assertStatus(200);
    $body = (string) $response->getContent();

    $pattern = '/<\w+\b[^>]*\bdata-testid="deletion-submit"[^>]*>/i';
    $matches = [];
    $found = preg_match_all($pattern, $body, $matches);
    expect($found)->toBe(1, "Expected exactly 1 element with data-testid=\"deletion-submit\" on /privacy/request-deletion, found {$found}.");

    $tag = $matches[0][0];
    expect($tag)->toContain('bg-indigo-600');
    expect($tag)->not->toContain('uppercase');
});