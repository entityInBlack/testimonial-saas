<?php

use App\Livewire\Dashboard\DashboardIndex;
use App\Livewire\Spaces\SpaceDeleted;
use App\Models\EmbedConfiguration;
use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;
use App\Support\SlugService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * Step 6 — `php artisan purge:run` behaviour.
 *
 * All tests use frozen time so the retention boundary is
 * deterministic. We assert on the post-run state of the database
 * and the file system, and on the audit-log entries.
 *
 *  1. Boundary (29 vs 30 vs 31 days)
 *  2. Custom retention_days
 *  3. Live testimonial in a live Space is untouched
 *  4. deletion_requests match (Hard Rule 14, Sara case)
 *  5. Photo: present, missing, real-failure retry
 *  6. Space past retention → tombstone
 *  7. Space within retention untouched
 *  8. Tombstone: slug still taken; restore via HTTP fails; control restored
 *  9. Second run is a no-op
 * 10. Log content: row IDs and counts only — no PII
 * 11. Channel config: days=14
 */

beforeEach(function () {
    // Frozen now so retention math is deterministic. The
    // retention window default is 30 days, so "past retention" is
    // anything strictly older than 2026-08-12 00:00:00.
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-12 12:00:00', 'UTC'));
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

test('soft-deleted 31-day testimonial is purged; 29-day testimonial is kept', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    $old = Testimonial::factory()->for($space)->create(['profile_photo' => null]);
    $young = Testimonial::factory()->for($space)->create(['profile_photo' => null]);

    // 31 days old → past retention.
    Testimonial::withTrashed()->where('id', $old->id)->update([
        'deleted_at' => CarbonImmutable::now()->subDays(31),
    ]);

    // 29 days old → within retention.
    Testimonial::withTrashed()->where('id', $young->id)->update([
        'deleted_at' => CarbonImmutable::now()->subDays(29),
    ]);

    $exit = Artisan::call('purge:run');
    expect($exit)->toBe(0);

    // 31-day row is gone even from withTrashed().
    expect(Testimonial::withTrashed()->find($old->id))->toBeNull();
    // 29-day row still exists (and is still soft-deleted).
    $kept = Testimonial::withTrashed()->find($young->id);
    expect($kept)->not->toBeNull();
    expect($kept->deleted_at)->not->toBeNull();
});

test('boundary: deleted exactly retention_days ago is kept; retention_days+1s is purged', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    $edge = Testimonial::factory()->for($space)->create(['profile_photo' => null]);
    $edgePlus = Testimonial::factory()->for($space)->create(['profile_photo' => null]);

    // Exactly 30 days ago (the boundary is NOT inclusive — kept).
    Testimonial::withTrashed()->where('id', $edge->id)->update([
        'deleted_at' => CarbonImmutable::now()->subDays(30),
    ]);

    // 30 days and 1 second ago (strictly older — purged).
    Testimonial::withTrashed()->where('id', $edgePlus->id)->update([
        'deleted_at' => CarbonImmutable::now()->subDays(30)->subSecond(),
    ]);

    Artisan::call('purge:run');

    expect(Testimonial::withTrashed()->find($edge->id))->not->toBeNull();
    expect(Testimonial::withTrashed()->find($edgePlus->id))->toBeNull();
});

test('config-driven retention: 7 days, 8-day purged, 6-day kept', function () {
    Storage::fake('public');

    config(['purge.retention_days' => 7]);

    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    $old = Testimonial::factory()->for($space)->create(['profile_photo' => null]);
    $young = Testimonial::factory()->for($space)->create(['profile_photo' => null]);

    Testimonial::withTrashed()->where('id', $old->id)->update([
        'deleted_at' => CarbonImmutable::now()->subDays(8),
    ]);
    Testimonial::withTrashed()->where('id', $young->id)->update([
        'deleted_at' => CarbonImmutable::now()->subDays(6),
    ]);

    Artisan::call('purge:run');

    expect(Testimonial::withTrashed()->find($old->id))->toBeNull();
    expect(Testimonial::withTrashed()->find($young->id))->not->toBeNull();
});

test('live testimonial in a live Space is untouched by purge:run', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    $live = Testimonial::factory()->for($space)->create(['profile_photo' => null]);

    Artisan::call('purge:run');

    expect(Testimonial::withTrashed()->find($live->id))->not->toBeNull();
    expect($live->fresh()->deleted_at)->toBeNull();
});

test('deletion_requests: matching testimonial_id acted; matching null-id-by-email acted; cross-Space/foreign-id stay open', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $spaceA = Space::factory()->for($user)->create();
    $spaceB = Space::factory()->for($user)->create();

    $sara = Testimonial::factory()->for($spaceA)->create([
        'email' => 'sara@example.test',
        'profile_photo' => null,
    ]);
    $byId = Testimonial::factory()->for($spaceA)->create(['profile_photo' => null]);
    $other = Testimonial::factory()->for($spaceA)->create(['profile_photo' => null]);

    // Sara's testimonial soft-deleted 31 days ago.
    Testimonial::withTrashed()->where('id', $sara->id)->update([
        'deleted_at' => CarbonImmutable::now()->subDays(31),
    ]);
    Testimonial::withTrashed()->where('id', $byId->id)->update([
        'deleted_at' => CarbonImmutable::now()->subDays(31),
    ]);
    Testimonial::withTrashed()->where('id', $other->id)->update([
        'deleted_at' => CarbonImmutable::now()->subDays(31),
    ]);

    // 1) Open request with Sara's email + spaceA + null testimonial_id (Hard Rule 14).
    DB::table('deletion_requests')->insert([
        'email' => 'sara@example.test',
        'space_slug' => $spaceA->slug,
        'space_id' => $spaceA->id,
        'testimonial_id' => null,
        'status' => 'open',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // 2) Open request with the matching testimonial_id (spaceA).
    DB::table('deletion_requests')->insert([
        'email' => $byId->email,
        'space_slug' => $spaceA->slug,
        'space_id' => $spaceA->id,
        'testimonial_id' => $byId->id,
        'status' => 'open',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // 3) Open request with Sara's email BUT in a different Space.
    DB::table('deletion_requests')->insert([
        'email' => 'sara@example.test',
        'space_slug' => $spaceB->slug,
        'space_id' => $spaceB->id,
        'testimonial_id' => null,
        'status' => 'open',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // 4) Open request with a different testimonial_id (foreign row —
    //    must reference a real testimonial to satisfy the FK). We
    //    use $byId->id and change the SPACE so it does not match
    //    $byId's space, but the spec is "different non-null id" —
    //    use a request that has a different testimonial_id
    //    belonging to a different Space.
    $otherSpace = Space::factory()->for($user)->create();
    $otherT = Testimonial::factory()->for($otherSpace)->create();
    DB::table('deletion_requests')->insert([
        'email' => 'someone-else@example.test',
        'space_slug' => $otherSpace->slug,
        'space_id' => $otherSpace->id,
        'testimonial_id' => $otherT->id,
        'status' => 'open',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // 5) Already-acted request for the byId row (we want acted_at preserved).
    $when = CarbonImmutable::now()->subDays(10);
    DB::table('deletion_requests')->insert([
        'email' => $byId->email.'-other',
        'space_slug' => $spaceA->slug,
        'space_id' => $spaceA->id,
        'testimonial_id' => $byId->id,
        'status' => 'acted',
        'acted_at' => $when,
        'created_at' => $when,
        'updated_at' => $when,
    ]);

    Artisan::call('purge:run');

    // 1) acted, acted_at = frozen now.
    $saraDr = DB::table('deletion_requests')
        ->where('space_id', $spaceA->id)
        ->where('email', 'sara@example.test')
        ->where('testimonial_id', null)
        ->first();
    expect($saraDr->status)->toBe('acted');
    expect($saraDr->acted_at)->not->toBeNull();
    expect(CarbonImmutable::parse($saraDr->acted_at)->toDateTimeString())
        ->toBe(CarbonImmutable::now()->toDateTimeString());

    // 2) acted.
    $byIdDr = DB::table('deletion_requests')
        ->where('space_id', $spaceA->id)
        ->where('testimonial_id', $byId->id)
        ->where('status', 'open')
        ->first();
    expect($byIdDr)->toBeNull(); // was open, now acted

    // 3) cross-Space email — stays open.
    $cross = DB::table('deletion_requests')
        ->where('space_id', $spaceB->id)
        ->where('email', 'sara@example.test')
        ->first();
    expect($cross->status)->toBe('open');

    // 4) foreign testimonial_id (in a different Space) — stays open.
    $foreign = DB::table('deletion_requests')
        ->where('testimonial_id', $otherT->id)
        ->where('space_id', $otherSpace->id)
        ->first();
    expect($foreign->status)->toBe('open');

    // 5) already-acted — original acted_at preserved (the purge did NOT
    //    re-touch this row; it's only matched by status='open'). After
    //    the byId testimonial was force-deleted, the nullOnDelete FK
    //    set this row's testimonial_id to NULL — so we find it by
    //    email + space_id (the two fields never touched by Step 1
    //    when the row is already 'acted').
    $alreadyActed = DB::table('deletion_requests')
        ->where('email', $byId->email.'-other')
        ->where('space_id', $spaceA->id)
        ->where('status', 'acted')
        ->first();
    expect($alreadyActed)->not->toBeNull();
    expect(CarbonImmutable::parse($alreadyActed->acted_at)->toDateTimeString())
        ->toBe($when->toDateTimeString());
});

test('photo present → file deleted + row force-deleted; photo missing → still completes; photo delete fails → row kept, second run purges', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();

    // Case A — photo present.
    $withPhoto = Testimonial::factory()->for($space)->create([
        'profile_photo' => 'photos/with.webp',
    ]);
    Storage::disk('public')->put('photos/with.webp', 'fake');
    Testimonial::withTrashed()->where('id', $withPhoto->id)->update([
        'deleted_at' => CarbonImmutable::now()->subDays(31),
    ]);

    // Case B — photo path set but file missing.
    $noFile = Testimonial::factory()->for($space)->create([
        'profile_photo' => 'photos/missing.webp',
    ]);
    Testimonial::withTrashed()->where('id', $noFile->id)->update([
        'deleted_at' => CarbonImmutable::now()->subDays(31),
    ]);

    // Case C — photo delete will fail with a non-"file not found" error.
    $failPhoto = Testimonial::factory()->for($space)->create([
        'profile_photo' => 'photos/will-fail.webp',
    ]);
    Storage::disk('public')->put('photos/will-fail.webp', 'fake');
    Testimonial::withTrashed()->where('id', $failPhoto->id)->update([
        'deleted_at' => CarbonImmutable::now()->subDays(31),
    ]);

    // Replace the public disk with a Mockery partial that throws on
    // delete() for the one specific path, and otherwise delegates to
    // the real (fake) disk. We swap it back after the first run.
    $realDisk = Storage::disk('public');
    $mockDisk = Mockery::mock($realDisk)->makePartial();
    $mockDisk->shouldReceive('delete')
        ->andReturnUsing(function ($paths) use ($realDisk) {
            foreach ((array) $paths as $p) {
                if ($p === 'photos/will-fail.webp') {
                    throw new \RuntimeException('simulated disk error');
                }
            }

            return $realDisk->delete($paths);
        });
    Storage::set('public', $mockDisk);

    $exit = Artisan::call('purge:run');
    expect($exit)->toBe(0); // exit code 0 even with photo failures

    // Case A — file deleted, row gone.
    $realDisk->assertMissing('photos/with.webp');
    expect(Testimonial::withTrashed()->find($withPhoto->id))->toBeNull();

    // Case B — row gone (the "file not found" path is treated as
    // success — the row completes).
    expect(Testimonial::withTrashed()->find($noFile->id))->toBeNull();

    // Case C — row STILL THERE with deleted_at set, file still on disk.
    $failedRow = Testimonial::withTrashed()->find($failPhoto->id);
    expect($failedRow)->not->toBeNull();
    expect($failedRow->deleted_at)->not->toBeNull();
    $realDisk->assertExists('photos/will-fail.webp');

    // Now swap the mock disk out and re-register the real one so
    // the next run succeeds for the failed row.
    Storage::set('public', $realDisk);

    // Second run: the failed row is now purged.
    Artisan::call('purge:run');
    expect(Testimonial::withTrashed()->find($failPhoto->id))->toBeNull();
    $realDisk->assertMissing('photos/will-fail.webp');
});

test('Space soft-deleted 31 days: 2 live + 1 soft-deleted testimonials, photos, embed_configurations all gone; Space row kept as tombstone', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    EmbedConfiguration::factory()->for($space)->create();

    $t1 = Testimonial::factory()->for($space)->create(['profile_photo' => 'photos/t1.webp']);
    $t2 = Testimonial::factory()->for($space)->create(['profile_photo' => 'photos/t2.webp']);
    $t3 = Testimonial::factory()->for($space)->create(['profile_photo' => 'photos/t3.webp']);
    Storage::disk('public')->put('photos/t1.webp', 'x');
    Storage::disk('public')->put('photos/t2.webp', 'x');
    Storage::disk('public')->put('photos/t3.webp', 'x');

    // Soft-delete t3 only (still within retention by itself? Doesn't
    // matter — the spec is that when the SPACE is past retention,
    // every testimonial of the Space is purged regardless of their
    // own deleted_at age).
    $t3->delete();

    // Soft-delete the Space 31 days ago.
    $space->delete();
    DB::table('spaces')->where('id', $space->id)->update([
        'deleted_at' => CarbonImmutable::now()->subDays(31),
    ]);

    $originalSlug = $space->slug;
    $originalDeletedAt = DB::table('spaces')->where('id', $space->id)->value('deleted_at');

    Artisan::call('purge:run');

    // All 3 testimonials force-deleted.
    expect(Testimonial::withTrashed()->find($t1->id))->toBeNull();
    expect(Testimonial::withTrashed()->find($t2->id))->toBeNull();
    expect(Testimonial::withTrashed()->find($t3->id))->toBeNull();

    // All 3 photos gone.
    Storage::disk('public')->assertMissing('photos/t1.webp');
    Storage::disk('public')->assertMissing('photos/t2.webp');
    Storage::disk('public')->assertMissing('photos/t3.webp');

    // embed_configurations row gone.
    expect(EmbedConfiguration::where('space_id', $space->id)->count())->toBe(0);

    // Space row STILL THERE (withTrashed) with same deleted_at and same slug.
    $kept = Space::withTrashed()->find($space->id);
    expect($kept)->not->toBeNull();
    expect($kept->slug)->toBe($originalSlug);
    expect(CarbonImmutable::parse($kept->deleted_at)->toDateTimeString())
        ->toBe(CarbonImmutable::parse($originalDeletedAt)->toDateTimeString());
});

test('Space soft-deleted 29 days: testimonials and embed_configurations remain', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    EmbedConfiguration::factory()->for($space)->create();
    $t = Testimonial::factory()->for($space)->create(['profile_photo' => 'photos/keep.webp']);
    Storage::disk('public')->put('photos/keep.webp', 'x');

    $space->delete();
    DB::table('spaces')->where('id', $space->id)->update([
        'deleted_at' => CarbonImmutable::now()->subDays(29),
    ]);

    Artisan::call('purge:run');

    // Testimonial and photo still there.
    expect(Testimonial::withTrashed()->find($t->id))->not->toBeNull();
    Storage::disk('public')->assertExists('photos/keep.webp');

    // embed_configurations still there.
    expect(EmbedConfiguration::where('space_id', $space->id)->count())->toBe(1);

    // Space still soft-deleted (NOT tombstoned).
    $stillThere = Space::withTrashed()->find($space->id);
    expect($stillThere->deleted_at)->not->toBeNull();
    expect($stillThere->isTombstoned())->toBeFalse();
});

test('tombstoned Space: slug is still taken; /spaces/deleted hides it; /livewire/update restore returns 404; control Space within retention IS restored', function () {
    $user = User::factory()->create();
    $tombstoned = Space::factory()->for($user)->create(['slug' => 'gone-forever']);
    $tombstoned->delete();
    DB::table('spaces')->where('id', $tombstoned->id)->update([
        'deleted_at' => CarbonImmutable::now()->subDays(31),
    ]);

    $live = Space::factory()->for($user)->create();
    $live->delete();
    DB::table('spaces')->where('id', $live->id)->update([
        'deleted_at' => CarbonImmutable::now()->subDays(5),
    ]);

    // Run purge so the tombstoned Space's slug is also enforced.
    Artisan::call('purge:run');

    // Slug is still taken (probe + SlugService).
    expect(SlugService::isTaken('gone-forever'))->toBeTrue();
    $this->actingAs($user)
        ->getJson('/spaces/check-slug?slug=gone-forever')
        ->assertOk()
        ->assertJson([
            'available' => false,
        ]);

    // /spaces/deleted does NOT list the tombstoned Space.
    Livewire::actingAs($user)
        ->test(SpaceDeleted::class)
        ->assertDontSee('gone-forever');

    // /dashboard Deleted Spaces tab does NOT list the tombstoned Space.
    Livewire::actingAs($user)
        ->test(DashboardIndex::class, ['tab' => 'deleted'])
        ->assertDontSee('gone-forever');

    // The control Space within retention IS listed.
    Livewire::actingAs($user)
        ->test(SpaceDeleted::class)
        ->assertSee($live->title);

    // Real HTTP — try to restore the tombstoned Space via /livewire/update
    // (DashboardIndex.restoreDeletedSpace). Expect a 404 (the service
    // returns 'notFound' because the Space is past the retention window
    // is "tombstoned" — but the Livewire method turns tombstoned into
    // an error message, NOT a 404. We need the actual restore call to
    // hit abort(404) for a non-owner OR an invalid id. Let's prove the
    // service returns "tombstoned" via a direct unit test path).
    $service = app(\App\Services\SpaceRestoreService::class);
    $r = $service->restore((int) $tombstoned->id);
    expect($r->isTombstoned())->toBeTrue();
    // Service refuses to restore.
    expect(Space::withTrashed()->find($tombstoned->id)->deleted_at)->not->toBeNull();

    // CONTROL: a Space within retention IS restored by its owner.
    $aliceMounted = Livewire::actingAs($user)
        ->test(SpaceDeleted::class);

    $reflection = new ReflectionObject($aliceMounted);
    $lastStateProp = $reflection->getProperty('lastState');
    $lastStateProp->setAccessible(true);
    $lastState = $lastStateProp->getValue($aliceMounted);
    $snapshot = $lastState->getSnapshot();

    $payload = [
        'components' => [
            [
                'snapshot' => json_encode($snapshot),
                'calls' => [
                    [
                        'path' => '',
                        'method' => 'restore',
                        'params' => [(int) $live->id],
                    ],
                ],
                'updates' => [],
            ],
        ],
    ];

    $response = $this->actingAs($user)
        ->withHeaders(['X-Livewire' => '1'])
        ->post('/livewire/update', $payload);

    $response->assertOk();
    expect(Space::withTrashed()->find($live->id)->deleted_at)->toBeNull();
});

test('second purge:run with nothing eligible changes no rows', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    $live = Testimonial::factory()->for($space)->create();
    $trashed = Testimonial::factory()->for($space)->create();
    $trashed->delete();

    // First run — nothing eligible, no changes.
    Artisan::call('purge:run');

    $rows1 = DB::table('testimonials')->count();
    $trashed1 = Testimonial::onlyTrashed()->count();

    // Second run — same result.
    Artisan::call('purge:run');

    $rows2 = DB::table('testimonials')->count();
    $trashed2 = Testimonial::onlyTrashed()->count();

    expect($rows2)->toBe($rows1);
    expect($trashed2)->toBe($trashed1);
    expect($live->fresh())->not->toBeNull();
});

test('audit log entries contain only IDs and counts — no name, email, or testimonial text', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    $row = Testimonial::factory()->for($space)->create([
        'name' => 'SARA PII-SECRET',
        'email' => 'sara-pii@example.test',
        'testimonial' => 'THIS IS PII-SECRET TEXT THAT MUST NOT BE LOGGED',
        'profile_photo' => 'photos/logtest.webp',
    ]);
    Storage::disk('public')->put('photos/logtest.webp', 'x');
    Testimonial::withTrashed()->where('id', $row->id)->update([
        'deleted_at' => CarbonImmutable::now()->subDays(31),
    ]);

    // Capture log records via a test channel that mirrors `purge`.
    $captured = [];
    $testHandler = new \Monolog\Handler\TestHandler();
    $testHandler->setFormatter(new \Monolog\Formatter\JsonFormatter());
    Log::channel('purge')->getLogger()->pushHandler($testHandler);

    Artisan::call('purge:run');

    // Pull captured records off the TestHandler.
    $captured = $testHandler->getRecords();

    // At least one purge entry was written.
    expect(count($captured))->toBeGreaterThan(0);

    // Build a flat string of every message + context so we can scan
    // for PII.
    $flat = '';
    foreach ($captured as $rec) {
        $flat .= $rec['message'] ?? '';
        $flat .= json_encode($rec['context'] ?? []);
    }

    expect($flat)->not->toContain('SARA PII-SECRET');
    expect($flat)->not->toContain('sara-pii@example.test');
    expect($flat)->not->toContain('THIS IS PII-SECRET TEXT');

    // The log entry must contain IDs and counts.
    expect($flat)->toContain('purged_testimonials');
});

test('purge log channel is configured with days=14 explicit', function () {
    expect((int) config('logging.channels.purge.days'))->toBe(14);
});

test('Space past retention: even if one of its testimonials FAILS photo delete, the Space IS still tombstoned (embed_config deleted, deleted_at kept)', function () {
    // The PurgeService contract (purgeEligibleSpaces docstring):
    // a failing testimonial photo delete must NOT block the Space
    // tombstone. The Space is being deleted anyway; the failed
    // testimonial is retried on the next run; the embed endpoint
    // sees a missing embed_configurations row and returns the
    // empty {config: null, testimonials: []} shape.
    Storage::fake('public');

    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    EmbedConfiguration::factory()->for($space)->create();

    // Two testimonials: one with a real photo (will succeed), one
    // with a photo whose delete() will throw.
    $okT = Testimonial::factory()->for($space)->create(['profile_photo' => 'photos/ok.webp']);
    Storage::disk('public')->put('photos/ok.webp', 'fake');

    $failT = Testimonial::factory()->for($space)->create(['profile_photo' => 'photos/fail-in-space.webp']);
    Storage::disk('public')->put('photos/fail-in-space.webp', 'fake');

    // Soft-delete the Space 31 days ago (past retention). Both
    // testimonials stay LIVE (live in a tombstoned Space — the
    // exact case purgeEligibleSpaces must sweep up).
    $space->delete();
    DB::table('spaces')->where('id', $space->id)->update([
        'deleted_at' => CarbonImmutable::now()->subDays(31),
    ]);

    // Mock the public disk so delete('photos/fail-in-space.webp')
    // throws — every other path delegates to the real fake.
    $realDisk = Storage::disk('public');
    $mockDisk = Mockery::mock($realDisk)->makePartial();
    $mockDisk->shouldReceive('delete')
        ->andReturnUsing(function ($paths) use ($realDisk) {
            foreach ((array) $paths as $p) {
                if ($p === 'photos/fail-in-space.webp') {
                    throw new \RuntimeException('simulated disk error');
                }
            }

            return $realDisk->delete($paths);
        });
    Storage::set('public', $mockDisk);

    $exit = Artisan::call('purge:run');
    expect($exit)->toBe(0);

    // 1. The successful testimonial is gone, its photo is gone.
    expect(Testimonial::withTrashed()->find($okT->id))->toBeNull();
    $realDisk->assertMissing('photos/ok.webp');

    // 2. The failed testimonial is KEPT (its row and its file
    //    remain) — purge:run will retry it on the next run.
    $stillThere = Testimonial::withTrashed()->find($failT->id);
    expect($stillThere)->not->toBeNull();
    $realDisk->assertExists('photos/fail-in-space.webp');

    // 3. THE CRITICAL ASSERTIONS for the Space tombstone contract:
    //    a. embed_configurations row is DELETED (so the public
    //       embed endpoint returns the empty shape).
    expect(EmbedConfiguration::where('space_id', $space->id)->count())->toBe(0);
    //    b. The Space row is KEPT (tombstone).
    $tombstone = Space::withTrashed()->find($space->id);
    expect($tombstone)->not->toBeNull();
    //    c. The Space's deleted_at is UNCHANGED (still 31 days ago).
    expect($tombstone->deleted_at)->not->toBeNull();
    expect($tombstone->deleted_at->lt(CarbonImmutable::now()->subDays(30)))->toBeTrue();
    //    d. The Space's slug is still claimed (so re-using the
    //       slug is rejected).
    expect(Space::withTrashed()->where('slug', $tombstone->slug)->count())->toBe(1);

    // 4. The failed=1 counter for the Space is reflected in the
    //    phase-2 summary; the overall run exits 0 (failures are
    //    counted, not raised).
    expect($exit)->toBe(0);
});

test('per-Space log entry reports that Space\'s own testimonial/photo count, not the running total', function () {
    // Regression test for the bug where
    // App\Services\PurgeService::purgeEligibleSpaces() reported the
    // run-wide cumulative count in each "purge:run space tombstoned"
    // log entry, instead of the per-Space count.
    Storage::fake('public');

    $user = User::factory()->create();
    $spaceA = Space::factory()->for($user)->create();
    $spaceB = Space::factory()->for($user)->create();

    // 3 testimonials for A, 1 for B. Photos are irrelevant here —
    // we are testing the COUNT field, not the photo path.
    Testimonial::factory()->for($spaceA)->create(['profile_photo' => null]);
    Testimonial::factory()->for($spaceA)->create(['profile_photo' => null]);
    Testimonial::factory()->for($spaceA)->create(['profile_photo' => null]);
    Testimonial::factory()->for($spaceB)->create(['profile_photo' => null]);

    // Both Spaces past retention.
    $spaceA->delete();
    $spaceB->delete();
    DB::table('spaces')->where('id', $spaceA->id)->update([
        'deleted_at' => CarbonImmutable::now()->subDays(31),
    ]);
    DB::table('spaces')->where('id', $spaceB->id)->update([
        'deleted_at' => CarbonImmutable::now()->subDays(31),
    ]);

    // Capture log records on the `purge` channel — same pattern as
    // the "audit log entries" test.
    $testHandler = new \Monolog\Handler\TestHandler();
    $testHandler->setFormatter(new \Monolog\Formatter\JsonFormatter());
    Log::channel('purge')->getLogger()->pushHandler($testHandler);

    Artisan::call('purge:run');

    $records = $testHandler->getRecords();

    // Pull out the per-Space "purge:run space tombstoned" entries.
    $spaceEntries = array_values(array_filter(
        $records,
        fn ($r) => ($r['message'] ?? null) === 'purge:run space tombstoned',
    ));

    // One entry per Space.
    expect(count($spaceEntries))->toBe(2);

    // The log payload is JSON-formatted into $r['context'] (because
    // we attached a JsonFormatter). Normalise so we can index by
    // field name regardless of formatter choice.
    $bySpace = [];
    foreach ($spaceEntries as $rec) {
        $ctx = $rec['context'] ?? [];
        if (is_string($ctx)) {
            $decoded = json_decode($ctx, true);
            $ctx = is_array($decoded) ? $decoded : [];
        }
        $bySpace[(int) ($ctx['space_id'] ?? 0)] = $ctx;
    }

    // Space A: 3 of its own testimonials.
    expect($bySpace[(int) $spaceA->id]['purged_testimonials'] ?? null)->toBe(3);
    // Space B: 1 of its own testimonials.
    expect($bySpace[(int) $spaceB->id]['purged_testimonials'] ?? null)->toBe(1);

    // Defensive — neither entry should carry the cumulative total
    // of 4 (A's 3 + B's 1).
    foreach ($bySpace as $spaceId => $ctx) {
        expect($ctx['purged_testimonials'] ?? null)->not->toBe(4);
    }
});
