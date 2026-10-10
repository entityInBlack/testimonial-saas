<?php

// One-off script for Step 7 manual browser verification.
//
// Creates, for the seeded user Maya (maya@brightcopy.co), one LIVE
// Space called "Step 7 Embed Demo" with 6 public testimonials:
//   - 1 containing exactly <script>alert(1)</script> in the testimonial
//     text (XSS test for embed.js textContent neutralization).
//   - 1 with <b>bold</b> in the name.
//   - 1 favorite with an older submitted_at (to confirm the favorite
//     outranks a newer non-favorite in the API ordering).
//   - 1 with social_url set to a real https:// URL.
//   - 1 with social_url set to "javascript:alert(1)" — the server
//     normalises it to null, and the embed client also drops it.
//     Used as a manual check for fix 3.
//   - 1 with a photo IF a fixture photo can be produced without
//     network access; otherwise the script prints the real
//     error message.
//
// Idempotent: re-runs do not duplicate the Space or raise slug errors.
// Prints the public_id at the end so the user can paste it into
// C:\11111\embed-test.html or open /spaces/{id}/embed in the browser.
//
// Run with:  php scripts/step7-fixture.php
// (Same style as scripts/step6-fixture.php — the file bootstraps
// Laravel and runs the inserts in the same process.)

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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

$marker = 'STEP7-EMBED-DEMO';
$owner = User::where('email', 'maya@brightcopy.co')->first()
    ?? User::where('email', 'like', 'maya%')->first()
    ?? User::first();

if (! $owner) {
    fwrite(STDERR, "No seeded user found; run php artisan db:seed first.\n");
    exit(1);
}

// Idempotency: marker in title.
$existing = Space::where('user_id', $owner->id)
    ->where('title', 'like', $marker.'%')
    ->first();

if ($existing) {
    fwrite(STDOUT, "Fixture already present for owner_id={$owner->id}; public_id={$existing->public_id} (slug={$existing->slug}).\n");
    exit(0);
}

$now = Carbon::now('UTC');

$space = Space::factory()->for($owner)->create([
    'title'    => $marker.' Space',
    'slug'     => 'step7-embed-demo-'.str()->lower(Str::random(6)),
    'name'     => $marker,
    'rating_enabled' => true,
    'field_config' => [
        'company_name' => ['enabled' => true, 'required' => false],
        'social_url'   => ['enabled' => true, 'required' => false],
        'profile_photo' => ['enabled' => true, 'required' => false],
    ],
]);

// XSS attempt: raw <script> tag in the testimonial body. The
// embed.js must render this as TEXT, not execute it.
$xss = Testimonial::factory()->for($space)->create([
    'name' => 'XSS Test',
    'email' => 'step7-xss@example.test',
    'testimonial' => '<script>alert(1)</script> I loved it.',
    'rating' => 5,
    'consent_given' => true,
    'is_wall_of_love' => true,
    'submitted_at' => $now->copy()->subMinutes(6),
]);

// <b>bold</b> in name — also must be neutralized.
$bold = Testimonial::factory()->for($space)->create([
    'name' => 'Bold <b>Mark</b>',
    'email' => 'step7-bold@example.test',
    'testimonial' => 'A short and helpful testimonial.',
    'rating' => 4,
    'consent_given' => true,
    'is_wall_of_love' => true,
    'submitted_at' => $now->copy()->subMinutes(5),
]);

// FAVORITE with an OLDER submitted_at. The API must still place
// this at index 0 when mixed with the rows below.
$fav = Testimonial::factory()->for($space)->create([
    'name' => 'Oldest Favorite',
    'email' => 'step7-fav@example.test',
    'testimonial' => 'This should rank first because is_favorite=1.',
    'rating' => 5,
    'is_favorite' => true,
    'consent_given' => true,
    'is_wall_of_love' => true,
    'submitted_at' => $now->copy()->subHour(),
]);

// Newer than the favorite but not a favorite.
$newer = Testimonial::factory()->for($space)->create([
    'name' => 'Newer Not-Favorite',
    'email' => 'step7-newer@example.test',
    'testimonial' => 'Newer than the favorite but lower in the list.',
    'rating' => 4,
    'is_favorite' => false,
    'consent_given' => true,
    'is_wall_of_love' => true,
    'submitted_at' => $now->copy()->subMinutes(2),
]);

// Valid https social URL — must be returned by the API unchanged.
Testimonial::factory()->for($space)->create([
    'name' => 'With Profile',
    'email' => 'step7-https@example.test',
    'testimonial' => 'Has a working https link.',
    'rating' => 4,
    'consent_given' => true,
    'is_wall_of_love' => true,
    'submitted_at' => $now->copy()->subMinutes(4),
    'social_url' => 'https://example.com/step7',
]);

// XSS via social_url — must come back as NULL in the API and
// NOT render as a link in the embed.
Testimonial::factory()->for($space)->create([
    'name' => 'Sketchy URL',
    'email' => 'step7-javascript@example.test',
    'testimonial' => 'Should never render a javascript: link.',
    'rating' => 3,
    'consent_given' => true,
    'is_wall_of_love' => true,
    'submitted_at' => $now->copy()->subMinutes(3),
    'social_url' => 'javascript:alert(1)',
]);

// Mixed rating 3.
$low = Testimonial::factory()->for($space)->create([
    'name' => 'Critical',
    'email' => 'step7-low@example.test',
    'testimonial' => 'Three stars, would have been five with chat support.',
    'rating' => 3,
    'consent_given' => true,
    'is_wall_of_love' => true,
    'submitted_at' => $now->copy()->subMinutes(1),
]);

// Photo row. Try plain GD first (always available on this machine);
// Intervention/image v4 (static facade) requires the composer install
// step and a different call shape, so the GD path is the safe one.
// If gd isn't available the row still exists with profile_photo=null.
$photoRow = Testimonial::factory()->for($space)->create([
    'name' => 'Photo Person',
    'email' => 'step7-photo@example.test',
    'testimonial' => 'Has a face.',
    'rating' => 5,
    'consent_given' => true,
    'is_wall_of_love' => true,
    'submitted_at' => $now->copy()->subMinutes(2),
    'profile_photo' => null,
]);

if (extension_loaded('gd') && function_exists('imagecreatetruecolor')) {
    try {
        $img = imagecreatetruecolor(200, 200);
        $bg = imagecolorallocate($img, 0x4f, 0x46, 0xe5);
        imagefilledrectangle($img, 0, 0, 200, 200, $bg);
        ob_start();
        imagewebp($img, null, 80);
        $bytes = ob_get_clean();
        imagedestroy($img);

        if ($bytes !== false && strlen($bytes) > 0) {
            $rel = 'photos/'.Str::lower(Str::random(32)).'.webp';
            Storage::disk('public')->put($rel, $bytes);
            $photoRow->profile_photo = $rel;
            $photoRow->save();
        } else {
            fwrite(STDOUT, "Photo skipped (imagewebp returned empty). PHP Gd WebP supported: ".(function_exists('imagewebp') ? 'yes' : 'no')."\n");
        }
    } catch (Throwable $e) {
        fwrite(STDOUT, "Photo skipped (real error): ".$e->getMessage()."\n");
    }
} else {
    fwrite(STDOUT, "Photo skipped (gd not available).\n");
}

// Save an embed config so the builder has a saved row to test
// against. Defaults are fine.
EmbedConfiguration::create([
    'space_id'         => $space->id,
    'layout'           => 'masonry',
    'dark_mode'        => false,
    'animation_enabled' => true,
    'background_color' => null,
    'show_rating'      => true,
    'item_limit'       => 12,
]);

fwrite(STDOUT, "Fixture created.\n");
fwrite(STDOUT, "  space_id={$space->id}\n");
fwrite(STDOUT, "  public_id={$space->public_id}\n");
fwrite(STDOUT, "  slug={$space->slug}\n");
fwrite(STDOUT, "  owner_id={$owner->id} ({$owner->email})\n");
fwrite(STDOUT, "\nUse this in C:\\11111\\embed-test.html:\n");
fwrite(STDOUT, "  <div data-testimonial-space=\"{$space->public_id}\"></div>\n");
fwrite(STDOUT, "  <script src=\"http://127.0.0.1:8000/embed.js\" async></script>\n");
