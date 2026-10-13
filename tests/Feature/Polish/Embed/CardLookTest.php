<?php

/**
 * Part 3 — Card look tests for the three testimonial surfaces:
 * the Inbox row, the embed builder preview, and the public widget
 * (public/embed.js).
 *
 * Pest cannot run CSS or JavaScript. These tests guard the SOURCE
 * of the three card implementations (Tailwind class names in the
 * Blade files, and the CSS string built inside embed.js). The
 * real visual check is the user's browser check.
 *
 * Every helper function in this file is prefixed `tcl` (Pest
 * helpers are global). The amber class chosen for filled stars in
 * the two Blade files is `text-amber-500`; the embed.js uses
 * `#f59e0b` directly (no Tailwind class needed). That choice is
 * stated in the relevant tests' comments so a future editor knows
 * which string to look for.
 */

use App\Livewire\Inbox\InboxRow;
use App\Models\EmbedConfiguration;
use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;
use Livewire\Livewire;

/* --------------------------------------------------------------------- */
/* Helpers                                                              */
/* --------------------------------------------------------------------- */

/**
 * Return the absolute path of the embed.js file. Centralised so
 * the tests do not repeat base_path() / public_path() in every
 * assertion.
 */
function tclEmbedJsPath(): string
{
    return public_path('embed.js');
}

/**
 * Read the raw text of public/embed.js. Throws a clear failure
 * (Pest's expectation surfaces it) if the file is missing.
 */
function tclEmbedJsSource(): string
{
    $path = tclEmbedJsPath();
    $src = file_get_contents($path);
    // file_get_contents can return false on a missing file. The
    // static test already proves the file exists; we still defend
    // against a transient read failure here.
    if ($src === false) {
        test()->fail('Could not read public/embed.js from '.$path);
    }
    return (string) $src;
}

/**
 * Extract every `.ts-card{...}` block from the embed.js source
 * text. The braces are matched by counting, so the regex below
 * only finds the OPENING position; the brace-counting loop in
 * tclCardBlocks() then walks the file once and slices each block.
 */
function tclCardBlocks(string $src): array
{
    $blocks = [];
    $offset = 0;
    while (true) {
        $pos = strpos($src, '.ts-card{', $offset);
        if ($pos === false) {
            break;
        }
        $start = $pos + strlen('.ts-card');
        $depth = 0;
        $end = -1;
        $len = strlen($src);
        for ($i = $start; $i < $len; $i++) {
            $ch = $src[$i];
            if ($ch === '{') {
                $depth++;
            } elseif ($ch === '}') {
                $depth--;
                if ($depth === 0) {
                    $end = $i;
                    break;
                }
            }
        }
        if ($end === -1) {
            break;
        }
        $blocks[] = substr($src, $start, $end - $start + 1);
        $offset = $end + 1;
    }
    return $blocks;
}

/**
 * Return the rendered Inbox row HTML for the given testimonial
 * and optional show* flags. Mirrors how InboxTrashTabTest mounts
 * the row component directly.
 */
function tclInboxRowHtml(Testimonial $row, ?User $user = null, bool $showRestore = false, bool $showForget = false, bool $showSoftDelete = true): string
{
    $user = $user ?: User::factory()->create();
    $html = (string) Livewire::actingAs($user)
        ->test(InboxRow::class, [
            'testimonialId' => $row->id,
            'showRestore' => $showRestore,
            'showForget' => $showForget,
            'showSoftDelete' => $showSoftDelete,
        ])
        ->html();
    return $html;
}

/**
 * Return the rendered embed builder HTML for the given space.
 * Optionally save an EmbedConfiguration row first.
 */
function tclBuilderHtml(Space $space, ?User $user = null, ?array $configOverrides = null): string
{
    $user = $user ?: User::factory()->create();
    if ($configOverrides !== null) {
        EmbedConfiguration::factory()->for($space)->create($configOverrides);
    }
    $response = test()->actingAs($user)
        ->get(route('spaces.embed', ['space' => $space->id]));
    $response->assertOk();
    return (string) $response->getContent();
}

/**
 * Return the opening tag of the FIRST element whose `data-testid`
 * attribute is `$testid`, walking the HTML byte-by-byte. The
 * opening tag may span multiple lines (the copy button carries a
 * long x-data / x-on:click payload that the renderer splits over
 * many lines), so a single-line regex is not enough. Returns ''
 * if the testid is not present.
 */
function tclOpeningTagByTestId(string $html, string $testid): string
{
    $needle = 'data-testid="'.$testid.'"';
    $tidPos = strpos($html, $needle);
    if ($tidPos === false) {
        return '';
    }
    // Walk backwards to find the opening '<' of the same tag.
    $before = substr($html, 0, $tidPos);
    $ltPos = strrpos($before, '<');
    if ($ltPos === false) {
        return '';
    }
    // Walk forwards to find the '>' that closes the opening tag.
    $gtPos = strpos($html, '>', $tidPos);
    if ($gtPos === false) {
        return '';
    }
    return substr($html, $ltPos, $gtPos - $ltPos + 1);
}

/* --------------------------------------------------------------------- */
/* TCL1 — embed.js CSS carries the card look                              */
/* --------------------------------------------------------------------- */

test('TCL1: embed.js carries the card look in its CSS source', function () {
    // Would fail if: any of the required tokens disappears from
    // buildCss(), or the animation declaration drifts to `forwards`
    // / `both`, or `will-change` / `filter` are introduced.
    $src = tclEmbedJsSource();

    // Required tokens.
    expect($src)->toContain('border-radius:16px')
        ->and($src)->toContain('#f59e0b')
        ->and($src)->toContain('#d1d5db')
        ->and($src)->toContain('min-height:44px')
        ->and($src)->toContain('prefers-reduced-motion')
        ->and($src)->toContain(':focus-visible')
        ->and($src)->toContain('backwards');

    // The exact animation declaration is present.
    expect($src)->toContain('animation:ts-fade 280ms ease-out backwards');

    // `forwards` is forbidden (the spec calls out backwards, not
    // forwards). The word `forwards` may legitimately appear as
    // part of other identifiers; we only reject the *animation
    // fill mode* " forwards" / " forwards;" / "forwards " after
    // the keyword `ease-out`.
    expect($src)->not->toContain('ease-out forwards')
        ->and($src)->not->toContain('ease-out both');

    // `both` as an animation fill mode is forbidden. The substring
    // `both` can appear inside other identifiers or words, so we
    // check for the animation pattern only.
    expect($src)->not->toContain('ease-out both');

    // `will-change` and `filter` are forbidden everywhere.
    expect($src)->not->toContain('will-change')
        ->and($src)->not->toContain('filter');
});

/* --------------------------------------------------------------------- */
/* TCL2 — base .ts-card rule keeps a plain resting state                  */
/* --------------------------------------------------------------------- */

test('TCL2: embed.js base .ts-card rule keeps a plain resting state (no opacity, no transform)', function () {
    // Would fail if: a base .ts-card{...} rule (one that is NOT
    // the animation rule) sets opacity or transform, which would
    // make the card invisible or shifted by default.
    //
    // What this test proves: in any .ts-card{...} block whose body
    // does NOT contain `@keyframes` or `animation:`, the literal
    // substrings `opacity` and `transform` are absent.
    //
    // What this test does NOT prove: the case where one rule
    // declares `opacity:0;` and a separate rule (the animation)
    // also targets `.ts-card`. In that case the resting state
    // would still be visible only via the animation. The prompt
    // asks the base .ts-card to be plain, and the test below
    // enforces that.
    $src = tclEmbedJsSource();
    $blocks = tclCardBlocks($src);
    expect(count($blocks))->toBeGreaterThan(0);

    foreach ($blocks as $body) {
        $isAnimationRule = (strpos($body, '@keyframes') !== false)
            || (strpos($body, 'animation:') !== false);
        if ($isAnimationRule) {
            continue;
        }
        // Base rule body: must not mention opacity or transform.
        expect(stripos($body, 'opacity'))->toBeFalse()
            ->and(stripos($body, 'transform'))->toBeFalse();
    }
});

/* --------------------------------------------------------------------- */
/* TCL3 — safe-rendering markers                                         */
/* --------------------------------------------------------------------- */

test('TCL3: embed.js still passes the safe-rendering markers', function () {
    // Would fail if: a forbidden token is reintroduced (the same
    // checks EmbedJsStaticTest runs, repeated here so the CardLook
    // test bundle is self-contained).
    $src = tclEmbedJsSource();

    expect($src)->not->toContain('innerHTML')
        ->and($src)->not->toContain('outerHTML')
        ->and($src)->not->toContain('insertAdjacentHTML')
        ->and($src)->not->toContain('document.write')
        ->and($src)->not->toContain('`')
        ->and($src)->not->toContain('shadowRoot.style');

    // Required safe-rendering markers.
    expect($src)->toContain('attachShadow')
        ->and($src)->toContain('textContent')
        ->and($src)->toContain('data-testimonial-space');

    // Carousel nav button labels.
    expect($src)->toContain('Previous')
        ->and($src)->toContain('Next');
});

/* --------------------------------------------------------------------- */
/* TCL4 — builder preview mirrors the widget                              */
/* --------------------------------------------------------------------- */

test('TCL4: builder preview mirrors the widget card look (rounded, stars amber, social safe)', function () {
    // Would fail if: a preview-card opening tag loses rounded-2xl,
    // the preview wrapper loses rounded-2xl, the rated row's stars
    // lose the text-amber-500 class (the class we chose for filled
    // stars) or the ★ glyph, the javascript: URL sneaks into an
    // href, or the snippet element disappears.
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    Testimonial::factory()->for($space)->create([
        'rating' => 4,
        'consent_given' => true,
        'is_wall_of_love' => true,
        'profile_photo' => 'photos/rated.webp',
        'social_url' => 'https://example.test/handle',
    ]);
    Testimonial::factory()->for($space)->create([
        'rating' => null,
        'consent_given' => true,
        'is_wall_of_love' => true,
    ]);
    Testimonial::factory()->for($space)->create([
        'rating' => 5,
        'consent_given' => true,
        'is_wall_of_love' => true,
        'social_url' => 'javascript:alert(1)',
    ]);

    $html = tclBuilderHtml($space, $user);

    // Every preview-card opening tag carries rounded-2xl.
    $cardCount = substr_count($html, 'data-testid="preview-card"');
    expect($cardCount)->toBeGreaterThan(0);
    preg_match_all('/<article\b[^>]*data-testid="preview-card"[^>]*>/i', $html, $cardTags);
    foreach ($cardTags[0] as $tag) {
        expect($tag)->toContain('rounded-2xl');
    }

    // The preview wrapper carries rounded-2xl.
    preg_match('/<div\b[^>]*data-testid="embed-preview"[^>]*>/i', $html, $previewTag);
    expect($previewTag)->not->toBeEmpty();
    expect($previewTag[0])->toContain('rounded-2xl');

    // The rated row renders a preview-rating element with
    // text-amber-500 and the ★ glyph.
    preg_match('/<div\b[^>]*data-testid="preview-rating"[^>]*>.*?<\/div>/is', $html, $ratingSlice);
    expect($ratingSlice)->not->toBeEmpty();
    expect($ratingSlice[0])->toContain('text-amber-500')
        ->and($ratingSlice[0])->toContain('★');

    // The javascript: URL must NOT appear in any href.
    expect($html)->not->toContain('href="javascript:alert(1)"');

    // The http social URL renders a preview-social testid.
    expect($html)->toContain('data-testid="preview-social"');

    // The snippet element is still rendered.
    expect($html)->toContain('data-testid="embed-snippet"');
});

/* --------------------------------------------------------------------- */
/* TCL5 — show_rating off hides stars entirely                            */
/* --------------------------------------------------------------------- */

test('TCL5: builder with show_rating off shows no stars at all', function () {
    // Would fail if: a rated row still renders preview-rating or
    // the ★ glyph when the saved config has show_rating = false.
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    Testimonial::factory()->for($space)->create([
        'rating' => 5,
        'consent_given' => true,
        'is_wall_of_love' => true,
    ]);

    $html = tclBuilderHtml($space, $user, ['show_rating' => false]);

    expect($html)->not->toContain('data-testid="preview-rating"')
        ->and($html)->not->toContain('★');
});

/* --------------------------------------------------------------------- */
/* TCL6 — dark mode card variant                                          */
/* --------------------------------------------------------------------- */

test('TCL6: builder dark mode carries the dark card variant', function () {
    // Would fail if: dark mode stops adding the embed-preview-dark
    // class, the data-ts-dark="on" marker, or a preview-card opens
    // with bg-gray-800 / bg-gray-900. The dark card uses bg-gray-800.
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    Testimonial::factory()->for($space)->create([
        'consent_given' => true,
        'is_wall_of_love' => true,
    ]);

    $html = tclBuilderHtml($space, $user, ['dark_mode' => true]);

    expect($html)->toContain('embed-preview-dark')
        ->and($html)->toContain('data-ts-dark="on"');

    preg_match_all('/<article\b[^>]*data-testid="preview-card"[^>]*>/i', $html, $cardTags);
    expect(count($cardTags[0]))->toBeGreaterThan(0);
    foreach ($cardTags[0] as $tag) {
        $hasDark = (strpos($tag, 'bg-gray-800') !== false)
            || (strpos($tag, 'bg-gray-900') !== false);
        expect($hasDark)->toBeTrue();
    }
});

/* --------------------------------------------------------------------- */
/* TCL7 — builder chrome is the Part 2 look                               */
/* --------------------------------------------------------------------- */

test('TCL7: builder chrome is the Part 2 look (rounded, min-h-[44px], app-lift, hint text exact)', function () {
    // Would fail if: the form loses rounded-2xl, the save button
    // loses !rounded-xl, the copy button loses min-h-[44px] /
    // rounded-xl, the back link loses rounded-xl, or the hint text
    // drifts.
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();

    $html = tclBuilderHtml($space, $user);

    // embed-form opening tag contains rounded-2xl.
    $formTag = tclOpeningTagByTestId($html, 'embed-form');
    expect($formTag)->not->toBe('');
    expect($formTag)->toContain('rounded-2xl');

    // embed-save opening tag contains !rounded-xl.
    $saveTag = tclOpeningTagByTestId($html, 'embed-save');
    expect($saveTag)->not->toBe('');
    expect($saveTag)->toContain('!rounded-xl');

    // embed-copy-button opening tag contains min-h-[44px] AND rounded-xl.
    $copyTag = tclOpeningTagByTestId($html, 'embed-copy-button');
    expect($copyTag)->not->toBe('');
    expect($copyTag)->toContain('min-h-[44px]')
        ->and($copyTag)->toContain('rounded-xl');

    // embed-back-link opening tag contains rounded-xl.
    $backTag = tclOpeningTagByTestId($html, 'embed-back-link');
    expect($backTag)->not->toBe('');
    expect($backTag)->toContain('rounded-xl');

    // The hint text is still the exact required string.
    expect($html)->toContain('The input is limited to whole numbers between 1 and 50.');
});

/* --------------------------------------------------------------------- */
/* TCL8 — inbox row is the card                                           */
/* --------------------------------------------------------------------- */

test('TCL8: inbox row is the card (rounded-2xl, photo, rating, four consent badges, hidden ring)', function () {
    // Would fail if: the <article> loses rounded-2xl or
    // data-testid="inbox-row", the photo element loses h-12 w-12,
    // the rating element loses the text "Rating: 4/5" or the
    // text-amber-500 class, any of the four consent states drops
    // the rounded-full class on its badge opening tag, or the
    // hidden fixture loses the amber ring.
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();

    // Rated, photographed, consented, wall-of-love row.
    $rated = Testimonial::factory()->for($space)->create([
        'rating' => 4,
        'profile_photo' => 'photos/has-photo.webp',
        'consent_given' => true,
        'is_wall_of_love' => true,
        'company_name' => 'Acme',
    ]);

    // Four consent-state fixtures (matches InboxCardRenderTest).
    Testimonial::factory()->for($space)->create([
        'name' => 'LiveRow',
        'consent_given' => true,
        'is_wall_of_love' => true,
    ]);
    Testimonial::factory()->for($space)->create([
        'name' => 'ConsentedRow',
        'consent_given' => true,
        'is_wall_of_love' => false,
    ]);
    Testimonial::factory()->for($space)->create([
        'name' => 'WithdrawnRow',
        'consent_given' => false,
        'is_wall_of_love' => false,
        'consented_at' => now()->subDays(7),
        'consent_text_version' => 'v1',
    ]);
    Testimonial::factory()->for($space)->create([
        'name' => 'NoConsentRow',
        'consent_given' => false,
        'is_wall_of_love' => false,
        'consented_at' => null,
        'consent_text_version' => null,
    ]);

    $hidden = Testimonial::factory()->for($space)->hidden()->create([
        'name' => 'HiddenRow',
    ]);

    // Render the rated row and assert card shape.
    $html = tclInboxRowHtml($rated, $user);

    preg_match('/<article\b[^>]*data-testid="inbox-row"[^>]*>/i', $html, $articleTag);
    expect($articleTag)->not->toBeEmpty();
    expect($articleTag[0])->toContain('rounded-2xl');

    // Photo element has h-12 w-12.
    preg_match('/<img\b[^>]*data-testid="row-photo"[^>]*>/i', $html, $photoTag);
    expect($photoTag)->not->toBeEmpty();
    expect($photoTag[0])->toContain('h-12 w-12');

    // Rating element contains the real value and the amber class.
    preg_match('/<div\b[^>]*data-testid="row-rating"[^>]*>.*?<\/div>/is', $html, $ratingSlice);
    expect($ratingSlice)->not->toBeEmpty();
    expect($ratingSlice[0])->toContain('Rating: 4/5')
        ->and($ratingSlice[0])->toContain('text-amber-500');

    // Render the parent InboxIndex so all four consent badges
    // appear in a single HTML, then assert each badge opening
    // tag literally.
    $indexHtml = (string) Livewire::actingAs($user)
        ->test(\App\Livewire\Inbox\InboxIndex::class, ['spaceId' => $space->id])
        ->html();

    foreach (['live', 'consented', 'withdrawn', 'none'] as $state) {
        $pattern = '/<span\b[^>]*data-testid="row-consent-badge"[^>]*data-consent="'.$state.'"[^>]*>/i';
        preg_match($pattern, $indexHtml, $badgeTag);
        expect($badgeTag)->not->toBeEmpty();
        expect($badgeTag[0])->toContain('rounded-full');
    }

    // Hidden fixture keeps the amber ring.
    $hiddenHtml = tclInboxRowHtml($hidden, $user);
    preg_match('/<article\b[^>]*data-testid="inbox-row"[^>]*>/i', $hiddenHtml, $hiddenArticle);
    expect($hiddenArticle)->not->toBeEmpty();
    expect($hiddenArticle[0])->toContain('ring-amber-200');
});

/* --------------------------------------------------------------------- */
/* TCL9 — action buttons are 44px and keep their wire contract            */
/* --------------------------------------------------------------------- */

test('TCL9: inbox row action buttons are 44px rounded and keep their wire contract (non-trash vs trash)', function () {
    // Would fail if: any non-trash action button loses
    // min-h-[44px] / rounded-xl, the wire:click="softDelete" call
    // disappears, the trash render stops carrying the restore /
    // forget buttons, or wire:click="softDelete" leaks into the
    // trash render.
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    $live = Testimonial::factory()->for($space)->create();
    $trashed = Testimonial::factory()->for($space)->create();
    $trashed->delete();

    // Non-trash render: softDelete present; restore / forget absent.
    $liveHtml = tclInboxRowHtml($live, $user);
    foreach (['row-favorite', 'row-walloflove', 'row-hide', 'row-edit', 'row-withdraw', 'row-delete'] as $tid) {
        $tag = tclOpeningTagByTestId($liveHtml, $tid);
        expect($tag)->not->toBe('');
        expect($tag)->toContain('min-h-[44px]')
            ->and($tag)->toContain('rounded-xl');
    }
    expect($liveHtml)->toContain('wire:click="softDelete"')
        ->and($liveHtml)->not->toContain('wire:click="restore"')
        ->and($liveHtml)->not->toContain('wire:click="forget"');

    // Trash render: restore / forget present with 44px + rounded-xl;
    // softDelete absent. Mount the row with the same flags the
    // parent InboxIndex passes when filter='trash'.
    $trashHtml = tclInboxRowHtml($trashed, $user, true, true, false);
    foreach (['row-restore', 'row-forget'] as $tid) {
        $tag = tclOpeningTagByTestId($trashHtml, $tid);
        expect($tag)->not->toBe('');
        expect($tag)->toContain('min-h-[44px]')
            ->and($tag)->toContain('rounded-xl');
    }
    expect($trashHtml)->not->toContain('wire:click="softDelete"');
});

/* --------------------------------------------------------------------- */
/* TCL10 — no light text / no tiny text / no "WoL" in the Blade files     */
/* --------------------------------------------------------------------- */

test('TCL10: no light text or tiny text or "WoL" left in the two Blade files', function () {
    // Would fail if: any of the forbidden tokens sneaks back into
    // either of the two Blade files in scope.
    foreach ([
        'resources/views/livewire/inbox/inbox-row.blade.php',
        'resources/views/livewire/spaces/embed-builder.blade.php',
    ] as $rel) {
        $abs = base_path($rel);
        $src = (string) file_get_contents($abs);
        expect($src)
            ->not->toContain('text-gray-500')
            ->not->toContain('text-gray-400')
            ->not->toContain('text-[10px]')
            ->not->toContain('text-[11px]')
            ->not->toContain('uppercase')
            ->not->toContain('tracking-widest')
            ->not->toContain('WoL');
    }
});

/* --------------------------------------------------------------------- */
/* TCL11 — photo + escaping contracts survive                             */
/* --------------------------------------------------------------------- */

test('TCL11: photo and escaping contracts survive in both the Inbox row and the builder preview', function () {
    // Would fail if: a testimonial whose text is `<b>x</b>` and
    // whose name is `<i>y</i>` is rendered as raw HTML in either
    // the Inbox row or the builder preview.
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    $t = Testimonial::factory()->for($space)->create([
        'name' => '<i>y</i>',
        'testimonial' => '<b>x</b>',
        'consent_given' => true,
        'is_wall_of_love' => true,
    ]);

    // Inbox row: assert escaped.
    $rowHtml = tclInboxRowHtml($t, $user);
    // The raw <b>x</b> must not appear in the row (anywhere).
    expect($rowHtml)->not->toContain('<b>x</b>')
        ->and($rowHtml)->toContain('&lt;b&gt;x&lt;/b&gt;')
        ->and($rowHtml)->not->toContain('<i>y</i>')
        ->and($rowHtml)->toContain('&lt;i&gt;y&lt;/i&gt;');

    // Builder preview: assert escaped.
    $builderHtml = tclBuilderHtml($space, $user);
    expect($builderHtml)->not->toContain('<b>x</b>')
        ->and($builderHtml)->toContain('&lt;b&gt;x&lt;/b&gt;')
        ->and($builderHtml)->not->toContain('<i>y</i>')
        ->and($builderHtml)->toContain('&lt;i&gt;y&lt;/i&gt;');
});

/* --------------------------------------------------------------------- */
/* TCL12 — builder dark preview: light company text and flag-driven link   */
/* --------------------------------------------------------------------- */

test('TCL12: builder dark preview uses light company text and the flag-driven link colour', function () {
    // Would fail if: with dark_mode = true the company line stays
    // text-gray-600 (low contrast on the gray-800 card) or keeps an
    // opacity reduction; the social link still uses the dark: variant
    // (which follows the OS theme, not the builder's toggle) instead
    // of the flag-driven indigo-300; with dark_mode = false the link
    // is no longer indigo-700 or the company is no longer gray-600.
    //
    // EmbedConfiguration has a unique constraint on space_id, so we
    // need two separate spaces - one per render.
    $user = User::factory()->create();

    $darkSpace = Space::factory()->for($user)->create();
    Testimonial::factory()->for($darkSpace)->create([
        'consent_given' => true,
        'is_wall_of_love' => true,
        'company_name' => 'Acme Co',
        'social_url' => 'https://example.test/x',
    ]);
    $lightSpace = Space::factory()->for($user)->create();
    Testimonial::factory()->for($lightSpace)->create([
        'consent_given' => true,
        'is_wall_of_love' => true,
        'company_name' => 'Acme Co',
        'social_url' => 'https://example.test/x',
    ]);

    // Dark mode: link = text-indigo-300 (flag-driven, no dark: variant),
    // company = text-gray-300.
    $darkHtml = tclBuilderHtml($darkSpace, $user, ['dark_mode' => true]);
    $darkSocial = tclOpeningTagByTestId($darkHtml, 'preview-social');
    expect($darkSocial)->not->toBe('');
    expect($darkSocial)->toContain('text-indigo-300');
    expect($darkSocial)->not->toContain('dark:text-indigo-300');

    // Find the company line inside the preview-card by its text and
    // confirm the class on the element wrapping "Acme Co".
    preg_match('/<div\b[^>]*>\s*Acme Co\s*<\/div>/i', $darkHtml, $companyTag);
    expect($companyTag)->not->toBeEmpty();
    expect($companyTag[0])->toContain('text-gray-300')
        ->and($companyTag[0])->not->toContain('text-gray-600');

    // Light mode: link = text-indigo-700, company = text-gray-600.
    $lightHtml = tclBuilderHtml($lightSpace, $user, ['dark_mode' => false]);
    $lightSocial = tclOpeningTagByTestId($lightHtml, 'preview-social');
    expect($lightSocial)->not->toBe('');
    expect($lightSocial)->toContain('text-indigo-700');
    expect($lightSocial)->not->toContain('text-indigo-300');

    preg_match('/<div\b[^>]*>\s*Acme Co\s*<\/div>/i', $lightHtml, $lightCompanyTag);
    expect($lightCompanyTag)->not->toBeEmpty();
    expect($lightCompanyTag[0])->toContain('text-gray-600')
        ->and($lightCompanyTag[0])->not->toContain('text-gray-300');
});

/* --------------------------------------------------------------------- */
/* TCL13 — builder preview empty stars are gray, filled are amber          */
/* --------------------------------------------------------------------- */

/* Helper: every star glyph span of a rating block, with its attributes.    */
function tclStarSpans(string $slice): array
{
    preg_match_all('/<span\b([^>]*)>\s*(★|☆)\s*<\/span>/u', $slice, $hits, PREG_SET_ORDER);
    $out = [];
    foreach ($hits as $hit) {
        $out[] = ['glyph' => $hit[2], 'attrs' => $hit[1]];
    }

    return $out;
}

test('TCL13: builder preview empty stars are gray, filled are amber (light and dark)', function () {
    // Would fail if: for a rating of 4 the preview does not render
    // exactly 4 filled + 1 empty star span; a filled star lacks
    // text-amber-500 or carries a gray class; an EMPTY star still
    // carries text-amber-500 (class order in the attribute does not
    // decide the colour in Tailwind, so the amber class must simply not
    // be there); the empty star is not text-gray-300 in light mode or
    // text-gray-600 in dark mode.
    $user = User::factory()->create();
    $lightSpace = Space::factory()->for($user)->create();
    Testimonial::factory()->for($lightSpace)->create([
        'rating' => 4,
        'consent_given' => true,
        'is_wall_of_love' => true,
    ]);
    $darkSpace = Space::factory()->for($user)->create();
    Testimonial::factory()->for($darkSpace)->create([
        'rating' => 4,
        'consent_given' => true,
        'is_wall_of_love' => true,
    ]);

    $variants = [
        ['html' => tclBuilderHtml($lightSpace, $user), 'empty' => 'text-gray-300', 'other' => 'text-gray-600'],
        ['html' => tclBuilderHtml($darkSpace, $user, ['dark_mode' => true]), 'empty' => 'text-gray-600', 'other' => 'text-gray-300'],
    ];

    foreach ($variants as $variant) {
        preg_match('/<div\b[^>]*data-testid="preview-rating"[^>]*>.*?<\/div>/is', $variant['html'], $ratingSlice);
        expect($ratingSlice)->not->toBeEmpty();

        $spans = tclStarSpans($ratingSlice[0]);
        expect(count($spans))->toBe(5);

        $filled = 0;
        $empty = 0;
        foreach ($spans as $span) {
            if ($span['glyph'] === '★') {
                $filled++;
                expect($span['attrs'])->toContain('text-amber-500')
                    ->and($span['attrs'])->not->toContain('text-gray-');
            } else {
                $empty++;
                expect($span['attrs'])->toContain($variant['empty'])
                    ->and($span['attrs'])->not->toContain($variant['other'])
                    ->and($span['attrs'])->not->toContain('text-amber-500');
            }
        }
        expect($filled)->toBe(4);
        expect($empty)->toBe(1);
    }
});

/* --------------------------------------------------------------------- */
/* TCL14 — inbox row stars colors + hidden chip self-start                 */
/* --------------------------------------------------------------------- */

test('TCL14: inbox row stars are amber+gray, hidden chip sizes to content (self-start)', function () {
    // Would fail if: a rating of 3 renders something other than 3★
    // + 2☆, the filled stars lose text-amber-500, the empty stars
    // lose text-gray-300 (or an empty star keeps text-amber-500), the
    // star spans lose aria-hidden="true",
    // the "Rating: 3/5" text is removed, or the hidden chip's
    // opening tag loses self-start / rounded-full.
    //
    // For F4 we chose `self-start` as the class that makes the chip
    // size to its content inside the meta's flex-col mobile layout.
    // That choice is stated here so a future editor knows which
    // string to look for.
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();

    // Row with rating 3.
    $row = Testimonial::factory()->for($space)->create([
        'rating' => 3,
    ]);
    $html = tclInboxRowHtml($row, $user);

    preg_match('/<div\b[^>]*data-testid="row-rating"[^>]*>.*?<\/div>/is', $html, $ratingSlice);
    expect($ratingSlice)->not->toBeEmpty();
    $slice = $ratingSlice[0];

    expect(substr_count($slice, '★'))->toBe(3);
    expect(substr_count($slice, '☆'))->toBe(2);
    expect($slice)->toContain('Rating: 3/5');

    // Per star: filled = amber only, empty = gray-300 only, all aria-hidden.
    $spans = tclStarSpans($slice);
    expect(count($spans))->toBe(5);
    foreach ($spans as $span) {
        expect($span['attrs'])->toContain('aria-hidden="true"');
        if ($span['glyph'] === '★') {
            expect($span['attrs'])->toContain('text-amber-500')
                ->and($span['attrs'])->not->toContain('text-gray-');
        } else {
            expect($span['attrs'])->toContain('text-gray-300')
                ->and($span['attrs'])->not->toContain('text-amber-500');
        }
    }

    // Hidden row: the chip carries self-start (F4 choice) + rounded-full.
    $hidden = Testimonial::factory()->for($space)->hidden()->create();
    $hiddenHtml = tclInboxRowHtml($hidden, $user);
    $chipTag = tclOpeningTagByTestId($hiddenHtml, 'row-hidden-chip');
    expect($chipTag)->not->toBe('');
    expect($chipTag)->toContain('self-start')
        ->and($chipTag)->toContain('rounded-full');
});

/* --------------------------------------------------------------------- */
/* TCL15 — builder text inputs match the form look                        */
/* --------------------------------------------------------------------- */

test('TCL15: builder text inputs (backgroundColor, itemLimitInput) carry rounded-xl and min-h-[44px]', function () {
    // Would fail if: either of the two text inputs drops the call-site
    // rounded-xl or min-h-[44px] class, or the x-text-input component
    // overrides them with its own rounded-md (in Tailwind, later class
    // wins, so the call-site rounded-xl should appear after rounded-md
    // in the merged class string).
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    $html = tclBuilderHtml($space, $user);

    foreach (['backgroundColor', 'itemLimitInput'] as $id) {
        $pattern = '/<input\b[^>]*id="'.$id.'"[^>]*>/i';
        preg_match($pattern, $html, $inputTag);
        expect($inputTag)->not->toBeEmpty();
        expect($inputTag[0])->toContain('rounded-xl')
            ->and($inputTag[0])->toContain('min-h-[44px]');
    }
});

/* --------------------------------------------------------------------- */
/* TCL16 — widget source order: name+company beside the photo             */
/* --------------------------------------------------------------------- */

test('TCL16: widget buildCard creates the company element before appending the header to the card', function () {
    // Would fail if: the widget stops stacking the company beside the
    // name (e.g., it goes back to being a sibling of the whole header),
    // the ts-company creation moves AFTER card.appendChild(header), or
    // the function no longer mentions item.company_name / ts-header /
    // ts-name / ts-photo.
    //
    // This test guards the SOURCE ORDER only; it does not prove the
    // pixels. The visual check is the user's browser check.
    $src = tclEmbedJsSource();

    // Slice from `function buildCard` up to (and including) the start
    // of `function buildCss`, so we are looking only at buildCard.
    $start = strpos($src, 'function buildCard');
    $end = strpos($src, 'function buildCss');
    expect($start)->not->toBeFalse();
    expect($end)->not->toBeFalse();
    expect($end)->toBeGreaterThan($start);
    $body = substr($src, $start, $end - $start);

    // The ts-company element creation appears BEFORE card.appendChild(header).
    $companyCreateAt = strpos($body, "'ts-company'");
    $headerAppendAt = strpos($body, 'card.appendChild(header)');
    expect($companyCreateAt)->not->toBeFalse();
    expect($headerAppendAt)->not->toBeFalse();
    expect($companyCreateAt)->toBeLessThan($headerAppendAt);

    // The condition on which company is created is still item.company_name.
    expect($body)->toContain('item.company_name');

    // The vocabulary is preserved: ts-header, ts-name, ts-photo.
    expect($body)->toContain('ts-header')
        ->and($body)->toContain('ts-name')
        ->and($body)->toContain('ts-photo');
});
