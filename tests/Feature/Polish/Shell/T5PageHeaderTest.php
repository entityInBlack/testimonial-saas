<?php

use Illuminate\Support\Facades\Blade;

/**
 * T5 — page header component.
 *
 * The shared page-header component (components/page-header.blade.php)
 * renders a title, an optional subtitle and an actions slot. The
 * responsive stacking rule (R7) is styled via Tailwind classes; this
 * test only checks the HTML contract.
 *
 * Would fail if:
 *   - the title text is missing,
 *   - the actions slot is missing,
 *   - the subtitle element is rendered when no subtitle is provided
 *     (the component must use @isset / @if on the prop, not on the
 *     slot, so a missing subtitle does not emit an empty element),
 *   - the subtitle text is missing when a subtitle is provided.
 */

test('T5: page header renders title, optional subtitle, and an actions slot', function () {
    $html = (string) Blade::render(
        '<x-page-header :title="$title" :subtitle="$subtitle">'
        .'<a href="/x">Action</a>'
        .'</x-page-header>',
        ['title' => 'Spaces', 'subtitle' => 'One per page or service.']
    );

    // Title appears.
    expect($html)->toContain('Spaces');

    // Subtitle appears.
    expect($html)->toContain('One per page or service.');

    // Actions slot appears.
    expect($html)->toContain('Action');
    expect($html)->toContain('href="/x"');
});

test('T5: page header without a subtitle does not render a subtitle element', function () {
    $html = (string) Blade::render(
        '<x-page-header :title="$title" />',
        ['title' => 'Spaces']
    );

    expect($html)->toContain('Spaces');

    // No subtitle testid on the rendered output.
    expect($html)->not->toContain('data-testid="page-header-subtitle"');
});
