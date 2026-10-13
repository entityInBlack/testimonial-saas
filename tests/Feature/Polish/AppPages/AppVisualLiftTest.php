<?php

/**
 * Part 2 — visual lift tests for the LOGGED-IN app pages.
 *
 * IMPORTANT: Pest cannot run CSS or JavaScript. These tests only guard
 * the SOURCE of the markup/CSS in the rendered page (Tailwind class
 * names, opening tag shapes, presence of selectors and text). The real
 * visual check is the user's browser check.
 *
 * Every helper function in this file is prefixed `apl` (Pest helpers
 * are global). The `lvl` (landing) and `gvl` (guest) helpers from
 * earlier Polish files use other prefixes; nothing in this file
 * collides with them.
 *
 * Each test starts with a "Would fail if:" comment so future edits
 * can see which shapes are intentional. No assertion can be weakened
 * to make it pass.
 */

use App\Livewire\Dashboard\DashboardIndex;
use App\Livewire\Spaces\SpaceForm;
use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;
use Livewire\Livewire;

/* --------------------------------------------------------------------- */
/* Helpers                                                              */
/* --------------------------------------------------------------------- */

/**
 * The list of files in scope for Part 2 (section 3 of the prompt).
 * Used by APL5 and APL8.
 */
function aplInScopeFiles(): array
{
    return [
        'resources/views/layouts/app.blade.php',
        'resources/views/components/page-header.blade.php',
        'resources/views/livewire/dashboard/dashboard-index.blade.php',
        'resources/views/livewire/spaces/space-index.blade.php',
        'resources/views/livewire/spaces/space-form.blade.php',
        'resources/views/livewire/spaces/space-created.blade.php',
        'resources/views/livewire/spaces/space-deleted.blade.php',
        'resources/views/components/spaces/deleted-list.blade.php',
        'resources/views/profile.blade.php',
        'resources/views/livewire/profile/update-profile-information-form.blade.php',
        'resources/views/livewire/profile/update-password-form.blade.php',
        'resources/views/livewire/profile/delete-user-form.blade.php',
        'resources/views/livewire/inbox/inbox-index.blade.php',
    ];
}

/**
 * Get the body of a page as the given user (or guest).
 * Same shape as T2LoggedInDashboardShellTest so we keep one
 * centralised way to GET rendered pages for the assertions.
 */
function aplBody(string $path, ?User $user = null): string
{
    $test = test();
    if ($user) {
        $test = $test->actingAs($user);
    }
    return (string) $test->get($path)->assertOk()->getContent();
}

/**
 * Extract the CSS text of the layout's <style> block (the one that
 * contains `.app-fade`) from a page body. Returns '' if not found.
 * Tolerates whitespace and newlines inside the block.
 */
function aplLayoutCss(string $body): string
{
    $pattern = '/<style\b[^>]*>(.*?)<\/style>/is';
    if (preg_match_all($pattern, $body, $matches)) {
        foreach ($matches[1] as $css) {
            if (str_contains($css, '.app-fade')) {
                return $css;
            }
        }
    }
    return '';
}

/**
 * Slice the rendered HTML of a card starting at the given
 * data-testid opening tag up to the next data-testid="counter-"
 * that is NOT the same card's own -value child. Returns '' if the
 * testid is not found.
 */
function aplCardSlice(string $body, string $testid): string
{
    $parts = explode('data-testid="'.$testid.'"', $body, 2);
    if (count($parts) !== 2) {
        return '';
    }
    $after = $parts[1];
    $stopAt = preg_split('/data-testid="(?!'.preg_quote($testid, '/').')counter-/', $after);
    $chunk = $stopAt[0] ?? $after;
    return 'data-testid="'.$testid.'"'.$chunk;
}

/* --------------------------------------------------------------------- */
/* APL1: soft canvas                                                   */
/* --------------------------------------------------------------------- */

test('APL1: app canvas is the soft gradient on dashboard, spaces, inbox, profile (not flat gray)', function () {
    // Would fail if: any of these pages is missing the canvas, the
    // gradient, the title bar border, or the old `bg-gray-100` /
    // `<header class="bg-white shadow">` returns.
    $user = User::factory()->create();
    Space::factory()->for($user)->create();

    foreach (['/dashboard', '/spaces', '/inbox', '/profile'] as $path) {
        $body = aplBody($path, $user);

        expect($body)
            ->toContain('from-indigo-50')
            ->not->toContain('class="bg-gray-100');
        expect($body)->not->toContain('<header class="bg-white shadow">');

        // The min-h-screen wrapper carries the gradient classes.
        $ok = preg_match('/<div\s+class="min-h-screen[^"]*bg-gradient-to-b[^"]*from-indigo-50[^"]*"/i', $body) === 1;
        // The min-h-screen wrapper must carry bg-gradient-to-b from-indigo-50.
        expect($ok)->toBeTrue();

        // The title bar's <header> carries `border-b` (no heavy shadow).
        $ok2 = preg_match('/<header\b[^>]*\bborder-b\b/i', $body) === 1;
        // The title bar <header> must carry border-b.
        expect($ok2)->toBeTrue();
    }
});

/* --------------------------------------------------------------------- */
/* APL2: page header contract                                          */
/* --------------------------------------------------------------------- */

test('APL2: page title is bigger and bolder, structure intact (dashboard)', function () {
    // Would fail if: the page-header h1 is back to text-xl font-semibold,
    // or the testid is duplicated, or the subtitle element is rendered
    // when no subtitle was provided.
    $user = User::factory()->create();

    $body = aplBody('/dashboard', $user);

    // Exactly one page-header testid.
    expect(substr_count($body, 'data-testid="page-header"'))->toBe(1);

    // The h1 has the new classes.
    preg_match('/<h1\b[^>]*data-testid="page-header-title"[^>]*>/i', $body, $h1);
    expect($h1)->not->toBeEmpty();
    $tag = $h1[0];
    expect($tag)->toContain('font-bold');
    expect($tag)->toContain('tracking-tight');
    expect($tag)->not->toContain('text-xl');
    expect($tag)->not->toContain('font-semibold');

    // Dashboard page-header has no subtitle.
    expect($body)->not->toContain('data-testid="page-header-subtitle"');
});

test('APL2b: page-header without subtitle does not render a subtitle element (Blade::render)', function () {
    // Same shape as T5b to confirm we did not regress the contract.
    $html = \Illuminate\Support\Facades\Blade::render(
        '<x-page-header :title="$title" />',
        ['title' => 'Spaces']
    );
    expect($html)->toContain('Spaces');
    expect($html)->not->toContain('data-testid="page-header-subtitle"');
});

/* --------------------------------------------------------------------- */
/* APL3: dashboard counter cards                                       */
/* --------------------------------------------------------------------- */

test('APL3: dashboard counter cards are icon cards with h-11 w-11 chip and bigger number', function () {
    // Would fail if: a counter card loses the icon chip, the
    // rounded-2xl look, the bigger number, or the value child gets
    // accidentally wrapped in the <svg>.
    $user = User::factory()->create();
    Space::factory()->for($user)->create();
    $space = Space::factory()->for($user)->create();
    Testimonial::factory()->for($space)->create(['rating' => 4]);
    Testimonial::factory()->for($space)->create(['rating' => 5]);

    $body = aplBody('/dashboard', $user);

    // Whole counter-grid markup contains no `uppercase`.
    preg_match('#<div\b[^>]*data-testid="counter-grid"[^>]*>.*?(?=<div\b[^>]*data-testid="deletion-requests-card")#s', $body, $m);
    $grid = $m[0] ?? '';
    expect($grid)->not->toBeEmpty();
    expect($grid)->not->toContain('uppercase');

    // For each of the five counter cards, the slice contains the
    // icon chip (h-11 w-11), an svg, rounded-2xl, and the
    // -value child is NOT inside the svg.
    $cards = [
        'counter-total-testimonials',
        'counter-total-customers',
        'counter-spaces-used',
        'counter-average-rating',
        'counter-open-deletion-requests',
    ];
    foreach ($cards as $tid) {
        $slice = aplCardSlice($body, $tid);
        expect($slice)->not->toBeEmpty();
        expect($slice)->toContain('h-11 w-11');
        expect($slice)->toContain('<svg');
        expect($slice)->toContain('rounded-2xl');

        // The -value element's text is not inside the svg.
        $valueTid = $tid.'-value';
        $vp = preg_quote($valueTid, '/');
        $found = preg_match('/<div\b[^>]*data-testid="'.$vp.'"[^>]*>(.+?)<\/div>/is', $slice, $vm);
        expect($found)->toBe(1);
        $inner = $vm[1];
        expect($inner)->not->toContain('<svg');
    }

    // "Spaces used" still renders as one text run. The fixture
    // above creates 2 Spaces, so the value is "2 of 3".
    preg_match('/<div\b[^>]*data-testid="counter-spaces-used-value"[^>]*>(.+?)<\/div>/is', $body, $sm);
    expect($sm)->not->toBeEmpty();
    $inner = preg_replace('/\s+/', ' ', trim(strip_tags($sm[1])));
    expect($inner)->toBe('2 of 3');
});

/* --------------------------------------------------------------------- */
/* APL4: dashboard cards are rounded-2xl + free-plan note              */
/* --------------------------------------------------------------------- */

test('APL4: dashboard cards are rounded-2xl and the free-plan note is a plain banner', function () {
    // Would fail if: the deletion-requests card or graph card loses
    // rounded-2xl, the free-plan note gets nested into an <a>/<button>
    // /[href], or the sentence drifts.
    $user = User::factory()->create();
    Space::factory()->for($user)->create();

    $html = (string) Livewire::actingAs($user)
        ->test(DashboardIndex::class)
        ->html();

    // Both cards have rounded-2xl in their opening tag.
    preg_match('/<div\b[^>]*data-testid="deletion-requests-card"[^>]*>/i', $html, $drm);
    expect($drm)->not->toBeEmpty();
    expect($drm[0])->toContain('rounded-2xl');

    preg_match('/<div\b[^>]*data-testid="graph-card"[^>]*>/i', $html, $grm);
    expect($grm)->not->toBeEmpty();
    expect($grm[0])->toContain('rounded-2xl');

    // The free-plan note has rounded-xl.
    preg_match('/<div\b[^>]*data-testid="free-plan-note"[^>]*>/i', $html, $fpm);
    expect($fpm)->not->toBeEmpty();
    expect($fpm[0])->toContain('rounded-xl');

    // Sentence is present exactly once.
    $expected = "You're on the Free plan (3 Spaces, 100 testimonials per Space).";
    expect($html)->toContain($expected);
    expect(substr_count($html, $expected))->toBe(1);

    // 400-char window is free of anchors/buttons/hrefs.
    $pos = strpos($html, "You're on the Free plan");
    expect($pos)->not->toBeFalse();
    $window = substr($html, max(0, $pos - 200), 400);
    expect($window)
        ->not->toContain('<a')
        ->not->toContain('<button')
        ->not->toContain('href=');
});

/* --------------------------------------------------------------------- */
/* APL5: no light-gray text in scope                                   */
/* --------------------------------------------------------------------- */

test('APL5: no low-contrast gray-500 / gray-400 in Part 2 files; no uppercase / tracking-widest either', function () {
    // Would fail if: any file of section 3 still carries a
    // text-gray-500 / text-gray-400 / uppercase / tracking-widest
    // class. The shared nav is OUT of scope on purpose and keeps
    // its own text-gray-500.
    foreach (aplInScopeFiles() as $rel) {
        $abs = base_path($rel);
        $src = (string) file_get_contents($abs);
        expect($src)
            ->not->toContain('text-gray-500')
            ->not->toContain('text-gray-400')
            ->not->toContain('uppercase')
            ->not->toContain('tracking-widest');
    }
});

/* --------------------------------------------------------------------- */
/* APL6: spaces rows are cards with real buttons                       */
/* --------------------------------------------------------------------- */

test('APL6: spaces rows are cards with rounded-2xl and proper secondary / danger buttons', function () {
    // Would fail if: a space-row card loses rounded-2xl, the row
    // buttons lose 44px height or rounded-xl, the delete button's
    // wire:click / wire:confirm disappear, the title link stops
    // containing exactly the escaped title, or the new-space-link
    // reverts to gray-800 or uppercase.
    $user = User::factory()->create();
    $a = Space::factory()->for($user)->create(['title' => 'My Test Space', 'slug' => 'my-test-space']);
    $b = Space::factory()->for($user)->create(['title' => 'My Other Space', 'slug' => 'my-other-space']);

    $body = aplBody('/spaces', $user);

    // space-row opens with rounded-2xl (one row per Space — at least
    // one matching). Tailwind alphabetises the class list so
    // `rounded-2xl` may appear BEFORE `data-testid`; the tag is
    // checked as a whole.
    $rowCount = substr_count($body, 'data-testid="space-row"');
    // Expected at least 2 space rows.
    expect($rowCount)->toBeGreaterThanOrEqual(2);
    preg_match_all('/<div\b[^>]*data-testid="space-row"[^>]*>/i', $body, $rowTags);
    foreach ($rowTags[0] as $rowTag) {
        expect($rowTag)->toContain('rounded-2xl');
    }

    // embed-link / edit-link / delete-button each carry min-h-[44px] and rounded-xl.
    foreach (['embed-link', 'edit-link', 'delete-button'] as $tid) {
        $pattern = '/<[a-z]+[^>]*data-testid="'.preg_quote($tid, '/').'"[^>]*>/i';
        preg_match($pattern, $body, $matches);
        expect($matches)->not->toBeEmpty();
        $tag = $matches[0];
        expect($tag)->toContain('min-h-[44px]')
            ->and($tag)->toContain('rounded-xl');
    }

    // delete-button still has wire:click and wire:confirm.
    $dpat = '/<button\b[^>]*data-testid="delete-button"[^>]*>/is';
    preg_match($dpat, $body, $dm);
    expect($dm)->not->toBeEmpty();
    $delete = $dm[0];
    expect($delete)->toContain('wire:click="delete(')
        ->and($delete)->toContain('wire:confirm');

    // Title link inner text is exactly the escaped title.
    foreach (['My Test Space', 'My Other Space'] as $title) {
        $quoted = preg_quote(e($title), '#');
        $pattern = '#<a[^>]*data-testid="space-row-title-link"[^>]*>\s*'.$quoted.'\s*</a>#';
        $ok = preg_match($pattern, $body);
        // Title link inner-text must match exactly.
        expect($ok)->toBe(1);
    }

    // new-space-link is indigo and rounded-xl, no uppercase.
    $pattern = '/<a\b[^>]*data-testid="new-space-link"[^>]*>/i';
    preg_match($pattern, $body, $m);
    expect($m)->not->toBeEmpty();
    $tag = $m[0];
    expect($tag)
        ->toContain('bg-indigo-600')
        ->toContain('rounded-xl')
        ->not->toContain('uppercase')
        ->not->toContain('tracking-widest');
});

/* --------------------------------------------------------------------- */
/* APL7: space form and success page contracts                          */
/* --------------------------------------------------------------------- */

test('APL7: space form carries rounded-2xl card and !rounded-xl save button; three label states exist', function () {
    // Would fail if: the form card loses rounded-2xl, the save
    // button loses the !rounded-xl helper, or any of the three
    // label states disappear from the source.
    $user = User::factory()->create();

    $body = aplBody('/spaces/new', $user);

    preg_match('/<form\b[^>]*data-testid="space-form"[^>]*>/i', $body, $fm);
    expect($fm)->not->toBeEmpty();
    expect($fm[0])->toContain('rounded-2xl');

    preg_match('/<button\b[^>]*data-testid="space-save"[^>]*>/i', $body, $sm);
    expect($sm)->not->toBeEmpty();
    expect($sm[0])->toContain('!rounded-xl');

    // The source still contains all three label states.
    $src = (string) file_get_contents(base_path('resources/views/livewire/spaces/space-form.blade.php'));
    expect($src)->toContain("__('Save changes')");
    expect($src)->toContain("__('Create Space')");
    expect($src)->toContain('Working…');
});

test('APL7b: space-created success page keeps its contracts and the URL box has break-all', function () {
    // Would fail if: any of public-url-box / copy-link /
    // go-to-dashboard are dropped, or the URL is no longer
    // wrap-friendly on a 320px screen.
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create(['slug' => 'public-wall', 'title' => 'My Title']);

    $body = aplBody('/spaces/'.$space->id.'/created', $user);

    expect($body)->toContain('data-testid="public-url-box"')
        ->and($body)->toContain('data-testid="copy-link"')
        ->and($body)->toContain('data-testid="go-to-dashboard"')
        ->and($body)->toContain('public-wall')
        ->and($body)->toContain('/s/public-wall');

    // The URL box OR its inner code carries `break-all` so the URL
    // wraps on 320px wide screens.
    preg_match('/<div\b[^>]*data-testid="public-url-box"[^>]*>(.+?)<\/div>\s*<p/is', $body, $bm);
    expect($bm)->not->toBeEmpty();
    $boxSlice = $bm[0];
    expect($boxSlice)->toContain('break-all');
});

/* --------------------------------------------------------------------- */
/* APL8: profile page uses three rounded cards                         */
/* --------------------------------------------------------------------- */

test('APL8: profile uses three rounded-2xl cards (HTML+source)', function () {
    // Would fail if: the HTML carries any sm:rounded-lg (the old
    // Breeze wrapper class on the profile page), or rounded-2xl is
    // missing on at least one of the three wrappers.
    $user = User::factory()->create();

    $body = aplBody('/profile', $user);
    expect($body)->not->toContain('sm:rounded-lg');

    // Source-level: rounded-2xl appears at least three times.
    $src = (string) file_get_contents(base_path('resources/views/profile.blade.php'));
    expect(substr_count($src, 'rounded-2xl'))->toBeGreaterThanOrEqual(3);
});

/* --------------------------------------------------------------------- */
/* APL9: inbox chrome                                                   */
/* --------------------------------------------------------------------- */

test('APL9: inbox chrome uses rounded-2xl cards and pill-style filter tabs that wrap', function () {
    // Would fail if: the space-selector loses rounded-2xl, any
    // filter-tab-* loses rounded-full or min-h-[44px], the active
    // tab is not visibly different from the inactive ones, or the
    // "WoL" abbreviation sneaks back.
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();

    $body = aplBody('/inbox?spaceId='.$space->id, $user);

    // space-selector is rounded-2xl.
    preg_match('/<div\b[^>]*data-testid="space-selector"[^>]*>/i', $body, $ssm);
    expect($ssm)->not->toBeEmpty();
    expect($ssm[0])->toContain('rounded-2xl');

    // each filter tab is rounded-full and min-h-[44px].
    foreach (['all', 'favorites', 'wall', 'hidden', 'trash'] as $key) {
        $pattern = '/<button\b[^>]*data-testid="filter-tab-'.$key.'"[^>]*>/i';
        preg_match($pattern, $body, $m);
        expect($m)->not->toBeEmpty();
        $tag = $m[0];
        expect($tag)->toContain('rounded-full')
            ->and($tag)->toContain('min-h-[44px]');
    }

    // "Wall of Love" present and "WoL" absent.
    expect($body)->toContain('Wall of Love');
    $bodyWithoutWallOfLove = str_replace('Wall of Love', '', $body);
    expect($bodyWithoutWallOfLove)->not->toContain('>WoL<')
        ->and($bodyWithoutWallOfLove)->not->toContain('WoL ✓');

    // The current filter's tab class is visibly different from an
    // inactive tab class. Render once with filter='all' (default) and
    // compare the two class strings.
    $htmlAll = (string) Livewire::actingAs($user)
        ->test(\App\Livewire\Inbox\InboxIndex::class, ['spaceId' => $space->id, 'filter' => 'all'])
        ->html();
    $htmlFav = (string) Livewire::actingAs($user)
        ->test(\App\Livewire\Inbox\InboxIndex::class, ['spaceId' => $space->id, 'filter' => 'favorites'])
        ->html();
    preg_match('/<button\b[^>]*data-testid="filter-tab-all"[^>]*>/i', $htmlAll, $am);
    preg_match('/<button\b[^>]*data-testid="filter-tab-favorites"[^>]*>/i', $htmlFav, $fm);
    expect($am[0] ?? '')->not->toBe($fm[0] ?? '');
});

/* --------------------------------------------------------------------- */
/* APL10: app motion CSS rules                                          */
/* --------------------------------------------------------------------- */

test('APL10: layout CSS keeps the plain resting state, prefers-reduced-motion block present, keyframe present', function () {
    // Would fail if: a base rule on .app-* sets opacity or transform,
    // the animation uses `forwards` or `both`, no reduced-motion
    // block exists, no @keyframes app-* is defined, or any rule
    // adds will-change / filter.
    $user = User::factory()->create();

    foreach (['/dashboard'] as $path) {
        $body = aplBody($path, $user);

        // Extract the CSS text of the layout's <style> block (the one
        // that contains `.app-fade`) BEFORE running the checks.
        // Layout <style> block must be present.
        $css = aplLayoutCss($body);
        expect($css)->not->toBeEmpty();
        expect($css)->toContain('.app-fade');

        // (1) The reduced-motion block exists and contains
        // animation: none and transition: none.
        $reducedPattern = '/@media\s*\(prefers-reduced-motion:\s*reduce\)\s*\{(?:[^{}]*\{[^{}]*\})*[^{}]*\}/s';
        $reducedFound = preg_match($reducedPattern, $css, $reducedMatch);
        expect($reducedFound)->toBe(1);
        expect($reducedMatch[0])->toContain('animation: none')
            ->and($reducedMatch[0])->toContain('transition: none');

        // (2) Strip the block and the result must still contain at
        // least one base `.app-*` rule whose block has NEITHER
        // opacity NOR transform.
        $stripped = preg_replace($reducedPattern, '', $css);
        expect($stripped)->not->toBeNull();
        $stripped = (string) $stripped;

        // Find every plain base rule starting with .app- and no
        // pseudo-class, not inside @keyframes.
        $baseRules = '/(\.app-[A-Za-z0-9_-]+)(?:\\s*,\\s*\\.[^{]+)*\\s*\\{([^{}]*)\\}/s';
        preg_match_all($baseRules, $stripped, $allMatches, PREG_SET_ORDER);
        // Filter out matches that look like keyframe placeholders or
        // hover/focus rules by checking for :hover/:focus selectors.
        $plains = array_filter($allMatches, function ($m) {
            $selector = $m[1];
            return stripos($selector, ':') === false;
        });
        expect(count($plains))->toBeGreaterThan(0);

        foreach ($plains as $i => $m) {
            $block = $m[2];
            // opacity is NEVER allowed on a base rule.
            expect(stripos($block, 'opacity'))->toBeFalse();
            // `transform` is only allowed as a value in `transition: transform 0.18s ...`
            // — that is the resting-state-plain pattern from Part 1b. Any bare
            // `transform:` declaration (or other use of transform) is forbidden.
            $blockMinusTransition = preg_replace('/transition\s*:\s*[^;}]*;?/i', '', $block);
            expect(stripos($blockMinusTransition, 'transform'))->toBeFalse();
        }

        // (3) backwards present, forwards absent.
        expect(stripos($stripped, 'backwards') !== false)->toBeTrue();
        expect(stripos($stripped, 'forwards') === false)->toBeTrue();

        // (4) at least one @keyframes app-* definition.
        $kfPattern = '/@keyframes\s+app-[A-Za-z0-9_-]+\s*\{[^}]*\}/s';
        preg_match_all($kfPattern, $stripped, $kfMatches);
        expect(count($kfMatches[0]))->toBeGreaterThan(0);

        // (5) no will-change and no filter.
        expect(stripos($stripped, 'will-change'))->toBeFalse()
            ->and(stripos($stripped, 'filter'))->toBeFalse();
    }
});

/* --------------------------------------------------------------------- */
/* APL11: animated wrapper is not Livewire/chart/modal                  */
/* --------------------------------------------------------------------- */

test('APL11: the animated wrapper is a stable content wrapper (not graph-canvas-wrap, no own wire:id)', function () {
    // Would fail if: graph-canvas-wrap itself gets an app-* class
    // (would re-run fade-up on chart updates), or the animated
    // element carries a wire:id (Livewire would re-create it on
    // each update and the fade would replay on every interaction).
    $user = User::factory()->create();
    Space::factory()->for($user)->create();

    foreach (['/dashboard', '/profile'] as $path) {
        $body = aplBody($path, $user);

        // graph-canvas-wrap does NOT carry any app-* class.
        $gcw = preg_match('/<div\b[^>]*data-testid="graph-canvas-wrap"[^>]*>/i', $body, $gm);
        if ($gcw === 1) {
            expect($gm[0])->not->toContain('app-');
        }

        // Find the element carrying app-fade. It must NOT carry a
        // wire:id on its own opening tag.
        $afp = '/<[^>]+\bapp-fade\b[^>]*>/i';
        preg_match_all($afp, $body, $afm);
        expect(count($afm[0]))->toBeGreaterThan(0);
        foreach ($afm[0] as $tag) {
            expect($tag)->not->toContain('wire:id');
        }
    }
});

/* --------------------------------------------------------------------- */
/* APL12: counter chip comes before the label and the number (320px)   */
/* --------------------------------------------------------------------- */

test('APL12: counter chip comes before the label and the number (no 320px overlap)', function () {
    // Would fail if: the chip goes back beside the text (right side),
    // or the card loses the stacked layout (flex-col) at every breakpoint.
    //
    // Honest caveat: Pest cannot measure overflow. The 320px check is
    // the user's browser check; this test only guards the markup
    // shape (chip-first, label-second, value-third, stacked layout).
    $user = User::factory()->create();
    Space::factory()->for($user)->create();
    $space = Space::factory()->for($user)->create();
    Testimonial::factory()->for($space)->create(['rating' => 4]);

    $body = aplBody('/dashboard', $user);

    $cards = [
        'counter-total-testimonials' => 'Total testimonials',
        'counter-total-customers' => 'Total customers',
        'counter-spaces-used' => 'Spaces used',
        'counter-average-rating' => 'Average rating',
        'counter-open-deletion-requests' => 'Open deletion requests',
    ];

    foreach ($cards as $tid => $label) {
        $slice = aplCardSlice($body, $tid);
        expect($slice)->not->toBeEmpty();

        // The slice uses a stacked (column) layout, not side-by-side.
        expect($slice)->toContain('flex-col');

        // The first h-11 w-11 chip must come BEFORE the label text and
        // BEFORE the card's own -value testid. (The chip is the
        // sibling of the label/value, never inside the -value.)
        $chipPos = strpos($slice, 'h-11 w-11');
        $labelPos = strpos($slice, $label);
        $valueTid = $tid.'-value';
        $valuePos = strpos($slice, 'data-testid="'.$valueTid.'"');

        expect($chipPos)->not->toBeFalse();
        expect($labelPos)->not->toBeFalse();
        expect($valuePos)->not->toBeFalse();
        expect($chipPos)->toBeLessThan($labelPos);
        expect($chipPos)->toBeLessThan($valuePos);
    }
});
