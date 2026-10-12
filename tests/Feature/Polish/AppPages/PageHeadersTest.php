<?php

use App\Models\User;

/**
 * Wave 1, Task B (B1): every app page uses <x-page-header>.
 *
 * TB2: for a logged-in user, GET /dashboard, /spaces, /inbox and
 * /profile each contain data-testid="page-header" exactly once.
 *
 * Would fail if: any of those pages falls back to a hand-written
 * header (or skips the component), or a page renders the test id
 * more than once.
 */
test('/dashboard renders data-testid="page-header" exactly once', function () {
    $user = User::factory()->create();

    $html = (string) $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->getContent();

    expect(substr_count($html, 'data-testid="page-header"'))->toBe(1);
});

test('/spaces renders data-testid="page-header" exactly once', function () {
    $user = User::factory()->create();

    $html = (string) $this->actingAs($user)
        ->get(route('spaces.index'))
        ->assertOk()
        ->getContent();

    expect(substr_count($html, 'data-testid="page-header"'))->toBe(1);
});

test('/inbox renders data-testid="page-header" exactly once', function () {
    $user = User::factory()->create();

    $html = (string) $this->actingAs($user)
        ->get(route('inbox.index'))
        ->assertOk()
        ->getContent();

    expect(substr_count($html, 'data-testid="page-header"'))->toBe(1);
});

test('/profile renders data-testid="page-header" exactly once', function () {
    $user = User::factory()->create();

    $html = (string) $this->actingAs($user)
        ->get(route('profile'))
        ->assertOk()
        ->getContent();

    expect(substr_count($html, 'data-testid="page-header"'))->toBe(1);
});
