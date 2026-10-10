<?php

use App\Livewire\Spaces\EmbedBuilder;
use App\Models\EmbedConfiguration;
use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;
use Livewire\Livewire;

/**
 * Embed builder (Step 7) — auth + content tests.
 *
 *   16. Owner GET -> 200; body contains public_id, data-testimonial-space,
 *       and {APP_URL}/embed.js; does NOT contain the slug inside the snippet.
 *   17. Cross-user: user B gets 404 (page load AND a real Livewire
 *       /livewire/update POST to the save action). CONTROL: the owner
 *       gets 200 and the row is saved.
 *   18. Guest GET -> redirect to login.
 *   19. Save: item_limit=200 clamps to 50; item_limit=0 clamps to 1;
 *       background_color "red" is a validation error; layout "grid" is
 *       a validation error; "#1a2B3c" is accepted.
 *   20. Preview: with show_rating off, the rendered preview contains
 *       NO rating markup for a rated row; with a <script>alert(1)</script>
 *       testimonial, the HTML contains the escaped form and not the raw tag.
 */

// -----------------------------------------------------------------
// 16. Owner GET -> 200 with the snippet
// -----------------------------------------------------------------

test('owner GET /spaces/{space}/embed -> 200, body contains public_id, embed.js URL, and NOT the slug', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();

    $response = $this->actingAs($user)->get(route('spaces.embed', ['space' => $space->id]));
    $response->assertOk();

    $body = $response->getContent();
    $expectedJs = rtrim((string) config('app.url'), '/').'/embed.js';

    expect($body)->toContain($space->public_id)
        ->and($body)->toContain('data-testimonial-space')
        ->and($body)->toContain($expectedJs)
        ->and($body)->not->toContain('data-testimonial-space="'.$space->slug.'"');
});

// -----------------------------------------------------------------
// 17a. Cross-user: page load -> 404
// -----------------------------------------------------------------

test('user B cannot load user A\'s embed builder (page load -> 404)', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $space = Space::factory()->for($alice)->create();

    $this->actingAs($bob)
        ->get(route('spaces.embed', ['space' => $space->id]))
        ->assertStatus(404);
});

// -----------------------------------------------------------------
// 17b. Cross-user via real /livewire/update POST -> 404
// -----------------------------------------------------------------

test('user B POST to /livewire/update (save action) gets 404; user A (control) gets 200 and the row is saved', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $space = Space::factory()->for($alice)->create();

    // 1. As Alice, mount the EmbedBuilder and grab the snapshot.
    $aliceMounted = Livewire::actingAs($alice)
        ->test(EmbedBuilder::class, ['space' => $space->id]);

    $reflection = new ReflectionObject($aliceMounted);
    $lastStateProp = $reflection->getProperty('lastState');
    $lastStateProp->setAccessible(true);
    $lastState = $lastStateProp->getValue($aliceMounted);
    $snapshot = $lastState->getSnapshot();

    // 2. Build the payload that calls `save`.
    $payload = [
        'components' => [
            [
                'snapshot' => json_encode($snapshot),
                'calls' => [
                    ['path' => '', 'method' => 'save', 'params' => []],
                ],
                'updates' => [],
            ],
        ],
    ];

    // 3. Bob -> 404.
    $bobResponse = $this->actingAs($bob)
        ->withHeaders(['X-Livewire' => '1'])
        ->post('/livewire/update', $payload);
    $bobResponse->assertStatus(404);

    // 4. CONTROL: Alice -> 200 and the row is saved.
    $aliceResponse = $this->actingAs($alice)
        ->withHeaders(['X-Livewire' => '1'])
        ->post('/livewire/update', $payload);
    $aliceResponse->assertOk();

    // The default config was applied — the row exists.
    $row = EmbedConfiguration::where('space_id', $space->id)->first();
    expect($row)->not->toBeNull()
        ->and((int) $row->item_limit)->toBe(12);
});

// -----------------------------------------------------------------
// 18. Guest GET -> redirect to login
// -----------------------------------------------------------------

test('guest GET /spaces/{space}/embed -> redirect to login', function () {
    $space = Space::factory()->create();

    $this->get(route('spaces.embed', ['space' => $space->id]))
        ->assertRedirect(route('login'));
});

// -----------------------------------------------------------------
// 19. Save: item_limit clamp, color/layout validation
// -----------------------------------------------------------------

test('item_limit=200 is stored as 50', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();

    Livewire::actingAs($user)
        ->test(EmbedBuilder::class, ['space' => $space->id])
        ->set('itemLimitInput', '200')
        ->call('save')
        ->assertHasNoErrors();

    $row = EmbedConfiguration::where('space_id', $space->id)->first();
    expect((int) $row->item_limit)->toBe(50);
});

test('item_limit=0 is stored as 1', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();

    Livewire::actingAs($user)
        ->test(EmbedBuilder::class, ['space' => $space->id])
        ->set('itemLimitInput', '0')
        ->call('save')
        ->assertHasNoErrors();

    $row = EmbedConfiguration::where('space_id', $space->id)->first();
    expect((int) $row->item_limit)->toBe(1);
});

test('background_color "red" is a validation error', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();

    Livewire::actingAs($user)
        ->test(EmbedBuilder::class, ['space' => $space->id])
        ->set('backgroundColor', 'red')
        ->call('save')
        ->assertHasErrors(['backgroundColor']);
});

test('layout "grid" is a validation error', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();

    Livewire::actingAs($user)
        ->test(EmbedBuilder::class, ['space' => $space->id])
        ->set('layout', 'grid')
        ->call('save')
        ->assertHasErrors(['layout']);
});

test('background_color "#1a2B3c" is accepted (mixed case)', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();

    Livewire::actingAs($user)
        ->test(EmbedBuilder::class, ['space' => $space->id])
        ->set('backgroundColor', '#1a2B3c')
        ->call('save')
        ->assertHasNoErrors();

    $row = EmbedConfiguration::where('space_id', $space->id)->first();
    expect($row->background_color)->toBe('#1a2B3c');
});

// -----------------------------------------------------------------
// 20. Preview: show_rating off hides rating markup; <script> shows escaped.
// -----------------------------------------------------------------

test('with show_rating off the preview renders no rating markup for a rated row', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    EmbedConfiguration::factory()->for($space)->create(['show_rating' => false]);
    Testimonial::factory()->for($space)->create([
        'rating' => 5,
        'consent_given' => true,
        'is_wall_of_love' => true,
    ]);

    $response = $this->actingAs($user)
        ->get(route('spaces.embed', ['space' => $space->id]));
    $response->assertOk();
    $html = (string) $response->getContent();
    expect($html)->not->toContain('data-testid="preview-rating"')
        ->and($html)->not->toContain('★');
});

test('a <script>alert(1)</script> testimonial in the preview is rendered escaped, not raw', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    Testimonial::factory()->for($space)->create([
        'testimonial' => '<script>alert(1)</script> after-script',
        'consent_given' => true,
        'is_wall_of_love' => true,
    ]);

    $response = $this->actingAs($user)
        ->get(route('spaces.embed', ['space' => $space->id]));
    $response->assertOk();
    $html = (string) $response->getContent();
    // The raw <script>alert(1)</script> tag must NOT appear in the
    // rendered preview body. (The page itself contains Livewire
    // and Vite asset <script> tags, so we look for the specific
    // body-shaped substring that the resource would have rendered
    // raw.)
    expect($html)->toContain('&lt;script&gt;alert(1)&lt;/script&gt;')
        ->and($html)->not->toContain('alert(1)</script> after-script');
});

// -----------------------------------------------------------------
// T5 — background_color: only exactly #RRGGBB or empty (null) accepted
// -----------------------------------------------------------------

test('background_color — additional rejections beyond "red"', function () {
    foreach (['abc', '#', '12', '#12345', '#1234567', '#GGGGGG'] as $bad) {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();

        Livewire::actingAs($user)
            ->test(EmbedBuilder::class, ['space' => $space->id])
            ->set('backgroundColor', $bad)
            ->call('save')
            ->assertHasErrors(['backgroundColor']);

        // No row created
        expect(EmbedConfiguration::where('space_id', $space->id)->exists())->toBeFalse();
    }
});

test('background_color — empty string saves as null', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();

    Livewire::actingAs($user)
        ->test(EmbedBuilder::class, ['space' => $space->id])
        ->set('backgroundColor', '')
        ->call('save')
        ->assertHasNoErrors();

    $row = EmbedConfiguration::where('space_id', $space->id)->first();
    expect($row)->not->toBeNull()
        ->and($row->background_color)->toBeNull();
});

// -----------------------------------------------------------------
// T6 — item_limit: no mid-typing rewrite; non-integer surfaces an error
// -----------------------------------------------------------------

test('item_limit_input "abc" surfaces validation error and saves nothing', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();

    $component = Livewire::actingAs($user)
        ->test(EmbedBuilder::class, ['space' => $space->id])
        ->set('itemLimitInput', 'abc');

    // The input is NOT rewritten.
    expect($component->get('itemLimitInput'))->toBe('abc');

    $component->call('save')
        ->assertHasErrors(['itemLimitInput']);

    expect(EmbedConfiguration::where('space_id', $space->id)->exists())->toBeFalse();
});

test('item_limit_input "200" (without save) is not rewritten; on save stores 50 and shows "50"', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();

    $component = Livewire::actingAs($user)
        ->test(EmbedBuilder::class, ['space' => $space->id])
        ->set('itemLimitInput', '200');

    // The input is NOT rewritten mid-typing.
    expect($component->get('itemLimitInput'))->toBe('200');

    // Now save — the input is clamped to 50 server-side and the
    // form re-syncs to "50" after save.
    $component->call('save')->assertHasNoErrors();

    $row = EmbedConfiguration::where('space_id', $space->id)->first();
    expect($row)->not->toBeNull()
        ->and((int) $row->item_limit)->toBe(50)
        ->and($component->get('itemLimitInput'))->toBe('50');
});

test('item_limit_input "0" stores 1 after save', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();

    Livewire::actingAs($user)
        ->test(EmbedBuilder::class, ['space' => $space->id])
        ->set('itemLimitInput', '0')
        ->call('save')
        ->assertHasNoErrors();

    $row = EmbedConfiguration::where('space_id', $space->id)->first();
    expect($row)->not->toBeNull()
        ->and((int) $row->item_limit)->toBe(1);
});

// -----------------------------------------------------------------
// T7 — preview reflects layout, dark mode, photo
// -----------------------------------------------------------------

test('preview markup reflects the selected options', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    Testimonial::factory()->for($space)->create([
        'profile_photo' => 'photos/abc123.webp',
        'rating'        => 5,
        'consent_given' => true,
        'is_wall_of_love' => true,
    ]);

    // 1. Baseline: default layout is masonry. The preview container
    //    carries data-layout="masonry".
    $r1 = $this->actingAs($user)
        ->get(route('spaces.embed', ['space' => $space->id]));
    $r1->assertOk();
    $html1 = (string) $r1->getContent();
    expect($html1)->toContain('data-layout="masonry"');

    // 2. Save a config row with carousel/dark/colour and re-fetch.
    //    The server reads the saved row and renders the preview
    //    with the configured values.
    EmbedConfiguration::factory()->for($space)->create([
        'layout'           => 'carousel',
        'dark_mode'        => true,
        'background_color' => '#1a2B3c',
    ]);

    $r2 = $this->actingAs($user)
        ->get(route('spaces.embed', ['space' => $space->id]));
    $r2->assertOk();
    $html2 = (string) $r2->getContent();

    expect($html2)->toContain('data-layout="carousel"')
        ->and($html2)->toContain('data-dark="1"');
    // The background colour is applied inline (preview isValidBackgroundColor).
    expect($html2)->toContain('background-color: #1a2B3c');
    // An img whose src ends with the photo path (the Storage::url
    // returns APP_URL + '/storage/' + path).
    expect($html2)->toMatch('/<img[^>]*src="[^"]*\/storage\/photos\/abc123\.webp"/');
    // Photos are marked with data-testid="preview-photo".
    expect($html2)->toContain('data-testid="preview-photo"');
});

// -----------------------------------------------------------------
// T8 — soft-deleted Space — owner GET -> 404
// -----------------------------------------------------------------

test('owner GET /spaces/{space}/embed for their own soft-deleted Space returns 404', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    $space->delete();

    $this->actingAs($user)
        ->get(route('spaces.embed', ['space' => $space->id]))
        ->assertStatus(404);
});
