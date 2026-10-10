<?php

/**
 * embed.js static checks (Step 7, test 22 + follow-up T9).
 *
 * Pest cannot run browser JS. The real render is checked manually
 * (see scripts/step7-fixture.php + C:\11111\embed-test.html). This
 * file proves the SCRIPT FILE itself is safe: no innerHTML, no
 * outerHTML, no insertAdjacentHTML, no document.write, no backtick
 * character, no `shadowRoot.style` assignment, YES contains
 * textContent / data-testimonial-space / attachShadow, AND the
 * file is reachable at the /embed.js route with the right headers.
 */

test('embed.js is reachable via the /embed.js route and contains the required safe-rendering markers', function () {
    $path = public_path('embed.js');
    expect(is_file($path))->toBeTrue();

    $src = file_get_contents($path);
    expect($src)->not->toBeFalse();

    // Forbidden tokens (anywhere in the file — including comments).
    expect($src)->not->toContain('innerHTML')
        ->and($src)->not->toContain('outerHTML')
        ->and($src)->not->toContain('insertAdjacentHTML')
        ->and($src)->not->toContain('document.write')
        ->and($src)->not->toContain('`');

    // T9: ShadowRoot has no .style property, so any assignment to
    // `shadowRoot.style` throws TypeError. The file must not
    // contain that pattern.
    expect($src)->not->toContain('shadowRoot.style');

    // T9: the file uses attachShadow (the prescribed way to isolate).
    // The fix switched from a `host.style = ...` approach to a
    // CSS <style> element built with `styleEl.textContent = ...`
    // — the latter is fine, the former was the bug.
    expect($src)->toContain('attachShadow');

    // Required markers.
    expect($src)->toContain('textContent')
        ->and($src)->toContain('data-testimonial-space');
});

test('GET /embed.js returns 200 with the right content type and a short cache header', function () {
    $response = $this->get('/embed.js');
    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toStartWith('application/javascript')
        ->and((string) $response->headers->get('Cache-Control'))->toContain('max-age=60');
});
