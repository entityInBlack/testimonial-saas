<?php

use App\Models\EmbedConfiguration;
use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Public embed API tests (Step 7).
 *
 * Route: GET /api/spaces/{public_id}/testimonials
 * - 404 on unknown public_id
 * - 200 with {"config": null, "testimonials": []} on soft-deleted Space
 * - 200 with the projected config + rows from scopePubliclyVisible() on live
 *
 * All 200 responses carry:
 *   - Cache-Control: contains max-age=60, no private/no-cache/no-store
 *   - Access-Control-Allow-Origin: * when an Origin header is sent
 *   - NO Set-Cookie header
 *
 * The API is outside the `web` middleware group (hard rule 8).
 */

// -----------------------------------------------------------------
// 1. Unknown public_id -> 404 (T1: assert exact JSON body too)
// -----------------------------------------------------------------

test('unknown public_id returns 404 with the exact {"error": "Not Found"} body', function () {
    // 12-char alphanumeric id that is guaranteed not to exist
    // (the route constraint allows only [A-Za-z0-9]+).
    $response = $this->getJson('/api/spaces/ZZZZZZZZZZZZ/testimonials');

    $response->assertStatus(404);
    $response->assertExactJson(['error' => 'Not Found']);
});

// -----------------------------------------------------------------
// 2. Soft-deleted Space -> 200 with the empty branch body
// -----------------------------------------------------------------

test('soft-deleted Space returns 200 with config null and empty testimonials', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    Testimonial::factory()->for($space)->count(2)->create();
    $space->delete();

    $response = $this->getJson("/api/spaces/{$space->public_id}/testimonials");

    $response->assertOk();
    $response->assertExactJson(['config' => null, 'testimonials' => []]);
});

// -----------------------------------------------------------------
// 3. Live Space with NO embed_configurations row -> config is the
//    six defaults, exactly.
// -----------------------------------------------------------------

test('live Space without an embed_configurations row returns the six defaults as config', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();

    $response = $this->getJson("/api/spaces/{$space->public_id}/testimonials");

    $response->assertOk();
    $response->assertJsonPath('config', [
        'layout'            => 'masonry',
        'dark_mode'         => false,
        'animation_enabled' => true,
        'background_color'  => null,
        'show_rating'       => true,
        'item_limit'        => 12,
    ]);
});

// -----------------------------------------------------------------
// 4. Live Space with a saved row -> config has the saved values
//    and DOES NOT contain id / space_id / created_at / updated_at.
// -----------------------------------------------------------------

test('live Space with a saved embed_configurations row returns only the six config keys', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    EmbedConfiguration::factory()->for($space)->create([
        'layout'            => 'carousel',
        'dark_mode'         => true,
        'animation_enabled' => false,
        'background_color'  => '#1A2B3C',
        'show_rating'       => false,
        'item_limit'        => 25,
    ]);

    $response = $this->getJson("/api/spaces/{$space->public_id}/testimonials");

    $response->assertOk();
    $configKeys = array_keys($response->json('config'));
    sort($configKeys);
    expect($configKeys)->toEqualCanonicalizing([
        'animation_enabled', 'background_color', 'dark_mode', 'item_limit', 'layout', 'show_rating',
    ]);
    expect($response->json('config'))->toMatchArray([
        'layout'            => 'carousel',
        'dark_mode'         => true,
        'animation_enabled' => false,
        'background_color'  => '#1A2B3C',
        'show_rating'       => false,
        'item_limit'        => 25,
    ]);
});

// -----------------------------------------------------------------
// 5. Item keys: every item has EXACTLY the seven whitelisted keys.
// -----------------------------------------------------------------

test('every item has the whitelisted key set', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    Testimonial::factory()->for($space)->count(3)->create();

    $response = $this->getJson("/api/spaces/{$space->public_id}/testimonials");
    $response->assertOk();

    foreach ($response->json('testimonials') as $item) {
        $keys = array_keys($item);
        sort($keys);
        expect($keys)->toEqualCanonicalizing([
            'company_name', 'name', 'photo_url', 'rating', 'social_url', 'submitted_at', 'testimonial',
        ]);
    }
});

// -----------------------------------------------------------------
// 6. Leak test: email + address never appear in the response body.
// -----------------------------------------------------------------

test('email and address never appear in the response body', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    Testimonial::factory()->for($space)->create([
        'email'   => 'leak-test@example.test',
        'address' => '999 Leak Street, Test City',
    ]);

    $response = $this->getJson("/api/spaces/{$space->public_id}/testimonials");
    $response->assertOk();

    $body = $response->getContent();
    expect($body)->not->toContain('leak-test@example.test')
        ->and($body)->not->toContain('999 Leak Street, Test City');

    foreach ($response->json('testimonials') as $item) {
        expect($item)->not->toHaveKey('email')
            ->and($item)->not->toHaveKey('address')
            ->and($item)->not->toHaveKey('id')
            ->and($item)->not->toHaveKey('space_id')
            ->and($item)->not->toHaveKey('public_id');
    }
});

// -----------------------------------------------------------------
// 7. show_rating=false -> every item lacks the rating key
// -----------------------------------------------------------------

test('show_rating=false omits the rating key on every item', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    EmbedConfiguration::factory()->for($space)->create(['show_rating' => false]);
    Testimonial::factory()->for($space)->count(3)->create(['rating' => 5]);

    $response = $this->getJson("/api/spaces/{$space->public_id}/testimonials");
    $response->assertOk();
    expect($response->json('testimonials'))->toHaveCount(3);
    foreach ($response->json('testimonials') as $item) {
        expect($item)->not->toHaveKey('rating');
    }
});

// -----------------------------------------------------------------
// 8. Ordering: same submitted_at -> favorite first; older favorite
//    outranks a newer non-favorite.
// -----------------------------------------------------------------

test('same submitted_at: the favorite appears first', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    $ts = Carbon::parse('2026-01-01 12:00:00');

    Testimonial::factory()->for($space)->create([
        'submitted_at' => $ts,
        'is_favorite'  => false,
        'testimonial'  => 'NOT-FAV',
    ]);
    Testimonial::factory()->for($space)->create([
        'submitted_at' => $ts,
        'is_favorite'  => true,
        'testimonial'  => 'FAV',
    ]);

    $response = $this->getJson("/api/spaces/{$space->public_id}/testimonials");
    $response->assertOk();

    expect($response->json('testimonials.0.testimonial'))->toBe('FAV')
        ->and($response->json('testimonials.1.testimonial'))->toBe('NOT-FAV');
});

test('older favorite outranks a newer non-favorite', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();

    Testimonial::factory()->for($space)->create([
        'submitted_at' => Carbon::now()->subDay(),
        'is_favorite'  => true,
        'testimonial'  => 'OLD-FAV',
    ]);
    Testimonial::factory()->for($space)->create([
        'submitted_at' => Carbon::now(),
        'is_favorite'  => false,
        'testimonial'  => 'NEW-NOT-FAV',
    ]);

    $response = $this->getJson("/api/spaces/{$space->public_id}/testimonials");
    $response->assertOk();

    expect($response->json('testimonials.0.testimonial'))->toBe('OLD-FAV')
        ->and($response->json('testimonials.1.testimonial'))->toBe('NEW-NOT-FAV');
});

// -----------------------------------------------------------------
// 9. Only publicly visible rows are returned (hidden, no-consent,
//    not-WoL, soft-deleted are all excluded).
// -----------------------------------------------------------------

test('only publicly visible rows are returned', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();

    // 1. Public (will appear)
    Testimonial::factory()->for($space)->create([
        'consent_given' => true, 'is_wall_of_love' => true, 'is_hidden' => false,
        'testimonial' => 'PUBLIC',
    ]);

    // 2. Hidden
    Testimonial::factory()->for($space)->hidden()->create([
        'consent_given' => true, 'is_wall_of_love' => true, 'testimonial' => 'HIDDEN',
    ]);

    // 3. No consent
    Testimonial::factory()->for($space)->noConsent()->create([
        'is_wall_of_love' => true, 'testimonial' => 'NO-CONSENT',
    ]);

    // 4. Not on Wall of Love
    Testimonial::factory()->for($space)->pending()->create([
        'consent_given' => true, 'testimonial' => 'NOT-WOL',
    ]);

    // 5. Soft-deleted
    $trashed = Testimonial::factory()->for($space)->create([
        'consent_given' => true, 'is_wall_of_love' => true, 'testimonial' => 'TRASHED',
    ]);
    $trashed->delete();

    $response = $this->getJson("/api/spaces/{$space->public_id}/testimonials");
    $response->assertOk();
    expect($response->json('testimonials'))->toHaveCount(1)
        ->and($response->json('testimonials.0.testimonial'))->toBe('PUBLIC');
});

// -----------------------------------------------------------------
// 10. One Space only: rows from a second Space (same owner) never appear.
// -----------------------------------------------------------------

test('rows from a second Space (same owner) never appear', function () {
    $user = User::factory()->create();
    $spaceA = Space::factory()->for($user)->create();
    $spaceB = Space::factory()->for($user)->create();

    Testimonial::factory()->for($spaceA)->create(['testimonial' => 'IN-A']);
    Testimonial::factory()->for($spaceB)->create(['testimonial' => 'IN-B']);

    $response = $this->getJson("/api/spaces/{$spaceA->public_id}/testimonials");
    $response->assertOk();

    $bodies = array_column($response->json('testimonials'), 'testimonial');
    expect($bodies)->toContain('IN-A')
        ->and($bodies)->not->toContain('IN-B');
});

// -----------------------------------------------------------------
// 11. Limit clamp (hard rule 9).
// -----------------------------------------------------------------

test('?limit=200 clamps to 50 even when the Space has 60+ public rows', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    Testimonial::factory()->for($space)->count(60)->create();

    $response = $this->getJson("/api/spaces/{$space->public_id}/testimonials?limit=200");
    $response->assertOk();
    expect($response->json('testimonials'))->toHaveCount(50);
});

test('no ?limit with item_limit=12 returns 12 rows (when 20 public rows exist)', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    EmbedConfiguration::factory()->for($space)->create(['item_limit' => 12]);
    Testimonial::factory()->for($space)->count(20)->create();

    $response = $this->getJson("/api/spaces/{$space->public_id}/testimonials");
    $response->assertOk();
    expect($response->json('testimonials'))->toHaveCount(12);
});

test('item_limit=12 + ?limit=40 returns 40', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    EmbedConfiguration::factory()->for($space)->create(['item_limit' => 12]);
    Testimonial::factory()->for($space)->count(60)->create();

    $response = $this->getJson("/api/spaces/{$space->public_id}/testimonials?limit=40");
    $response->assertOk();
    expect($response->json('testimonials'))->toHaveCount(40);
});

test('item_limit=12 + ?limit=5 returns 5', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    EmbedConfiguration::factory()->for($space)->create(['item_limit' => 12]);
    Testimonial::factory()->for($space)->count(20)->create();

    $response = $this->getJson("/api/spaces/{$space->public_id}/testimonials?limit=5");
    $response->assertOk();
    expect($response->json('testimonials'))->toHaveCount(5);
});

test('bad ?limit (0, negative, non-numeric) is ignored and item_limit is used', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    EmbedConfiguration::factory()->for($space)->create(['item_limit' => 12]);
    Testimonial::factory()->for($space)->count(20)->create();

    foreach (['0', '-3', 'abc', '1.5'] as $bad) {
        $response = $this->getJson("/api/spaces/{$space->public_id}/testimonials?limit={$bad}");
        $response->assertOk();
        expect($response->json('testimonials'))->toHaveCount(12);
    }
});

// -----------------------------------------------------------------
// 12. CORS: Origin header -> Access-Control-Allow-Origin: *
//     (T2: live, soft-deleted, unknown id, and preflight)
// -----------------------------------------------------------------

test('CORS: live Space 200 carries Access-Control-Allow-Origin: *', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();

    $response = $this->withHeaders(['Origin' => 'https://example.com'])
        ->getJson("/api/spaces/{$space->public_id}/testimonials");

    $response->assertOk();
    expect($response->headers->get('Access-Control-Allow-Origin'))->toBe('*');
});

test('CORS: soft-deleted Space 200 carries Access-Control-Allow-Origin: *', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    $space->delete();

    $response = $this->withHeaders(['Origin' => 'https://example.com'])
        ->getJson("/api/spaces/{$space->public_id}/testimonials");

    $response->assertOk();
    expect($response->headers->get('Access-Control-Allow-Origin'))->toBe('*');
});

test('CORS: unknown public_id 404 carries Access-Control-Allow-Origin: *', function () {
    $response = $this->withHeaders(['Origin' => 'https://example.com'])
        ->getJson('/api/spaces/ZZZZZZZZZZZZ/testimonials');

    $response->assertStatus(404);
    expect($response->headers->get('Access-Control-Allow-Origin'))->toBe('*');
});

test('CORS: preflight OPTIONS returns 2xx with Access-Control-Allow-Origin: *', function () {
    $response = $this->call('OPTIONS', '/api/spaces/ZZZZZZZZZZZZ/testimonials', [], [], [], [
        'HTTP_ORIGIN' => 'https://example.com',
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
    ]);

    // 2xx success range. Laravel's HandleCors may return 200 or 204.
    expect($response->getStatusCode())->toBeGreaterThanOrEqual(200)
        ->and($response->getStatusCode())->toBeLessThan(300)
        ->and($response->headers->get('Access-Control-Allow-Origin'))->toBe('*');
});

// -----------------------------------------------------------------
// 13. Cache + no Set-Cookie
// -----------------------------------------------------------------

test('200 response has Cache-Control with max-age=60 and no private/no-cache/no-store, and no Set-Cookie', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();

    $response = $this->getJson("/api/spaces/{$space->public_id}/testimonials");
    $response->assertOk();

    $cc = (string) $response->headers->get('Cache-Control');
    expect($cc)->toContain('max-age=60')
        ->and($cc)->not->toContain('private')
        ->and($cc)->not->toContain('no-cache')
        ->and($cc)->not->toContain('no-store');

    $response->assertHeaderMissing('Set-Cookie');
});

// -----------------------------------------------------------------
// 14. Throttle: 120 requests succeed, the 121st returns 429
// -----------------------------------------------------------------

test('throttle: 120 requests succeed, 121st returns 429 (same IP)', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();

    for ($i = 1; $i <= 120; $i++) {
        $r = $this->getJson("/api/spaces/{$space->public_id}/testimonials");
        expect($r->status())->toBeLessThan(400, "Request #{$i} failed unexpectedly: status=".$r->status());
    }

    $r121 = $this->getJson("/api/spaces/{$space->public_id}/testimonials");
    $r121->assertStatus(429);
});

// -----------------------------------------------------------------
// 15. Photo: profile_photo set + Space's field_config photo field
//     disabled -> photo_url is a non-null absolute URL.
//     Testimonial without photo -> photo_url is null.
// -----------------------------------------------------------------

test('photo URL is built from profile_photo even when the field_config disables photos', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create([
        'field_config' => [
            'company_name' => ['enabled' => true,  'required' => false],
            'social_url'   => ['enabled' => false, 'required' => false],
            'profile_photo' => ['enabled' => false, 'required' => false], // disabled
        ],
    ]);
    Testimonial::factory()->for($space)->create([
        'profile_photo' => 'photos/abc123.webp',
    ]);

    $response = $this->getJson("/api/spaces/{$space->public_id}/testimonials");
    $response->assertOk();
    $photoUrl = (string) $response->json('testimonials.0.photo_url');

    // T3: the public disk URL is APP_URL + '/storage/' + path. The
    // expected prefix matches config/filesystems.php 'public' disk.
    $expectedPrefix = rtrim((string) config('app.url'), '/').'/storage/';
    expect($photoUrl)->toStartWith($expectedPrefix)
        ->and($photoUrl)->toEndWith('/storage/photos/abc123.webp');
});

test('testimonial without profile_photo has photo_url = null', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    Testimonial::factory()->for($space)->create(['profile_photo' => null]);

    $response = $this->getJson("/api/spaces/{$space->public_id}/testimonials");
    $response->assertOk();
    expect($response->json('testimonials.0.photo_url'))->toBeNull();
});

// -----------------------------------------------------------------
// 21. Slug rename: after changing the Space's slug, the API at the
//     same public_id still returns 200 with the same testimonials.
// -----------------------------------------------------------------

test('renaming the Space slug does not break the API (public_id is the embed key)', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    Testimonial::factory()->for($space)->create(['testimonial' => 'UNCHANGED']);

    // 1. baseline
    $before = $this->getJson("/api/spaces/{$space->public_id}/testimonials");
    $before->assertOk();
    $countBefore = count($before->json('testimonials'));
    expect($countBefore)->toBeGreaterThan(0);

    // 2. rename the slug
    $space->slug = 'renamed-'.$space->slug;
    $space->save();

    // 3. same public_id still works
    $after = $this->getJson("/api/spaces/{$space->public_id}/testimonials");
    $after->assertOk();
    expect(count($after->json('testimonials')))->toBe($countBefore)
        ->and($after->json('testimonials.0.testimonial'))->toBe('UNCHANGED');
});

// -----------------------------------------------------------------
// 22. T4 — social_url scheme filtering (server-side)
// -----------------------------------------------------------------

test('social_url scheme filter: https kept; javascript/ftp/JAVASCRIPT become null', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();

    $rows = [
        ['name' => 'OK',          'social_url' => 'https://example.com/a'],
        ['name' => 'XSS-low',     'social_url' => 'javascript:alert(1)'],
        ['name' => 'XSS-upper',   'social_url' => 'JAVASCRIPT:alert(1)'],
        ['name' => 'FTP',         'social_url' => 'ftp://x.test'],
    ];
    foreach ($rows as $r) {
        Testimonial::factory()->for($space)->create([
            'name'       => $r['name'],
            'social_url' => $r['social_url'],
        ]);
    }

    $response = $this->getJson("/api/spaces/{$space->public_id}/testimonials");
    $response->assertOk();

    // index by name so the test is order-independent
    $byName = [];
    foreach ($response->json('testimonials') as $item) {
        $byName[$item['name']] = $item;
    }

    expect($byName['OK']['social_url'])->toBe('https://example.com/a')
        ->and($byName['XSS-low']['social_url'])->toBeNull()
        ->and($byName['XSS-upper']['social_url'])->toBeNull()
        ->and($byName['FTP']['social_url'])->toBeNull();
});
