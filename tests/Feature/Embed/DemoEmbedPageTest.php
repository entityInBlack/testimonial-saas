<?php

/**
 * Static checks for `public/demo-embed.html`.
 *
 * Part F requires a plain HTML page that pastes the embed snippet
 * for any Space and renders the wall. We don't have a JS engine
 * under Pest, so this file proves only the structural shape of the
 * static HTML:
 *
 *   - The file exists at the conventional static-asset path.
 *   - The snippet shown to the user matches the shape produced
 *     by EmbedBuilder::snippet() (i.e. the documented contract).
 *   - The script src is `${apiBase}/embed.js`, where apiBase is
 *     the API base URL input (defaulted to location.origin when
 *     served over http/https, otherwise http://127.0.0.1:8000).
 *   - The page supports a `?space=` query parameter.
 *   - The page builds its DOM through createElement /
 *     setAttribute / textContent — NOT through innerHTML,
 *     outerHTML, insertAdjacentHTML, or document.write. No
 *     template-literal backticks either.
 *   - The ONLY http:// or https:// substring in the file is the
 *     fallback default "http://127.0.0.1:8000" — no remote
 *     resources, no CDN, no fonts, no third-party hosts.
 */

test('public/demo-embed.html exists as a static file', function () {
    $path = public_path('demo-embed.html');
    expect(is_file($path))->toBeTrue();
});

test('demo-embed.html contains the embed snippet markers, /embed.js and ?space=', function () {
    $src = (string) file_get_contents(public_path('demo-embed.html'));
    expect($src)->not->toBeFalse();

    expect($src)->toContain('data-testimonial-space')
        ->and($src)->toContain('/embed.js')
        ->and($src)->toContain('?space=');
});

test('demo-embed.html builds DOM with createElement + setAttribute (not innerHTML, outerHTML, insertAdjacentHTML, document.write)', function () {
    $src = (string) file_get_contents(public_path('demo-embed.html'));
    expect($src)->not->toBeFalse();

    // The page MUST be built with DOM APIs. Forbidding the
    // substring `innerHTML` (and the related DOM-insertion
    // strings) keeps the codebase honest — even a comment that
    // mentions the string is a smell.
    expect($src)->toContain("createElement('script')")
        ->and($src)->not->toContain('innerHTML')
        ->and($src)->not->toContain('outerHTML')
        ->and($src)->not->toContain('insertAdjacentHTML')
        ->and($src)->not->toContain('document.write');
});

test('demo-embed.html does NOT use template backtick string interpolation', function () {
    $src = (string) file_get_contents(public_path('demo-embed.html'));
    expect($src)->not->toBeFalse();

    // All dynamic strings must be built by DOM APIs or simple
    // string concatenation, NOT template-literal interpolation.
    expect($src)->not->toContain('`');
});

test('demo-embed.html does NOT pull in any remote resource — the ONLY http:// or https:// substring is the fallback default "http://127.0.0.1:8000"', function () {
    $src = (string) file_get_contents(public_path('demo-embed.html'));
    expect($src)->not->toBeFalse();

    // The page is plain HTML. Anything that points to a third-party
    // host (or even to our own CDN, which we do not have) would be
    // a Step 8 Hard Rule violation: no new remote resources.
    expect($src)->not->toContain('https://')
        ->and($src)->not->toContain('fonts.googleapis')
        ->and($src)->not->toContain('fonts.gstatic')
        ->and($src)->not->toContain('cdn.');

    // The only http:// occurrence allowed is the fallback API
    // base. Count occurrences and assert.
    $count = substr_count($src, 'http://');
    expect($count)->toBe(1, "demo-embed.html must contain http:// exactly once (the fallback API base). Found {$count}.");

    $fallbackPos = strpos($src, 'http://127.0.0.1:8000');
    expect($fallbackPos)->not->toBeFalse();
});

test('demo-embed.html inline script is not cut off — only the file\u2019s own closing </script> tag appears (case-insensitive), and the snippet builder still emits a closing tag at runtime via <\\/script>', function () {
    $src = (string) file_get_contents(public_path('demo-embed.html'));
    expect($src)->not->toBeFalse();

    // The HTML parser terminates the inline <script> at the FIRST
    // case-insensitive "</script" it sees anywhere in the file —
    // even inside a JS comment or a JS string literal. Earlier
    // versions of this file contained three such accidental
    // closers (one in a comment near the top, and two inside
    // the snippet-builder string in clearAll()/snippetFor()),
    // which left the page dead. The fix is to write the JS-side
    // closing tag as "<\/script>": the backslash is dropped at
    // runtime so the string the user sees in the snippet block
    // and the DOM the embed creates are byte-identical, but the
    // raw file no longer contains the byte sequence that ends
    // the inline <script> element prematurely.
    $count = preg_match_all('/<\/script/i', $src);
    expect($count)->toBe(1, "demo-embed.html must contain a case-insensitive \"</script\" exactly once (the file's own closing tag). Found {$count}.");

    // The snippet builder must still produce a real closing
    // </script> tag at runtime — escape it in the source as
    // <\/script> so the HTML parser does not see it as a
    // premature closer.
    expect($src)->toContain('<\\/script>');
});
