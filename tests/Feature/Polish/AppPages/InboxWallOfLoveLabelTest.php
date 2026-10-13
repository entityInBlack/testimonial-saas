<?php

use App\Livewire\Inbox\InboxRow;
use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;
use Livewire\Livewire;

/**
 * Wave 1, Task B (B5, D5): the Inbox row never renders the bare
 * "WoL" label — it shows "Wall of Love" in full, both when the row
 * is off and on the wall.
 *
 * TB6 (conditional): if D5 was applied, the rendered Inbox row
 * contains "Wall of Love" and no standalone "WoL" label.
 *
 * Would fail if: a designer reverts the button label to the
 * abbreviated "WoL" form.
 */
test('Inbox row never shows the "WoL" abbreviation; "Wall of Love" is shown in full', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();

    // Row OFF the Wall of Love.
    $off = Testimonial::factory()->for($space)->create([
        'name' => 'Off-row',
        'consent_given' => true,
        'is_wall_of_love' => false,
    ]);
    $htmlOff = (string) Livewire::actingAs($user)
        ->test(InboxRow::class, ['testimonialId' => $off->id])
        ->html();

    // Row ON the Wall of Love.
    $on = Testimonial::factory()->for($space)->create([
        'name' => 'On-row',
        'consent_given' => true,
        'is_wall_of_love' => true,
    ]);
    $htmlOn = (string) Livewire::actingAs($user)
        ->test(InboxRow::class, ['testimonialId' => $on->id])
        ->html();

    expect($htmlOff)
        ->toContain('Wall of Love')
        ->not->toContain('>WoL<')
        ->not->toContain('WoL ✓');

    expect($htmlOn)
        ->toContain('Wall of Love')
        ->not->toContain('>WoL<')
        ->not->toContain('WoL ✓');
});
