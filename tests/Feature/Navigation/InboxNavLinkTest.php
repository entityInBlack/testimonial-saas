<?php

use App\Models\User;

/**
 * Branch polish-nav: the "Inbox" link must appear in the logged-in
 * top navigation (desktop + responsive) and must NOT leak onto the
 * public landing page.
 */

test('the logged-in nav contains the inbox link exactly twice', function () {
    // Would fail if: either the desktop or responsive menu lacks the
    // Inbox link, the href is hardcoded to a wrong URL, or the link
    // appears a third time somewhere in the rendered page.
    $user = User::factory()->create();

    $inboxHref = route('inbox.index');
    $needle = 'href="'.$inboxHref.'"';

    $response = $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();

    $content = $response->getContent();
    expect(substr_count($content, $needle))->toBe(2);
});

test('guests on the public landing page do not see the inbox link', function () {
    // Would fail if: the Inbox link leaked onto the public landing
    // page (i.e. the nav (or any other view) renders it for guests).
    $inboxHref = route('inbox.index');
    $needle = 'href="'.$inboxHref.'"';

    $response = $this->get(route('landing'))
        ->assertOk();

    $content = $response->getContent();
    expect(substr_count($content, $needle))->toBe(0);
});
