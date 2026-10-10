<?php

use App\Livewire\Dashboard\DashboardIndex;
use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/**
 * Step 5 — Time-series graph (Chart.js via CDN).
 *
 * All tests freeze time at 2026-10-12 12:00:00 UTC so the exact
 * bucket keys and counts are deterministic and the assertions are
 * exact (no "~", no ">=", no "varies").
 *
 * Range-start rule: range = Nd uses today (startOfDay UTC) as the
 * END, and today - (N-1) days as the START. So `7d` covers the 7
 * days ending today inclusive; `30d` covers 30 days inclusive.
 *
 * `all` starts at the owner's earliest live testimonial's bucket
 * and ends at today inclusive.
 *
 * Time is reset in afterEach.
 */

beforeEach(function () {
    Carbon::setTestNow(CarbonImmutable::parse('2026-10-12 12:00:00', 'UTC'));
});

afterEach(function () {
    Carbon::setTestNow();
    DB::statement("SET time_zone = '+00:00'");
});

test('day granularity + 7d range: 7 exact keys 2026-10-06..2026-10-12 with exact counts', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();

    // 3 submissions on 3 different days within 7d. Frozen time = 2026-10-12.
    Testimonial::factory()->for($space)->create(['submitted_at' => CarbonImmutable::parse('2026-10-06 09:00', 'UTC')]);
    Testimonial::factory()->for($space)->create(['submitted_at' => CarbonImmutable::parse('2026-10-09 12:00', 'UTC')]);
    Testimonial::factory()->for($space)->create(['submitted_at' => CarbonImmutable::parse('2026-10-12 08:00', 'UTC')]);

    $c = Livewire::actingAs($user)
        ->test(DashboardIndex::class, ['range' => '7d', 'granularity' => 'day'])
        ->instance();

    $keys = $c->graphData['keys'];
    $counts = $c->graphData['counts'];

    expect($keys)->toBe([
        '2026-10-06','2026-10-07','2026-10-08','2026-10-09','2026-10-10','2026-10-11','2026-10-12',
    ])->and(array_combine($keys, $counts))->toBe([
        '2026-10-06' => 1,
        '2026-10-07' => 0,
        '2026-10-08' => 0,
        '2026-10-09' => 1,
        '2026-10-10' => 0,
        '2026-10-11' => 0,
        '2026-10-12' => 1,
    ]);
});

test('day granularity + 30d range: 30 exact keys 2026-09-13..2026-10-12', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();

    Testimonial::factory()->for($space)->count(2)->create(['submitted_at' => CarbonImmutable::parse('2026-09-20 09:00', 'UTC')]);

    $c = Livewire::actingAs($user)
        ->test(DashboardIndex::class, ['range' => '30d', 'granularity' => 'day'])
        ->instance();

    $keys = $c->graphData['keys'];
    $counts = $c->graphData['counts'];

    expect($keys)->toHaveCount(30)
        ->and($keys[0])->toBe('2026-09-13')
        ->and($keys[29])->toBe('2026-10-12')
        ->and($keys[7])->toBe('2026-09-20')
        ->and($counts[7])->toBe(2);

    // Every other key has count 0.
    foreach ($counts as $i => $v) {
        if ($i === 7) continue;
        expect($v)->toBe(0, "index $i (key {$keys[$i]}) expected 0, got $v");
    }
});

test('day granularity + 90d range: 90 exact keys', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();

    $c = Livewire::actingAs($user)
        ->test(DashboardIndex::class, ['range' => '90d', 'granularity' => 'day'])
        ->instance();

    $keys = $c->graphData['keys'];
    $counts = $c->graphData['counts'];

    expect($keys)->toHaveCount(90)
        ->and($keys[0])->toBe('2026-07-15')
        ->and($keys[89])->toBe('2026-10-12')
        ->and(array_sum($counts))->toBe(0);
});

test('week granularity + 30d range: exact ISO week keys from week of 2026-09-13 to week of 2026-10-12', function () {
    // 2026-09-13 is a Sunday. ISO week of 2026-09-13 is the week
    // starting Mon 2026-09-07 — which Carbon's startOfWeek(MONDAY)
    // reports as ISO week "2026-37".
    // 2026-10-12 is a Monday — ISO week "2026-42".
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();

    Testimonial::factory()->for($space)->create(['submitted_at' => CarbonImmutable::parse('2026-10-02 12:00', 'UTC')]);
    Testimonial::factory()->for($space)->create(['submitted_at' => CarbonImmutable::parse('2026-10-09 12:00', 'UTC')]);

    $c = Livewire::actingAs($user)
        ->test(DashboardIndex::class, ['range' => '30d', 'granularity' => 'week'])
        ->instance();

    $keys = $c->graphData['keys'];
    $counts = $c->graphData['counts'];

    // Expected exact ISO week keys (o-W format) covering weeks 37..42.
    expect($keys)->toBe(['2026-37','2026-38','2026-39','2026-40','2026-41','2026-42'])
        ->and(array_combine($keys, $counts))->toBe([
            '2026-37' => 0,
            '2026-38' => 0,
            '2026-39' => 0,
            '2026-40' => 1,
            '2026-41' => 1,
            '2026-42' => 0,
        ]);
});

test('month granularity + 30d range: exact keys 2026-09 and 2026-10', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();

    Testimonial::factory()->for($space)->create(['submitted_at' => CarbonImmutable::parse('2026-09-15 12:00', 'UTC')]);
    Testimonial::factory()->for($space)->create(['submitted_at' => CarbonImmutable::parse('2026-10-05 12:00', 'UTC')]);

    $c = Livewire::actingAs($user)
        ->test(DashboardIndex::class, ['range' => '30d', 'granularity' => 'month'])
        ->instance();

    expect($c->graphData['keys'])->toBe(['2026-09','2026-10'])
        ->and($c->graphData['counts'])->toBe([1, 1]);
});

test('all range: starts at owner earliest live testimonial bucket', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();

    Testimonial::factory()->for($space)->create(['submitted_at' => CarbonImmutable::parse('2026-08-05 12:00', 'UTC')]);
    Testimonial::factory()->for($space)->create(['submitted_at' => CarbonImmutable::parse('2026-09-18 12:00', 'UTC')]);
    Testimonial::factory()->for($space)->create(['submitted_at' => CarbonImmutable::parse('2026-10-10 12:00', 'UTC')]);

    $c = Livewire::actingAs($user)
        ->test(DashboardIndex::class, ['range' => 'all', 'granularity' => 'day'])
        ->instance();

    $keys = $c->graphData['keys'];
    $counts = $c->graphData['counts'];

    expect($keys[0])->toBe('2026-08-05')
        ->and($keys[count($keys) - 1])->toBe('2026-10-12')
        ->and(array_sum($counts))->toBe(3)
        // Exact count for the 3 known days.
        ->and(array_combine($keys, $counts)['2026-08-05'])->toBe(1)
        ->and(array_combine($keys, $counts)['2026-09-18'])->toBe(1)
        ->and(array_combine($keys, $counts)['2026-10-10'])->toBe(1);
});

test('UTC bucketing is done at the database level (raw SQL contains DATE(submitted_at))', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    Testimonial::factory()->for($space)->create();

    $c = Livewire::actingAs($user)
        ->test(DashboardIndex::class, ['range' => '7d', 'granularity' => 'day'])
        ->instance();

    // MySQL's DATE() function — what the design pins for the
    // UTC bucketing. SQLite fallback would use substr(...).
    $sql = strtolower($c->graphSql);
    $hasDateExpr = str_contains($sql, 'date(') || str_contains($sql, 'substr(');

    expect($hasDateExpr)->toBeTrue(
        "Graph SQL must bucket at the DB level (date() or substr()). Got: {$sql}"
    );
});

test('UTC midnight boundary: 23:59:59 UTC day-3 and 00:00:01 UTC day-2 land in different buckets', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();

    // Frozen time = 2026-10-12 12:00:00 UTC. 7d range = 2026-10-06..2026-10-12.
    // X: 2026-10-09 23:59:59 UTC (one second before midnight UTC on day-3)
    // Y: 2026-10-10 00:00:01 UTC (one second after midnight UTC on day-2)
    Testimonial::factory()->for($space)->create(['submitted_at' => CarbonImmutable::parse('2026-10-09 23:59:59', 'UTC')]);
    Testimonial::factory()->for($space)->create(['submitted_at' => CarbonImmutable::parse('2026-10-10 00:00:01', 'UTC')]);

    $c = Livewire::actingAs($user)
        ->test(DashboardIndex::class, ['range' => '7d', 'granularity' => 'day'])
        ->instance();

    $keys = $c->graphData['keys'];
    $counts = $c->graphData['counts'];
    $byKey = array_combine($keys, $counts);

    expect($keys)->toHaveCount(7)
        ->and($keys[count($keys) - 1])->toBe('2026-10-12')
        ->and($byKey['2026-10-09'])->toBe(1)
        ->and($byKey['2026-10-10'])->toBe(1);

    // Every other day in the 7d range is 0.
    foreach ($keys as $i => $k) {
        if ($k === '2026-10-09' || $k === '2026-10-10') continue;
        expect($byKey[$k])->toBe(0, "key $k expected 0, got {$byKey[$k]}");
    }
});

test('UTC midnight boundary: identical results when session timezone is +06:00 (proves bucketing is session-tz independent)', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();

    Testimonial::factory()->for($space)->create(['submitted_at' => CarbonImmutable::parse('2026-10-09 23:59:59', 'UTC')]);
    Testimonial::factory()->for($space)->create(['submitted_at' => CarbonImmutable::parse('2026-10-10 00:00:01', 'UTC')]);

    // Force the MySQL session timezone to a non-UTC value BEFORE the
    // graph query runs. The bucketing is at the DB level; if it
    // depended on the session timezone, the bucket keys would shift
    // by 1 day and the counts would be wrong.
    DB::statement("SET time_zone = '+06:00'");

    $c = Livewire::actingAs($user)
        ->test(DashboardIndex::class, ['range' => '7d', 'granularity' => 'day'])
        ->instance();

    $keys = $c->graphData['keys'];
    $counts = $c->graphData['counts'];
    $byKey = array_combine($keys, $counts);

    // Identical assertions to the previous test — bucketing must
    // NOT depend on the session timezone because the value
    // submitted_at was written by the app as a UTC string AND the
    // app reads/writes it in UTC.
    expect($keys)->toHaveCount(7)
        ->and($keys[count($keys) - 1])->toBe('2026-10-12')
        ->and($byKey['2026-10-09'])->toBe(1)
        ->and($byKey['2026-10-10'])->toBe(1);
});

test('Space filter only shows that Space data and is silently dropped for foreign/soft-deleted Spaces', function () {
    $user = User::factory()->create();
    $own = Space::factory()->for($user)->create();
    $other = Space::factory()->for(User::factory()->create())->create();

    Testimonial::factory()->for($own)->count(4)->create(['submitted_at' => CarbonImmutable::parse('2026-10-10 12:00', 'UTC')]);
    Testimonial::factory()->for($other)->count(9)->create(['submitted_at' => CarbonImmutable::parse('2026-10-10 12:00', 'UTC')]);

    $c = Livewire::actingAs($user)
        ->test(DashboardIndex::class, [
            'range' => '30d',
            'granularity' => 'day',
            'spaceId' => $own->id,
        ])
        ->instance();

    expect(array_sum($c->graphData['counts']))->toBe(4);

    // Foreign Space: silently dropped, no data leak.
    $cForeign = Livewire::actingAs($user)
        ->test(DashboardIndex::class, [
            'range' => '30d',
            'granularity' => 'day',
            'spaceId' => $other->id,
        ])
        ->instance();

    expect($cForeign->spaceId)->toBeNull();

    // Soft-deleted Space: also dropped.
    $deleted = Space::factory()->for($user)->create();
    $deleted->delete();
    $cDead = Livewire::actingAs($user)
        ->test(DashboardIndex::class, [
            'range' => '30d',
            'granularity' => 'day',
            'spaceId' => $deleted->id,
        ])
        ->instance();
    expect($cDead->spaceId)->toBeNull();
});

test('invalid granularity and range values fall back to defaults', function () {
    $user = User::factory()->create();

    $c = Livewire::actingAs($user)
        ->test(DashboardIndex::class, [
            'range' => 'bogus',
            'granularity' => 'also-bogus',
        ])
        ->instance();

    expect($c->granularity)->toBe('day')
        ->and($c->range)->toBe('30d')
        ->and($c->graphData['keys'])->toBe([
            '2026-09-13','2026-09-14','2026-09-15','2026-09-16','2026-09-17','2026-09-18','2026-09-19','2026-09-20','2026-09-21','2026-09-22','2026-09-23','2026-09-24','2026-09-25','2026-09-26','2026-09-27','2026-09-28','2026-09-29','2026-09-30','2026-10-01','2026-10-02','2026-10-03','2026-10-04','2026-10-05','2026-10-06','2026-10-07','2026-10-08','2026-10-09','2026-10-10','2026-10-11','2026-10-12',
        ]);
});

test('graph HTML canvas has the labels/counts/keys as data attributes for Chart.js', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    Testimonial::factory()->for($space)->create();

    $html = (string) Livewire::actingAs($user)
        ->test(DashboardIndex::class, ['range' => '7d', 'granularity' => 'day'])
        ->html();

    expect($html)
        ->toContain('data-testid="graph-canvas"')
        ->toContain('id="dashboard-graph"')
        // The data-labels/data-counts attributes are JSON arrays.
        // Blade renders `data-labels='[...]'` with single quotes
        // because the JSON contains double quotes.
        ->toContain("data-labels='[")
        ->toContain("data-counts='[");
});

test('Part 5a: chart.js@4.4.1 appears exactly once in the rendered HTML', function () {
    $user = User::factory()->create();
    Space::factory()->for($user)->create();

    $html = (string) Livewire::actingAs($user)
        ->test(DashboardIndex::class)
        ->html();

    expect(substr_count($html, 'chart.js@4.4.1'))->toBe(1);
});

test('Part 3 (follow-up 3): rendered HTML contains x-on:dashboard-graph-updated.window', function () {
    // Adjusted (follow-up 4): the wrapper now uses x-on:dashboard-graph-updated.window
    // AND loads Chart.js from data-chartjs-src instead of a separate <script src> tag,
    // because the plain script placed after the wrapper on first wire:navigate visit
    // loaded after Alpine x-init had already run (causing a blank graph).
    $user = User::factory()->create();
    Space::factory()->for($user)->create();

    $html = (string) Livewire::actingAs($user)
        ->test(DashboardIndex::class)
        ->html();

    expect($html)->toContain('x-on:dashboard-graph-updated.window');
});

test('Part 3 (follow-up 3): rendered HTML does not contain alpine:init or _x_dataStack', function () {
    // Adjusted (follow-up 4): now also asserts no @assets block and no <script ... src=...chart.js>
    // tag, because Chart.js is loaded by the wrapper's x-init from data-chartjs-src.
    $user = User::factory()->create();
    Space::factory()->for($user)->create();

    $html = (string) Livewire::actingAs($user)
        ->test(DashboardIndex::class)
        ->html();

    expect($html)
        ->not->toContain('alpine:init')
        ->not->toContain('_x_dataStack')
        ->not->toContain('@assets')
        // The CDN script is no longer a literal <script src=...chart.js...> tag.
        // We match the opening of a script tag specifically so Livewire's
        // wire:snapshot (which contains the data-chartjs-src value for
        // round-tripping) is not flagged.
        ->not->toContain('<script src="https://cdn.jsdelivr.net/npm/chart.js');
});

test('Part 4 (follow-up 4): wrapper carries data-chartjs-src with the pinned chart.js@4.4.1 UMD URL', function () {
    $user = User::factory()->create();
    Space::factory()->for($user)->create();

    $html = (string) Livewire::actingAs($user)
        ->test(DashboardIndex::class)
        ->html();

    // The CDN URL lives on the wire:ignore wrapper as a data attribute.
    // It is the only occurrence of "chart.js@4.4.1" in the HTML — also
    // asserted by the Part 5a test.
    expect($html)
        ->toContain('data-chartjs-src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"')
        // No literal <script src=...chart.js...> tag.
        ->not->toContain('<script src="https://cdn.jsdelivr.net/npm/chart.js');
});

test('Part 5b: changing range dispatches dashboard-graph-updated with correct counts length (frozen time)', function () {
    $user = User::factory()->create();
    Space::factory()->for($user)->create();

    $c = Livewire::actingAs($user)
        ->test(DashboardIndex::class)
        ->set('range', '90d')
        ->assertDispatched('dashboard-graph-updated');

    // Verify the dispatched payload's counts length matches the new range.
    $dispatches = collect($c->effects['dispatches'] ?? []);
    $last = $dispatches->firstWhere('name', 'dashboard-graph-updated');
    expect($last)->not->toBeNull();
    expect(count($last['params']['counts']))->toBe(90);
});

test('Part 5c: changing granularity dispatches dashboard-graph-updated', function () {
    $user = User::factory()->create();
    Space::factory()->for($user)->create();

    Livewire::actingAs($user)
        ->test(DashboardIndex::class)
        ->set('granularity', 'month')
        ->assertDispatched('dashboard-graph-updated');
});
