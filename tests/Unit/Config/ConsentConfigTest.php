<?php

test('consent.current points at a published version', function () {
    $current = config('consent.current');
    $texts = config('consent.texts');

    expect($current)->toBeString();
    expect(array_key_exists($current, $texts))->toBeTrue("consent.current='$current' has no matching text");
});

test('every published consent version is a non-empty string', function () {
    foreach (config('consent.texts') as $version => $text) {
        expect($version)->toBeString("version key is not a string: ".var_export($version, true));
        expect($text)->toBeString("consent.texts.$version is not a string");
        expect(trim($text))->not->toBeEmpty("consent.texts.$version is empty");
    }
});

test('published consent versions are immutable — once shipped, the version key stays', function () {
    // Data-model §7.4: published versions are never edited. The test
    // asserts the v1 key still exists (changing the key would silently
    // rewrite what respondents agreed to).
    expect(array_key_exists('v1', config('consent.texts')))->toBeTrue();
});