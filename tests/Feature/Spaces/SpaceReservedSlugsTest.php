<?php

use App\Models\Space;

/*
 * Pins the exact set of reserved slugs that protect Breeze/app routes
 * from being shadowed by a public `/s/{slug}` page. If anyone adds or
 * removes a reserved word, this test fails.
 */
test('Space::RESERVED_SLUGS is exactly the pinned 10-word set', function () {
    expect(Space::RESERVED_SLUGS)->toBe([
        's',
        'api',
        'admin',
        'login',
        'register',
        'dashboard',
        'billing',
        'embed',
        'assets',
        'up',
    ]);
});

test('Space::RESERVED_SLUGS contains no duplicates and is non-empty', function () {
    expect(Space::RESERVED_SLUGS)->not->toBeEmpty();
    expect(count(Space::RESERVED_SLUGS))->toBe(count(array_unique(Space::RESERVED_SLUGS)));
});
