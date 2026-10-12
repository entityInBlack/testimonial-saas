<?php

use App\Models\User;

/**
 * Wave 1, Task B (B2): "New Space" follows D3.
 *
 * TB5: the "New Space" link (which carries data-testid="new-space-link")
 * has bg-indigo-600 in its class and no "uppercase" class.
 *
 * Would fail if: the link is hand-written with the old gray-800 or
 * uppercase tracking-widest style, or the test id is removed.
 */
test('the "New Space" link is an indigo D3 primary button (no uppercase)', function () {
    $user = User::factory()->create();

    // A GET renders the full page (layout + header slot);
    // Livewire::test()->html() renders only the component view.
    $html = $this->actingAs($user)
        ->get('/spaces')
        ->assertOk()
        ->getContent();

    // Pull out the new-space-link element and check its class.
    preg_match('#<a[^>]*data-testid="new-space-link"[^>]*>#', $html, $m);
    expect($m)->not->toBeEmpty('new-space-link anchor must exist when not at cap');

    $tag = $m[0];

    expect($tag)
        ->toContain('bg-indigo-600')
        ->not->toContain('uppercase')
        ->not->toContain('tracking-widest');
});
