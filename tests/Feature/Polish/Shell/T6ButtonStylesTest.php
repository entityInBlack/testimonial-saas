<?php

use Illuminate\Support\Facades\Blade;

/**
 * T6 — button components.
 *
 * D3: indigo primary, sentence case, no uppercase / tracking-widest.
 * R5: focus ring visible; below sm every button is at least 44 px tall.
 *
 * Would fail if:
 *   - any of the three button components renders an `uppercase` class
 *     (the Breeze gray-800 uppercase style would be back),
 *   - the primary does not use bg-indigo-600,
 *   - the danger does not use bg-red-600,
 *   - the secondary does not use the white surface style.
 */

test('T6a: primary-button uses indigo and is sentence case', function () {
    $html = (string) Blade::render('<x-primary-button>Save</x-primary-button>');

    expect($html)->toContain('bg-indigo-600');
    expect($html)->not->toContain('uppercase');
    // Touch target: 44 px below sm.
    expect($html)->toContain('min-h-[44px]');
});

test('T6b: secondary-button is sentence case with a white surface', function () {
    $html = (string) Blade::render('<x-secondary-button>Cancel</x-secondary-button>');

    expect($html)->toContain('bg-white');
    expect($html)->not->toContain('uppercase');
    expect($html)->toContain('min-h-[44px]');
});

test('T6c: danger-button uses red and is sentence case', function () {
    $html = (string) Blade::render('<x-danger-button>Delete</x-danger-button>');

    expect($html)->toContain('bg-red-600');
    expect($html)->not->toContain('uppercase');
    expect($html)->toContain('min-h-[44px]');
});
