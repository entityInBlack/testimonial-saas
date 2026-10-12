<?php

use App\Livewire\Spaces\EmbedBuilder;
use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;
use Livewire\Livewire;

/**
 * Wave 1 / Task A — embed builder polish (#5 preview markers,
 * #7 hint, #22/#25 container/header, #24 copy button).
 *
 * Pest cannot run browser JavaScript, so the embed-side tests live
 * elsewhere (#6 background validation, #8 column width, #16 human
 * dates are all JS-only). These tests assert the SERVER-rendered
 * HTML contract of the builder page itself.
 *
 * Each test states a "would fail if" note so the test can never
 * pass for the wrong reason.
 */

test('A2: masonry vs carousel preview carries distinct layout markers; dark on adds an extra dark marker', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    Testimonial::factory()->for($space)->create([
        'consent_given' => true, 'is_wall_of_love' => true,
    ]);

    // 1. Baseline (masonry, light). The marker data-ts-layout="masonry".
    $r1 = $this->actingAs($user)
        ->get(route('spaces.embed', ['space' => $space->id]));
    $r1->assertOk();
    $html1 = (string) $r1->getContent();
    expect($html1)->toContain('data-ts-layout="masonry"')
        ->and($html1)->toContain('data-ts-dark="off"')
        ->and($html1)->not->toContain('data-ts-layout="carousel"');

    // 2. Save carousel. The marker flips to data-ts-layout="carousel".
    Livewire::actingAs($user)
        ->test(EmbedBuilder::class, ['space' => $space->id])
        ->set('layout', 'carousel')
        ->call('save')
        ->assertHasNoErrors();
    $r2 = $this->actingAs($user)
        ->get(route('spaces.embed', ['space' => $space->id]));
    $r2->assertOk();
    $html2 = (string) $r2->getContent();
    expect($html2)->toContain('data-ts-layout="carousel"')
        ->and($html2)->not->toContain('data-ts-layout="masonry"');

    // 3. Enable dark. An EXTRA dark marker is added.
    Livewire::actingAs($user)
        ->test(EmbedBuilder::class, ['space' => $space->id])
        ->set('darkMode', true)
        ->call('save')
        ->assertHasNoErrors();
    $r3 = $this->actingAs($user)
        ->get(route('spaces.embed', ['space' => $space->id]));
    $r3->assertOk();
    $html3 = (string) $r3->getContent();
    expect($html3)->toContain('data-ts-dark="on"')
        ->and($html3)->toContain('embed-preview-dark')
        ->and($html3)->toContain('data-dark="1"');

    // 4. Switch back to masonry with dark still on — both markers
    //    differ from the baseline state.
    Livewire::actingAs($user)
        ->test(EmbedBuilder::class, ['space' => $space->id])
        ->set('layout', 'masonry')
        ->call('save')
        ->assertHasNoErrors();
    $r4 = $this->actingAs($user)
        ->get(route('spaces.embed', ['space' => $space->id]));
    $r4->assertOk();
    $html4 = (string) $r4->getContent();
    expect($html4)->toContain('data-ts-layout="masonry"')
        ->and($html4)->toContain('data-ts-dark="on"');

    // Would fail if:
    //  - data-ts-layout was missing or did not change between states,
    //  - data-ts-dark was missing or did not flip with dark mode,
    //  - the existing data-layout / data-dark markers were removed
    //    (the existing T7 test still asserts those).
});

test('A4: builder hint now matches the real (input + clamp) behaviour and the old hint text is gone', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();

    $response = $this->actingAs($user)
        ->get(route('spaces.embed', ['space' => $space->id]));
    $response->assertOk();
    $html = (string) $response->getContent();

    // The NEW hint is present.
    expect($html)->toContain('data-testid="item-limit-hint"')
        ->and($html)->toContain('The input is limited to whole numbers between 1 and 50.');

    // The OLD hint text is gone (both the wording and the
    // "clamped to 50" claim the user could no longer reach).
    expect($html)->not->toContain('Values above 50 are clamped to 50')
        ->and($html)->not->toContain('values below 1 become 1');

    // Would fail if:
    //  - the new hint is not rendered,
    //  - the old hint is still rendered (so the copy is wrong),
    //  - the data-testid was removed.
});

test('A7+A8: builder uses the shared page-header and the content container matches the header bar container', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();

    $response = $this->actingAs($user)
        ->get(route('spaces.embed', ['space' => $space->id]));
    $response->assertOk();
    $html = (string) $response->getContent();

    // A7 — the page-header component is used.
    expect($html)->toContain('data-testid="page-header"')
        ->and($html)->toContain('data-testid="page-header-title"')
        ->and($html)->toContain('data-testid="page-header-subtitle"')
        ->and($html)->toContain('data-testid="page-header-actions"');

    // Title and subtitle text.
    expect($html)->toContain('Embed builder')
        ->and($html)->toContain($space->title);

    // "Back to Spaces" lives in the actions slot, with a data-testid
    // the prompt did not forbid and the other tests do not assert.
    expect($html)->toContain('data-testid="embed-back-link"')
        ->and($html)->toContain('Back to Spaces');

    // A8 — content uses the same container as the header bar
    // (layouts/app.blade.php wraps $header in
    //  max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8).
    expect($html)->toContain('max-w-7xl mx-auto px-4 sm:px-6 lg:px-8');

    // Would fail if:
    //  - page-header component is not used (custom header markup),
    //  - title / subtitle text or actions slot is missing,
    //  - the content container does not match the header bar
    //    container (so the left edges drift apart on lg).
});

test('A9: builder renders a copy button and the snippet element holds the exact snippet string', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();

    $response = $this->actingAs($user)
        ->get(route('spaces.embed', ['space' => $space->id]));
    $response->assertOk();
    $html = (string) $response->getContent();

    // The copy button exists with the contract test id.
    expect($html)->toContain('data-testid="embed-copy-button"');

    // The snippet element exists. Its text content is the snippet
    // string Blade computed — Blade auto-escapes for HTML, so the
    // rendered HTML carries the HTML-encoded form. We assert on
    // the encoded form (which round-trips to the original string
    // when the browser reads the <pre>'s textContent).
    $expectedJs = rtrim((string) config('app.url'), '/').'/embed.js';
    $expectedSnippet = '<div data-testimonial-space="'.$space->public_id.'"></div>'."\n"
        .'<script src="'.$expectedJs.'" async></script>';
    $encodedSnippet = htmlspecialchars($expectedSnippet, ENT_QUOTES, 'UTF-8');

    expect($html)->toContain('data-testid="embed-snippet"')
        ->and($html)->toContain($encodedSnippet)
        ->and($html)->not->toContain('data-testimonial-space="'.$space->slug.'"');

    // The button carries the same snippet in a data-snippet
    // attribute (also Blade-encoded) so a click can copy it
    // without re-rendering.
    expect($html)->toContain('data-snippet="'.$encodedSnippet.'"');

    // Would fail if:
    //  - the copy button is missing,
    //  - the snippet element is missing or its content drifted,
    //  - the snippet accidentally includes the slug instead of
    //    the public_id.
});