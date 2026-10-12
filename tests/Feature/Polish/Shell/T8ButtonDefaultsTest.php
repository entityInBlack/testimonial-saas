<?php

use Illuminate\Support\Facades\Blade;

/**
 * T8 — button components default to the right HTML type.
 *
 * Wave 0a already wrote the three buttons with the right defaults.
 * Wave 0b (F3) confirms the contract:
 *   - <x-primary-button>        renders <button type="submit" ...>
 *   - <x-danger-button>         renders <button type="submit" ...>
 *   - <x-secondary-button>      renders <button type="button" ...>
 *
 * A caller-supplied type overrides the default (merge order).
 * The rendered <button> tag must carry exactly one type attribute.
 *
 * Would fail if:
 *   - a default is wrong (e.g. primary becomes "button"),
 *   - the merge order changes so the caller cannot override,
 *   - a render accidentally produces two `type=` attributes on a tag.
 */

function buttonOpeningTag(string $html): string
{
    // Capture the first <button ...> opening tag.
    $pattern = '/<button\b([^>]*)>/i';
    $matches = [];
    $found = preg_match($pattern, $html, $matches);
    expect($found)->toBe(1, 'No <button> opening tag found.');

    return $matches[1];
}

function attrValue(string $attrs, string $name): ?string
{
    // Match the attribute (single or double quoted), return the
    // value (with quotes stripped) or null when not present.
    $pattern = '/\b'.preg_quote($name, '/').'\s*=\s*(["\'])(.*?)\1/i';
    $matches = [];
    if (preg_match($pattern, $attrs, $matches) === 1) {
        return $matches[2];
    }

    return null;
}

test('T8a: <x-primary-button> defaults to type="submit"', function () {
    $html = (string) Blade::render('<x-primary-button>Save</x-primary-button>');
    $attrs = buttonOpeningTag($html);

    expect(attrValue($attrs, 'type'))->toBe('submit');
    // Exactly one type attribute.
    expect(substr_count($attrs, 'type='))->toBe(1, 'rendered <button> must have exactly one type attribute.');
});

test('T8b: <x-danger-button> defaults to type="submit"', function () {
    $html = (string) Blade::render('<x-danger-button>Delete</x-danger-button>');
    $attrs = buttonOpeningTag($html);

    expect(attrValue($attrs, 'type'))->toBe('submit');
    expect(substr_count($attrs, 'type='))->toBe(1, 'rendered <button> must have exactly one type attribute.');
});

test('T8c: <x-secondary-button> defaults to type="button"', function () {
    $html = (string) Blade::render('<x-secondary-button>Cancel</x-secondary-button>');
    $attrs = buttonOpeningTag($html);

    expect(attrValue($attrs, 'type'))->toBe('button');
    expect(substr_count($attrs, 'type='))->toBe(1, 'rendered <button> must have exactly one type attribute.');
});

test('T8d: a caller-supplied type overrides the danger default and the tag still has exactly one type attribute', function () {
    // Caller wants a danger button that does NOT submit the form
    // (e.g. a Delete trigger on its own). The merge must respect
    // the caller's value and not duplicate the attribute.
    $html = (string) Blade::render('<x-danger-button type="button">Delete</x-danger-button>');
    $attrs = buttonOpeningTag($html);

    expect(attrValue($attrs, 'type'))->toBe('button');
    expect(substr_count($attrs, 'type='))->toBe(1, 'rendered <button> must have exactly one type attribute.');
});