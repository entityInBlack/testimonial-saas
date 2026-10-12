<?php

use App\Models\User;

/**
 * T2 — logged-in /dashboard shell.
 *
 * The app navigation (livewire/layout/navigation.blade.php) replaces
 * the Laravel logo with the brand wordmark. A logged-in user on the
 * dashboard must see the wordmark linking to route('dashboard') and
 * must NOT see the Laravel logo or a remote font link.
 *
 * Would fail if:
 *   - the Laravel logo is still rendered in the nav (or anywhere in
 *     the layout chain),
 *   - the fonts.bunny.net link is still in the layout,
 *   - the wordmark is removed or its link target changes.
 */

test('T2: logged-in /dashboard renders the wordmark, no logo svg, no remote font', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertStatus(200);

    $body = (string) $response->getContent();

    // No remote font link.
    expect($body)->not->toContain('fonts.bunny.net');

    // No Laravel logo.
    expect($body)->not->toContain('viewBox="0 0 316 316"');

    // Wordmark link to the dashboard route (the app navigation uses
    // context="app", so href is route('dashboard')).
    $needle = '<a href="'.route('dashboard').'"';
    expect(substr_count($body, $needle))->toBeGreaterThanOrEqual(1);
    expect($body)->toContain(config('app.name'));
});
