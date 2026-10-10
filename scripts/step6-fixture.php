<?php

// One-off script for Step 6 verification. Creates:
//   1) one soft-deleted >30-day testimonial (no Space, just a row),
//   2) one soft-deleted >30-day Space (with a testimonial and
//      an embed_configurations row).
//
// Run with: php scripts/step6-fixture.php
//
// Prints before / after state via tinker --execute; idempotent
// (refuses to create if it sees its own marker rows already
// present from a previous run).

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\EmbedConfiguration;
use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

$marker = 'STEP6-PURGE-RUN-VERIFICATION-FIXTURE';
$now = Carbon::now('UTC');
$thirtyOneDaysAgo = $now->copy()->subDays(31);

// Idempotency: look for our marker. We tag the fixture Space
// title with the marker so re-runs do nothing.
$existing = Space::withTrashed()->where('title', 'like', $marker.'%')->first();
if ($existing) {
    fwrite(STDOUT, "Fixture already present (space_id={$existing->id}); aborting.\n");
    exit(0);
}

// Owner.
$owner = User::firstOrCreate(
    ['email' => 'purge-run-verification@example.test'],
    ['name' => 'Purge Run Verification', 'password' => bcrypt(str()->random(40))]
);

// Space soft-deleted 31 days ago, with one testimonial and an
// embed_configurations row.
$space = Space::factory()->for($owner)->create([
    'title' => $marker.' tombstoned space',
    'slug' => 'step6-tombstone-'.str()->lower(str()->random(6)),
]);
EmbedConfiguration::factory()->for($space)->create();
$spaceTestimonial = Testimonial::factory()->for($space)->create([
    'profile_photo' => null,
    'name' => 'STEP6-PII-NOT-LOGGED',
    'email' => 'step6-pii@example.test',
    'testimonial' => 'STEP6 PII TEXT NOT LOGGED',
]);
$space->delete();
DB::table('spaces')->where('id', $space->id)->update([
    'deleted_at' => $thirtyOneDaysAgo,
]);

// A separate, soft-deleted >30-day testimonial in a LIVE Space
// (i.e. the testimonial is past retention, the Space is not).
$liveSpace = Space::factory()->for($owner)->create([
    'title' => $marker.' live space',
    'slug' => 'step6-live-'.str()->lower(str()->random(6)),
]);
$trashedTestimonial = Testimonial::factory()->for($liveSpace)->create([
    'profile_photo' => null,
    'name' => 'STEP6-LIVE-OWNER-NOT-LOGGED',
    'email' => 'step6-live-pii@example.test',
    'testimonial' => 'STEP6 LIVE PII TEXT NOT LOGGED',
]);
$trashedTestimonial->delete();
DB::table('testimonials')->where('id', $trashedTestimonial->id)->update([
    'deleted_at' => $thirtyOneDaysAgo,
]);

fwrite(STDOUT, "Fixture created.\n");
fwrite(STDOUT, "  tombstoned space_id={$space->id} slug={$space->slug}\n");
fwrite(STDOUT, "  tombstoned space testimonial_id={$spaceTestimonial->id}\n");
fwrite(STDOUT, "  live-space trashed testimonial_id={$trashedTestimonial->id}\n");
