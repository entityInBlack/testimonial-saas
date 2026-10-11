<?php

use App\Livewire\PublicSubmission;
use App\Models\Space;
use App\Models\Testimonial;
use Livewire\Livewire;

/*
 * The /s/{slug} public submission page must show a visible "Back to home"
 * link in every state the anonymous visitor can land on: the form
 * (default), the locked state (Space at cap), and the thank-you state
 * (after a successful submit; also covers duplicate / honeypot / locked
 * submits because they all render the same thanks-page view).
 *
 * The link must:
 *   - have visible text "Back to home"
 *   - point at the named "landing" route (no hardcoded URL)
 *   - carry data-testid="back-home-link" so it is discoverable
 *   - appear EXACTLY once per state
 *
 * Livewire::test() renders only the component view, NOT the guest
 * layout, so the layout's existing icon-only <a href="/"> logo link
 * is not in the HTML we are asserting on. This test therefore ties
 * the href to the back-home-link element directly (regex on the <a>
 * that owns the testid) rather than asserting that "the landing URL
 * appears anywhere in the page".
 */

/**
 * Render the PublicSubmission component and return its full HTML.
 * Centralised so the three tests stay one-liner each on the call site.
 */
function backHomeHtml(Space $space, ?array $validInput = null, bool $submit = false): string
{
    $tester = Livewire::test(PublicSubmission::class, ['slug' => $space->slug]);

    if ($validInput !== null) {
        $tester->set('name', $validInput['name'])
            ->set('email', $validInput['email'])
            ->set('address', $validInput['address'])
            ->set('testimonial', $validInput['testimonial']);

        if ($submit) {
            $tester->call('submit');
        }
    }

    return (string) $tester->html();
}

/**
 * Assert there is EXACTLY one <a> with data-testid="back-home-link"
 * whose href attribute equals $expectedHref. Uses a regex on the
 * rendered HTML so the href check is tied to the back-home-link
 * element (not "the URL appears anywhere").
 */
function assertExactlyOneBackHomeLink(string $html, string $expectedHref): void
{
    // 1. Count of data-testid="back-home-link" must be exactly 1.
    $testidCount = substr_count($html, 'data-testid="back-home-link"');
    expect($testidCount)->toBe(1, "Expected exactly 1 data-testid=\"back-home-link\", got {$testidCount}.");

    // 2. Capture every <a ... data-testid="back-home-link" ...> open tag
    //    and check its href attribute equals $expectedHref. The order
    //    of attributes in Blade is stable (href first, class second,
    //    data-testid third in the source), but the regex below
    //    accepts href in any position inside the tag.
    $pattern = '/<a\b[^>]*\bdata-testid="back-home-link"[^>]*>/i';
    $matches = [];
    $found = preg_match_all($pattern, $html, $matches);

    expect($found)->toBe(1, "Expected to find exactly 1 <a data-testid=\"back-home-link\">, found {$found}.");

    $tag = $matches[0][0];

    // Pull the href="..." value out of that single tag. Single or
    // double quotes both accepted; value is anything but the matching
    // quote or a '>'.
    $hrefPattern = '/\bhref=(["\'])(.*?)\1/i';
    $hrefMatches = [];
    $hasHref = preg_match($hrefPattern, $tag, $hrefMatches);

    expect($hasHref)->toBe(1, "back-home-link <a> has no href attribute. Tag was: {$tag}");
    expect($hrefMatches[2])->toBe($expectedHref);
}

test('form state: submission-form is shown and the back-home link appears exactly once, pointing at route(landing)', function () {
    // Would fail if: the link is missing from the form state, the link
    // is duplicated, or it points anywhere other than route('landing')
    // (e.g. a hardcoded "/", "http://localhost/", or the wrong named
    // route).
    $space = Space::factory()->create(['rating_enabled' => false]);

    $html = backHomeHtml($space);

    // The form state must be present — otherwise we're asserting on
    // the wrong view branch.
    expect($html)->toContain('data-testid="submission-form"');
    expect($html)->not->toContain('data-testid="thanks-page"');
    expect($html)->not->toContain('data-testid="locked-message"');

    assertExactlyOneBackHomeLink($html, route('landing'));
});

test('thank-you state: thanks-page is shown after a valid submit and the back-home link appears exactly once, pointing at route(landing)', function () {
    // Would fail if: the link was placed only inside the form branch
    // and therefore disappears once the visitor sees the thank-you
    // page; or if it appears more than once because the form-state
    // link was also rendered; or if its href is wrong.
    $space = Space::factory()->create(['rating_enabled' => false]);

    $html = backHomeHtml(
        $space,
        [
            'name' => 'Priya Sharma',
            'email' => 'priya@example.test',
            'address' => '42 Galaxy Way, Bengaluru',
            'testimonial' => 'A great product, highly recommend.',
        ],
        true, // submit
    );

    // The thank-you state must be present — otherwise the submit
    // didn't go through and we're back on the form.
    expect($html)->toContain('data-testid="thanks-page"');
    expect($html)->not->toContain('data-testid="submission-form"');

    assertExactlyOneBackHomeLink($html, route('landing'));
});

test('locked state: locked-message is shown when the Space is at cap and the back-home link appears exactly once, pointing at route(landing)', function () {
    // Would fail if: the link was placed only inside the form branch
    // and is missing when the Space is at the cap and the form is
    // hidden; or if it is duplicated because the locked branch and
    // the form branch both render it; or if its href is wrong.
    $space = Space::factory()->create(['rating_enabled' => false]);
    // Fill to cap with 100 LIVE rows — the same setup as LockTest.
    Testimonial::factory()->for($space)->count(100)->create();

    $html = backHomeHtml($space);

    // The locked state must be present — otherwise the cap was not
    // hit and we're still on the form.
    expect($html)->toContain('data-testid="locked-message"');
    expect($html)->not->toContain('data-testid="submission-form"');
    expect($html)->not->toContain('data-testid="thanks-page"');

    assertExactlyOneBackHomeLink($html, route('landing'));
});
