<?php

use App\Livewire\Dashboard\DashboardIndex;
use App\Livewire\Inbox\InboxIndex;
use App\Livewire\Inbox\InboxRow;
use App\Models\DeletionRequest;
use App\Models\EmbedConfiguration;
use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

/**
 * Part E — seeded-data tests (Step 8 / task 8.5).
 *
 * The `php artisan migrate --seed` baseline produced by
 * `DatabaseSeeder` (BaseUsersSeeder + FixtureSeeder) is the exact
 * shape the rest of Step 8 promises. These tests assert that shape
 * row-by-row so a refactor of either seeder immediately surfaces
 * the divergence.
 *
 * Every test seeds the database through DatabaseSeeder, so the
 * rows are exactly the ones the demo / dev server gets.
 *
 * "would fail if" notes are in the docblock above each test.
 */

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

// ---------------------------------------------------------------------
// E1 — counts
// ---------------------------------------------------------------------

test('E1 counts: Maya has 6 LIVE brightcopy + 3 LIVE course-launch testimonials', function () {
    // would fail if: the seeder dropped a row, doubled a row, or
    // wrong-named a Space.
    $maya = User::where('email', 'maya@brightcopy.co')->firstOrFail();
    $bc = Space::where('user_id', $maya->id)->where('slug', 'brightcopy-wins')->firstOrFail();
    $cl = Space::where('user_id', $maya->id)->where('slug', 'course-launch')->firstOrFail();

    expect(Testimonial::where('space_id', $bc->id)->count())->toBe(6);
    expect(Testimonial::where('space_id', $cl->id)->count())->toBe(3);
});

test('E1 counts: Dev has exactly 2 LIVE Spaces', function () {
    // would fail if: the seeder dropped a Space or soft-deleted one.
    $dev = User::where('email', 'dev@shiplog.io')->firstOrFail();

    expect(Space::where('user_id', $dev->id)->count())->toBe(2);
    expect(Space::where('user_id', $dev->id)->whereNull('deleted_at')->count())->toBe(2);
});

test('E1 counts: shiplog-reviews has 100 LIVE + 101 withTrashed', function () {
    // would fail if: the seeder over- or under-seeded shiplog, or
    // the soft-delete of Ana was lost.
    $dev = User::where('email', 'dev@shiplog.io')->firstOrFail();
    $shiplog = Space::where('user_id', $dev->id)->where('slug', 'shiplog-reviews')->firstOrFail();

    expect(Testimonial::where('space_id', $shiplog->id)->count())->toBe(100);
    expect(Testimonial::withTrashed()->where('space_id', $shiplog->id)->count())->toBe(101);
});

test('E1 counts: beta-testers has 12 LIVE + 12 withTrashed', function () {
    // would fail if: the seeder over- or under-seeded beta.
    $dev = User::where('email', 'dev@shiplog.io')->firstOrFail();
    $beta = Space::where('user_id', $dev->id)->where('slug', 'beta-testers')->firstOrFail();

    expect(Testimonial::where('space_id', $beta->id)->count())->toBe(12);
    expect(Testimonial::withTrashed()->where('space_id', $beta->id)->count())->toBe(12);
});

// ---------------------------------------------------------------------
// E2 — is_public (STORED GENERATED column) for the five named
//      shiplog-reviews rows reflects the consent / wall-of-love /
//      soft-delete state.
// ---------------------------------------------------------------------

test('E2 is_public (named rows): Priya 1, Ben 0, Tom 0, Sara 0, Ana 0', function () {
    // would fail if: is_public STORED expression regressed, or any
    // of the five named rows had its consent / WoL / hidden /
    // soft-delete flag flipped.
    $dev = User::where('email', 'dev@shiplog.io')->firstOrFail();
    $shiplog = Space::where('user_id', $dev->id)->where('slug', 'shiplog-reviews')->firstOrFail();

    $map = [
        'priya@nimbus.dev'          => 1,
        'ben@example.test'          => 0,
        'tom.alvarez.shl@example.test' => 0,
        'sara@example.test'         => 0,
        'ana.ruiz.shl@example.test' => 0,
    ];

    foreach ($map as $email => $expected) {
        $row = Testimonial::withTrashed()
            ->where('space_id', $shiplog->id)
            ->where('email', $email)
            ->firstOrFail();
        expect((int) $row->is_public)->toBe($expected, "is_public for {$email}");
    }
});

test('E2 is_public: exactly 21 live shiplog rows have is_public = 1', function () {
    // would fail if: the four-condition is_public rule changed, or
    // any of the 20 named-or-WoL rows lost a flag.
    $dev = User::where('email', 'dev@shiplog.io')->firstOrFail();
    $shiplog = Space::where('user_id', $dev->id)->where('slug', 'shiplog-reviews')->firstOrFail();

    expect(Testimonial::where('space_id', $shiplog->id)->where('is_public', 1)->count())->toBe(21);
});

test('E2 generated rows: first 20 by id have is_wall_of_love = 1, other 76 have 0', function () {
    // would fail if: the order of insertion of the 96 generated
    // rows was changed so the lowest-id 20 were not the WoL ones.
    $dev = User::where('email', 'dev@shiplog.io')->firstOrFail();
    $shiplog = Space::where('user_id', $dev->id)->where('slug', 'shiplog-reviews')->firstOrFail();

    $generated = Testimonial::where('space_id', $shiplog->id)
        ->where('email', 'like', 'shiplog-c%')
        ->orderBy('id')
        ->get();

    expect($generated->count())->toBe(96);

    $first20 = $generated->take(20);
    $rest = $generated->slice(20, 76);

    expect($first20->every(fn ($r) => (int) $r->is_wall_of_love === 1))->toBeTrue();
    expect($rest->every(fn ($r) => (int) $r->is_wall_of_love === 0))->toBeTrue();
});

// ---------------------------------------------------------------------
// E3 — cap message on /s/{slug}
// ---------------------------------------------------------------------

test('E3 cap: /s/shiplog-reviews shows the cap message (100 LIVE == 100 cap)', function () {
    // would fail if: the cap message text was removed, or the
    // cap-check itself was bypassed.
    $this->get('/s/shiplog-reviews')
        ->assertStatus(200)
        ->assertSee('This Space is not accepting responses right now.', false);
});

test('E3 cap: /s/brightcopy-wins does NOT show the cap message', function () {
    // would fail if: the cap message was rendered for any Space
    // (e.g. off-by-one in the cap check) or the 6 testimonials
    // exceeded the cap.
    $this->get('/s/brightcopy-wins')
        ->assertStatus(200)
        ->assertDontSee('This Space is not accepting responses right now.', false);
});

// ---------------------------------------------------------------------
// E4 — Total customers
// ---------------------------------------------------------------------

/**
 * Extract the rendered number inside the
 * `data-testid="counter-total-customers-value"` div from the
 * dashboard's HTML. The number is rendered through `number_format`
 * so the assertion uses the rendered string verbatim.
 */
function totalCustomersFromHtml(string $html): ?string
{
    if (preg_match('/data-testid="counter-total-customers-value"[^>]*>([^<]+)</', $html, $m)) {
        return trim($m[1]);
    }

    return null;
}

test('E4 total customers: dashboard renders 111 for Dev and 9 for Maya, parsed from the counter div', function () {
    // would fail if: the Total-customers counter div was mis-tagged,
    // the rendered number_format string changed, or
    // COUNT(DISTINCT email) was wrong.
    $maya = User::where('email', 'maya@brightcopy.co')->firstOrFail();
    $dev = User::where('email', 'dev@shiplog.io')->firstOrFail();

    // Real HTTP: GET the /dashboard route as Dev, parse the counter
    // div. A bare `assertSee('111')` is rejected by the prompt
    // because '111' can appear elsewhere (e.g. a testimonial id
    // 111 in the inbox list, or in the free-plan note copy that
    // contains "100 testimonials per Space").
    $devResponse = $this->actingAs($dev)->get(route('dashboard'));
    $devResponse->assertStatus(200);
    $devHtml = (string) $devResponse->getContent();
    $devCounter = totalCustomersFromHtml($devHtml);
    expect($devCounter)->toBe('111');

    $mayaResponse = $this->actingAs($maya)->get(route('dashboard'));
    $mayaResponse->assertStatus(200);
    $mayaCounter = totalCustomersFromHtml((string) $mayaResponse->getContent());
    expect($mayaCounter)->toBe('9');
});

test('E4 total customers: raw SQL COUNT(DISTINCT email) for Dev is 111 and Maya is 9', function () {
    // would fail if: the Livewire counter formula and the SQL
    // formula diverged, or the seeder produced different rows.
    $maya = User::where('email', 'maya@brightcopy.co')->firstOrFail();
    $dev = User::where('email', 'dev@shiplog.io')->firstOrFail();

    $devCount = (int) DB::table('testimonials')
        ->join('spaces', 'spaces.id', '=', 'testimonials.space_id')
        ->where('spaces.user_id', $dev->id)
        ->whereNull('spaces.deleted_at')
        ->whereNull('testimonials.deleted_at')
        ->distinct()
        ->count('testimonials.email');

    $mayaCount = (int) DB::table('testimonials')
        ->join('spaces', 'spaces.id', '=', 'testimonials.space_id')
        ->where('spaces.user_id', $maya->id)
        ->whereNull('spaces.deleted_at')
        ->whereNull('testimonials.deleted_at')
        ->distinct()
        ->count('testimonials.email');

    expect($devCount)->toBe(111);
    expect($mayaCount)->toBe(9);
});

// ---------------------------------------------------------------------
// E5 — Embed JSON
// ---------------------------------------------------------------------

test('E5 embed shiplog: 21 items, names include Priya, exclude hidden/soft-deleted/no-consent, no email/address/id leak', function () {
    // would fail if: the embed endpoint exposed an extra row, lost
    // a row, or leaked a private field.
    $dev = User::where('email', 'dev@shiplog.io')->firstOrFail();
    $shiplog = Space::where('user_id', $dev->id)->where('slug', 'shiplog-reviews')->firstOrFail();

    $response = $this->get('/api/spaces/'.$shiplog->public_id.'/testimonials');
    $response->assertStatus(200);

    $body = (string) $response->getContent();
    $payload = $response->json();

    expect($payload)->toHaveKey('testimonials');
    expect(count($payload['testimonials']))->toBe(21);

    $names = collect($payload['testimonials'])->pluck('name')->all();

    expect($names)->toContain('Priya Raman');
    expect($names)->not->toContain('Ben Fischer');
    expect($names)->not->toContain('Tom Alvarez');
    expect($names)->not->toContain('Sara Nkemi');
    expect($names)->not->toContain('Ana Ruiz');

    // No leak: sara@example.test, "email" key, "address" key.
    expect($body)->not->toContain('sara@example.test');
    expect($body)->not->toContain('"email"');
    expect($body)->not->toContain('"address"');

    // 21 distinct submitted_at values, ordering equals submitted_at DESC.
    $submittedAt = collect($payload['testimonials'])->pluck('submitted_at')->all();
    expect(count(array_unique($submittedAt)))->toBe(21);

    $sorted = collect($payload['testimonials'])->sortByDesc('submitted_at')->values()->all();
    expect($payload['testimonials'])->toEqual($sorted);
});

test('E5 embed shiplog: promoting a generated WoL row to favorite puts it first', function () {
    // would fail if: the embed ordering was is_favorite DESC but
    // ties were broken wrongly, or the favorite flag was not
    // honored, or the WoL guard prevented the toggle.
    $dev = User::where('email', 'dev@shiplog.io')->firstOrFail();
    $shiplog = Space::where('user_id', $dev->id)->where('slug', 'shiplog-reviews')->firstOrFail();

    // Take the OLDEST (by submitted_at) of the 20 generated WoL rows.
    $oldest = Testimonial::where('space_id', $shiplog->id)
        ->where('is_wall_of_love', 1)
        ->where('email', 'like', 'shiplog-c%')
        ->orderBy('submitted_at')
        ->first();
    expect($oldest)->not->toBeNull();
    $oldest->is_favorite = true;
    $oldest->save();

    $response = $this->get('/api/spaces/'.$shiplog->public_id.'/testimonials');
    $payload = $response->json();

    expect($payload['testimonials'][0]['name'])->toBe($oldest->name);
});

test('E5 embed beta: 12 items including Priya', function () {
    // would fail if: the seeder skipped a beta row, or the embed
    // filtered out the cross-Space Priya row.
    $dev = User::where('email', 'dev@shiplog.io')->firstOrFail();
    $beta = Space::where('user_id', $dev->id)->where('slug', 'beta-testers')->firstOrFail();

    $response = $this->get('/api/spaces/'.$beta->public_id.'/testimonials');
    $response->assertStatus(200);

    $payload = $response->json();
    expect(count($payload['testimonials']))->toBe(12);
    expect(collect($payload['testimonials'])->pluck('name')->all())->toContain('Priya Raman');
});

test('E5 embed: Cache-Control includes max-age=60 (no private, no no-store, no no-cache)', function () {
    // would fail if: the cache header regressed.
    $dev = User::where('email', 'dev@shiplog.io')->firstOrFail();
    $shiplog = Space::where('user_id', $dev->id)->where('slug', 'shiplog-reviews')->firstOrFail();

    $response = $this->get('/api/spaces/'.$shiplog->public_id.'/testimonials');
    $cacheControl = $response->headers->get('Cache-Control');

    expect($cacheControl)->toContain('max-age=60');
    expect($cacheControl)->not->toContain('private');
    expect($cacheControl)->not->toContain('no-store');
    expect($cacheControl)->not->toContain('no-cache');
});

// ---------------------------------------------------------------------
// E6 — dashboard isolation
// ---------------------------------------------------------------------

test('E6 dashboard: Dev sees sara@example.test; Maya does not', function () {
    // would fail if: the dashboard's open-deletion-requests list
    // leaked across owners.
    $dev = User::where('email', 'dev@shiplog.io')->firstOrFail();
    $maya = User::where('email', 'maya@brightcopy.co')->firstOrFail();

    expect(DeletionRequest::where('email', 'sara@example.test')->count())->toBe(1);

    $devHtml = (string) Livewire::actingAs($dev)->test(DashboardIndex::class)->html();
    expect($devHtml)->toContain('sara@example.test');

    $mayaHtml = (string) Livewire::actingAs($maya)->test(DashboardIndex::class)->html();
    expect($mayaHtml)->not->toContain('sara@example.test');
});

// ---------------------------------------------------------------------
// E7 — InboxRow::softDelete then InboxRow::forget close the deletion
//      request and hard-delete Sara's row.
// ---------------------------------------------------------------------

test('E7 forget-now: trash Sara then forget closes the deletion_request to status=acted and force-deletes her row', function () {
    // would fail if: forget() did not close the matching open
    // request, or the three-step order in InboxRow::forget was
    // broken, or the seeder never wrote the request.
    $dev = User::where('email', 'dev@shiplog.io')->firstOrFail();
    $shiplog = Space::where('user_id', $dev->id)->where('slug', 'shiplog-reviews')->firstOrFail();
    $sara = Testimonial::where('space_id', $shiplog->id)->where('email', 'sara@example.test')->firstOrFail();

    $req = DeletionRequest::where('email', 'sara@example.test')->firstOrFail();
    expect($req->status)->toBe(DeletionRequest::STATUS_OPEN);
    expect($req->acted_at)->toBeNull();

    // 1) Trash Sara through the real InboxRow action.
    Livewire::actingAs($dev)
        ->test(InboxRow::class, ['testimonialId' => $sara->id])
        ->call('softDelete');

    $sara->refresh();
    expect($sara->deleted_at)->not->toBeNull();

    // 2) Forget-now through the real InboxRow action.
    Livewire::actingAs($dev)
        ->test(InboxRow::class, ['testimonialId' => $sara->id])
        ->call('forget');

    $req->refresh();
    expect($req->status)->toBe(DeletionRequest::STATUS_ACTED);
    expect($req->acted_at)->not->toBeNull();

    expect(Testimonial::withTrashed()->where('id', $sara->id)->count())->toBe(0);
});

// ---------------------------------------------------------------------
// E8 — purge:run with Ana past retention, course-launch past
//      retention, Priya control
// ---------------------------------------------------------------------

test('E8 purge:run: deletes Ana (testimonial past 30d), course-launch (Space past 30d) and its embed config, but keeps Priya and the spaces row', function () {
    // would fail if: the purge order regressed, the embed config
    // was not removed, the spaces row was hard-deleted, or Ana /
    // Priya were over-deleted.
    $dev = User::where('email', 'dev@shiplog.io')->firstOrFail();
    $maya = User::where('email', 'maya@brightcopy.co')->firstOrFail();
    $shiplog = Space::where('user_id', $dev->id)->where('slug', 'shiplog-reviews')->firstOrFail();
    $cl = Space::where('user_id', $maya->id)->where('slug', 'course-launch')->firstOrFail();

    $ana = Testimonial::withTrashed()
        ->where('space_id', $shiplog->id)
        ->where('email', 'ana.ruiz.shl@example.test')
        ->firstOrFail();
    $ana->deleted_at = now()->subDays(31);
    $ana->save();

    // Give course-launch an embed_configurations row first, then
    // soft-delete it past retention.
    EmbedConfiguration::create([
        'space_id' => $cl->id,
        'layout' => 'masonry',
        'dark_mode' => false,
        'animation_enabled' => true,
        'background_color' => null,
        'item_limit' => 12,
        'show_rating' => true,
    ]);
    $cl->delete();
    $cl->refresh();
    $cl->deleted_at = now()->subDays(31);
    $cl->save();

    $priya = Testimonial::where('space_id', $shiplog->id)->where('email', 'priya@nimbus.dev')->firstOrFail();

    // Sanity: 3 LIVE testimonials in course-launch pre-purge.
    expect(Testimonial::where('space_id', $cl->id)->count())->toBe(3);

    $exitCode = Artisan::call('purge:run');
    expect($exitCode)->toBe(0);

    // Ana is hard-deleted.
    expect(Testimonial::withTrashed()->where('id', $ana->id)->count())->toBe(0);

    // course-launch's 3 testimonials are gone; embed_configuration gone.
    expect(Testimonial::withTrashed()->where('space_id', $cl->id)->count())->toBe(0);
    expect(EmbedConfiguration::where('space_id', $cl->id)->count())->toBe(0);

    // course-launch spaces row still exists (tombstoned).
    $cl->refresh();
    expect($cl->exists)->toBeTrue();
    expect($cl->deleted_at)->not->toBeNull();

    // Control: Priya is still there.
    $priya->refresh();
    expect($priya->exists)->toBeTrue();
});

// ---------------------------------------------------------------------
// E9 — restore + WoL guard
// ---------------------------------------------------------------------

test('E9 restore: Ana can be restored through the real InboxRow action while shiplog has 100 LIVE rows', function () {
    // would fail if: restore was guarded by the Space's
    // testimonial cap, or the InboxRow::restore action was broken.
    $dev = User::where('email', 'dev@shiplog.io')->firstOrFail();
    $shiplog = Space::where('user_id', $dev->id)->where('slug', 'shiplog-reviews')->firstOrFail();

    $ana = Testimonial::withTrashed()
        ->where('space_id', $shiplog->id)
        ->where('email', 'ana.ruiz.shl@example.test')
        ->firstOrFail();
    expect($ana->deleted_at)->not->toBeNull();
    expect(Testimonial::where('space_id', $shiplog->id)->count())->toBe(100);

    Livewire::actingAs($dev)
        ->test(InboxRow::class, ['testimonialId' => $ana->id])
        ->call('restore');

    $ana->refresh();
    expect($ana->deleted_at)->toBeNull();
    expect(Testimonial::where('space_id', $shiplog->id)->count())->toBe(101);
});

test('E9 WoL guard: setting wall_of_love on a no-consent row is refused', function () {
    // would fail if: the WoL guard was removed from InboxRow.
    $dev = User::where('email', 'dev@shiplog.io')->firstOrFail();
    $shiplog = Space::where('user_id', $dev->id)->where('slug', 'shiplog-reviews')->firstOrFail();
    $sara = Testimonial::where('space_id', $shiplog->id)->where('email', 'sara@example.test')->firstOrFail();
    expect((int) $sara->consent_given)->toBe(0);
    expect((int) $sara->is_wall_of_love)->toBe(0);

    Livewire::actingAs($dev)
        ->test(InboxRow::class, ['testimonialId' => $sara->id])
        ->call('toggleWallOfLove');

    $sara->refresh();
    expect((int) $sara->is_wall_of_love)->toBe(0);
    expect((int) $sara->is_public)->toBe(0);
});

// ---------------------------------------------------------------------
// E10 — cross-user 404 + public-route open
// ---------------------------------------------------------------------

test('E10 authz: Maya gets 404 on Dev\'s spaces.edit and spaces.embed; Dev gets 200 on both', function () {
    // would fail if: the owner gate on SpaceForm / EmbedBuilder
    // was bypassed, or the route name changed.
    $maya = User::where('email', 'maya@brightcopy.co')->firstOrFail();
    $dev = User::where('email', 'dev@shiplog.io')->firstOrFail();
    $shiplog = Space::where('user_id', $dev->id)->where('slug', 'shiplog-reviews')->firstOrFail();

    $this->actingAs($maya)
        ->get(route('spaces.edit', ['space' => $shiplog->id]))
        ->assertStatus(404);
    $this->actingAs($maya)
        ->get(route('spaces.embed', ['space' => $shiplog->id]))
        ->assertStatus(404);

    $this->actingAs($dev)
        ->get(route('spaces.edit', ['space' => $shiplog->id]))
        ->assertStatus(200);
    $this->actingAs($dev)
        ->get(route('spaces.embed', ['space' => $shiplog->id]))
        ->assertStatus(200);
});

test('E10 authz: Maya\'s inbox does not contain Dev\'s customers', function () {
    // would fail if: the inbox query joined the wrong owner, or
    // the inbox leaked Dev's data through Maya.
    $maya = User::where('email', 'maya@brightcopy.co')->firstOrFail();
    $mayaInbox = (string) Livewire::actingAs($maya)->test(InboxIndex::class)->html();
    expect($mayaInbox)->not->toContain('priya@nimbus.dev');
    expect($mayaInbox)->not->toContain('sara@example.test');
    expect($mayaInbox)->not->toContain('ana.ruiz.shl@example.test');
});

test('E10 authz: the public submission form is open for all four seeded Spaces (no auth required)', function () {
    // would fail if: /s/{slug} was placed behind auth.
    $this->get('/s/shiplog-reviews')->assertStatus(200);
    $this->get('/s/brightcopy-wins')->assertStatus(200);
    $this->get('/s/course-launch')->assertStatus(200);
    $this->get('/s/beta-testers')->assertStatus(200);
});

test('E10 authz: the public embed API is open for Dev\'s Spaces (no auth required)', function () {
    // would fail if: /api/spaces/{public_id}/testimonials was
    // placed behind auth.
    $dev = User::where('email', 'dev@shiplog.io')->firstOrFail();
    $shiplog = Space::where('user_id', $dev->id)->where('slug', 'shiplog-reviews')->firstOrFail();
    $beta = Space::where('user_id', $dev->id)->where('slug', 'beta-testers')->firstOrFail();

    $this->get('/api/spaces/'.$shiplog->public_id.'/testimonials')->assertStatus(200);
    $this->get('/api/spaces/'.$beta->public_id.'/testimonials')->assertStatus(200);
});

// ---------------------------------------------------------------------
// E11 — base users can log in and hit the dashboard
// ---------------------------------------------------------------------

test('E11 base users: email_verified_at not null, password=password, and Maya GETs /dashboard with 200', function () {
    // would fail if: BaseUsersSeeder dropped email_verified_at, or
    // the password hashing changed, or the dashboard route was
    // broken for verified users.
    $emails = [
        'maya@brightcopy.co',
        'dev@shiplog.io',
        'priya@nimbus.dev',
        'ben@example.test',
    ];
    foreach ($emails as $email) {
        $u = User::where('email', $email)->firstOrFail();
        expect($u->email_verified_at)->not->toBeNull("email_verified_at for {$email}");
        expect(Hash::check('password', (string) $u->password))->toBeTrue("password for {$email}");
    }

    $maya = User::where('email', 'maya@brightcopy.co')->firstOrFail();
    $this->actingAs($maya)->get(route('dashboard'))->assertStatus(200);
});

// ---------------------------------------------------------------------
// E12 — submitted_at distinctness per Space
// ---------------------------------------------------------------------

test('E12 submitted_at: per Space, distinct count = withTrashed count, in (now-90d, now], at most 2 per UTC day', function () {
    // would fail if: the seeder's distinctOffsets() regressed (any
    // ties, any out-of-window timestamps, any day with 3+ rows).
    $spaces = Space::withTrashed()->orderBy('id')->get();

    foreach ($spaces as $space) {
        $rows = Testimonial::withTrashed()->where('space_id', $space->id)->get();
        if ($rows->isEmpty()) {
            continue;
        }
        $withTrashedCount = $rows->count();
        $distinct = $rows->pluck('submitted_at')->map(fn ($v) => (string) $v)->unique()->count();
        expect($distinct)->toBe($withTrashedCount, "{$space->slug}: distinct submitted_at count = withTrashed count");

        $min = $rows->min('submitted_at');
        $max = $rows->max('submitted_at');
        expect($min->gt(now()->subDays(90)))->toBeTrue("{$space->slug}: min submitted_at > now-90d");
        expect($max->lte(now()))->toBeTrue("{$space->slug}: max submitted_at <= now");

        // Per-UTC-day count, at most 2.
        $byDay = $rows->groupBy(fn ($r) => $r->submitted_at->copy()->utc()->toDateString());
        foreach ($byDay as $day => $dayRows) {
            expect($dayRows->count())->toBeLessThanOrEqual(2, "{$space->slug} on {$day}: >2 rows");
        }
    }
});
