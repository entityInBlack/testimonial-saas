<?php

use App\Models\User;

/**
 * T4 — app navigation dividers.
 *
 * The Log Out item is separated from Profile by a divider in BOTH the
 * desktop dropdown AND the mobile (responsive) menu. The divider is a
 * thin <div data-testid="…"> so it can be pinned by a test.
 *
 * Would fail if:
 *   - either divider is missing from the rendered dashboard HTML,
 *   - either divider is rendered more than once (which would be a
 *     double-divider bug).
 */

test('T4: the logged-in dashboard HTML contains the desktop and the responsive nav dividers exactly once each', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertStatus(200);

    $body = (string) $response->getContent();

    // The desktop dropdown divider.
    $desktopCount = substr_count($body, 'data-testid="nav-dropdown-divider"');
    expect($desktopCount)->toBe(1, "Expected exactly 1 nav-dropdown-divider, got {$desktopCount}.");

    // The mobile (responsive) menu divider.
    $responsiveCount = substr_count($body, 'data-testid="nav-responsive-divider"');
    expect($responsiveCount)->toBe(1, "Expected exactly 1 nav-responsive-divider, got {$responsiveCount}.");
});
