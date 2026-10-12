<?php

use App\Livewire\Spaces\SpaceIndex;
use App\Models\Space;
use App\Models\User;
use Livewire\Livewire;

/**
 * Wave 1, Task B (B3): Spaces row clickability + public URL link.
 *
 * TB1: a user with one Space sees, on /spaces:
 *   - an <a> with data-testid="space-row-title-link" whose href
 *     equals the Inbox URL for that Space (built via route() with
 *     the spaceId query param);
 *   - an <a> with data-testid="space-row-public-link" whose href
 *     is the Space's public URL (/s/{slug}), with target="_blank"
 *     and rel containing "noopener".
 *
 * Would fail if: the title is no longer a link, the link href is
 * built by string concatenation (and the host changes), the
 * public link loses its target/rel attributes, or the new test
 * ids are removed.
 */
test('Spaces row: title links to that Space\'s Inbox, public URL is a new-tab link', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create([
        'title' => 'Shiplog Reviews',
        'slug' => 'shiplog-reviews',
    ]);

    $html = (string) Livewire::actingAs($user)
        ->test(SpaceIndex::class)
        ->html();

    $expectedInboxHref = route('inbox.index', ['spaceId' => $space->id]);

    // Title link → Inbox for that Space.
    expect($html)->toContain('data-testid="space-row-title-link"')
        ->and($html)->toContain('href="'.$expectedInboxHref.'"')
        ->and($html)->toContain('>'.$space->title.'</a>');

    // Public URL link → /s/{slug}, opens in a new tab, rel has noopener.
    $publicHref = url('/s/'.$space->slug);
    expect($html)->toContain('data-testid="space-row-public-link"')
        ->and($html)->toContain('href="'.$publicHref.'"')
        ->and($html)->toContain('target="_blank"')
        ->and($html)->toMatch('/rel="[^"]*noopener[^"]*"/');
});
