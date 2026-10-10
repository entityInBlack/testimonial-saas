<?php

namespace Database\Seeders;

use App\Models\DeletionRequest;
use App\Models\EmbedConfiguration;
use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Step 8 deterministic demo fixtures.
 *
 * Drives the demo a fresh `php artisan migrate --seed && serve`:
 *   - Maya Sharma  : 2 Spaces (brightcopy-wins 6 LIVE,
 *                               course-launch 3 LIVE)
 *   - Dev Okafor   : 2 Spaces (shiplog-reviews 100 LIVE + 1 soft-deleted,
 *                               beta-testers 12 LIVE)
 *   - Priya Raman  : 1 testimonial in shiplog-reviews + 1 in beta-testers
 *                    (same email — exercises the "count once across
 *                    Spaces" Total-customers formula).
 *   - Ben Fischer  : 1 testimonial in shiplog-reviews.
 *
 * Hard rules honoured:
 *   - NEVER write `is_public` directly (Hard Rule 3 — STORED generated
 *     column managed by MySQL).
 *   - NEVER write `profile_photo` to a non-null value (no files on
 *     disk; the seeder touches zero storage).
 *   - `submitted_at` is fixed per-row (no `now()`, no `random()`)
 *     so the embed-ordering test and the dashboard graph bucket
 *     counts are stable across runs.
 *   - `public_id` is generated through `Space::generatePublicId()`
 *     so the app's existing logic is the single source of truth.
 *   - Insert directly, NOT through SubmissionService. SubmissionService
 *     is built around the public form (cap, dedupe, photo pipeline)
 *     and would not let us reach the cap or insert soft-deleted rows.
 *   - `consent_given = 1` rows get `consented_at = submitted_at` and
 *     `consent_text_version = config('consent.current')`. The Sara
 *     row (consent = 0) gets both nulls.
 *   - All emails are unique across the whole seed (no two rows share
 *     an email) except Priya, who deliberately appears in TWO of
 *     Dev's Spaces with the SAME email — that is what makes
 *     "Total customers" = 100 + 12 - 1 = 111.
 */
class FixtureSeeder extends Seeder
{
    public function run(): void
    {
        // Wrap in a transaction so a partial seed never leaves a half-
        // populated dev DB. The seeder runs inside RefreshDatabase in
        // the test suite, so the transaction is rolled back at the
        // end of each test automatically.
        DB::transaction(function () {
            $maya = User::where('email', 'maya@brightcopy.co')->firstOrFail();
            $dev = User::where('email', 'dev@shiplog.io')->firstOrFail();

            $this->fillMaya($maya);
            $this->fillDev($dev);
        });
    }

    // -----------------------------------------------------------------
    // Maya
    // -----------------------------------------------------------------

    private function fillMaya(User $maya): void
    {
        $brightcopyWins = $this->makeSpace($maya, [
            'slug' => 'brightcopy-wins',
            'name' => 'BrightCopy Wins',
            'title' => 'BrightCopy Wins',
            'subtitle' => 'Customer wins from the BrightCopy launch',
            'ask' => 'Tell us about a win your team had with BrightCopy.',
        ]);

        $courseLaunch = $this->makeSpace($maya, [
            'slug' => 'course-launch',
            'name' => 'Course Launch',
            'title' => 'Course Launch',
            'subtitle' => 'Course launch testimonials',
            'ask' => 'What changed for you after the course?',
        ]);

        $this->fillBrightcopyWins($brightcopyWins);
        $this->fillCourseLaunch($courseLaunch);
    }

    private function fillBrightcopyWins(Space $space): void
    {
        $rows = [
            ['Ana M.',       'ana.m.bcw@example.test',   'Maya Sharma is brilliant.'],
            ['Beth O.',      'beth.obcw@example.test',    'Loved the onboarding.'],
            ['Carla P.',     'carla.pbcw@example.test',   'Our launch was smooth.'],
            ['Diego Q.',     'diego.qbcw@example.test',   'Support is responsive.'],
            ['Esme R.',      'esme.rbcw@example.test',    'Beautiful copy.'],
            ['Farah S.',     'farah.sbcw@example.test',   'It changed our funnel.'],
        ];

        // 6 rows -> slots 0..5 -> days 0,0,1,1,2,2 (UTC).
        $timestamps = $this->slots(6);

        foreach ($rows as $i => [$name, $email, $body]) {
            $this->insertTestimonial(
                space: $space,
                name: $name,
                email: $email,
                body: $body,
                submittedAt: $timestamps[$i],
                consent: true,
                wallOfLove: true,
                isFavorite: false,
                isHidden: false,
                softDelete: false,
            );
        }
    }

    private function fillCourseLaunch(Space $space): void
    {
        $rows = [
            ['Gita T.',  'gita.t.cl@example.test',  'The modules were practical.'],
            ['Hari U.',  'hari.u.cl@example.test',  'I shipped in 30 days.'],
            ['Iris V.',  'iris.v.cl@example.test',  'Worth every minute.'],
        ];

        // 3 rows -> days 0, 1, 2.
        $timestamps = $this->slots(3);

        foreach ($rows as $i => [$name, $email, $body]) {
            $this->insertTestimonial(
                space: $space,
                name: $name,
                email: $email,
                body: $body,
                submittedAt: $timestamps[$i],
                consent: true,
                wallOfLove: true,
                isFavorite: false,
                isHidden: false,
                softDelete: false,
            );
        }
    }

    // -----------------------------------------------------------------
    // Dev
    // -----------------------------------------------------------------

    private function fillDev(User $dev): void
    {
        $shiplog = $this->makeSpace($dev, [
            'slug' => 'shiplog-reviews',
            'name' => 'Shiplog Reviews',
            'title' => 'Shiplog Reviews',
            'subtitle' => 'Customer reviews for Shiplog',
            'ask' => 'Tell us what you think of Shiplog.',
        ]);

        $beta = $this->makeSpace($dev, [
            'slug' => 'beta-testers',
            'name' => 'Beta Testers',
            'title' => 'Beta Testers',
            'subtitle' => 'What our beta testers are saying',
            'ask' => 'What did you find in the beta?',
        ]);

        // Seed shiplog-reviews's embed_configurations row with
        // item_limit=50 so the embed endpoint returns the 21
        // publicly-visible items (Priya + 20 generated WoL). Without
        // this row the API would use config('embed.item_limit') = 12
        // and the 21-item proof would fail.
        EmbedConfiguration::create([
            'space_id' => $shiplog->id,
            'layout' => config('embed.layout'),
            'dark_mode' => config('embed.dark_mode'),
            'animation_enabled' => config('embed.animation_enabled'),
            'background_color' => config('embed.background_color'),
            'item_limit' => 50,
            'show_rating' => config('embed.show_rating'),
        ]);

        // beta-testers gets NO embed_configurations row — the code
        // defaults (item_limit = 12) already give 12 items, exactly
        // what the spec requires.

        $this->fillShiplog($shiplog);
        $this->fillBetaTesters($beta);

        // 1 open deletion request for Sara (email-match path,
        // Hard Rule 14). space_id is set; testimonial_id is null on
        // purpose.
        DeletionRequest::create([
            'email' => 'sara@example.test',
            'space_slug' => 'shiplog-reviews',
            'space_id' => $shiplog->id,
            'testimonial_id' => null,
            'status' => DeletionRequest::STATUS_OPEN,
            'acted_at' => null,
        ]);
    }

    private function fillShiplog(Space $space): void
    {
        // 5 named rows + 96 generated = 101 rows total for shiplog.
        // Pre-allocate 101 DISTINCT slots for this Space so the
        // 5 named rows and the 96 generated rows never collide.
        // Order matters: the named rows take slots 0..4, the
        // generated rows take slots 5..100. The 96 generated are
        // inserted in that slot order; the FIRST 20 (slots 5..24)
        // get wall_of_love=1, the other 76 (slots 25..100) get
        // wall_of_love=0.
        $allSlots = $this->slots(101);
        $named = array_slice($allSlots, 0, 5);
        $generated = array_slice($allSlots, 5, 96);

        // Priya (slot 0, the most recent), Ben (1), Tom (2),
        // Sara (3), Ana (4). Insertion order = slot order = most
        // recent first, so the lowest AUTO_INCREMENT ids are the
        // lowest slot numbers. That is what makes "the 20
        // lowest-id generated rows are wall_of_love = 1" hold:
        // slots 5..24 are the FIRST 20 generated rows by id.
        $this->insertTestimonial(
            space: $space,
            name: 'Priya Raman',
            email: 'priya@nimbus.dev',
            body: 'Shiplog is the best logistics tool we have used.',
            submittedAt: $named[0],
            consent: true,
            wallOfLove: true,
            isFavorite: false,
            isHidden: false,
            softDelete: false,
        );

        $this->insertTestimonial(
            space: $space,
            name: 'Ben Fischer',
            email: 'ben@example.test',
            body: 'Shiplog has changed how we ship.',
            submittedAt: $named[1],
            consent: true,
            wallOfLove: true,
            isFavorite: false,
            isHidden: true, // is_hidden = 1 -> is_public STORED = 0
            softDelete: false,
        );

        $this->insertTestimonial(
            space: $space,
            name: 'Tom Alvarez',
            email: 'tom.alvarez.shl@example.test',
            body: 'Solid product. Good support.',
            submittedAt: $named[2],
            consent: true,
            wallOfLove: false, // wall_of_love = 0 -> is_public = 0
            isFavorite: false,
            isHidden: false,
            softDelete: false,
        );

        $sara = $this->insertTestimonial(
            space: $space,
            name: 'Sara Nkemi',
            email: 'sara@example.test',
            body: 'Was not for me.',
            submittedAt: $named[3],
            consent: false, // consent_given = 0 -> is_public = 0
            wallOfLove: false,
            isFavorite: false,
            isHidden: false,
            softDelete: false,
        );

        $ana = $this->insertTestimonial(
            space: $space,
            name: 'Ana Ruiz',
            email: 'ana.ruiz.shl@example.test',
            body: 'Great onboarding.',
            submittedAt: $named[4],
            consent: true,
            wallOfLove: true,
            isFavorite: false,
            isHidden: false,
            softDelete: true, // soft-deleted -> is_public = 0
        );

        // 96 generated LIVE rows.
        // First 20 (slots 5..24): consent=1, wall_of_love=1, hidden=0, favorite=0.
        // Other 76 (slots 25..100): consent=1, wall_of_love=0, hidden=0, favorite=0.
        for ($i = 0; $i < 96; $i++) {
            $isWol = $i < 20;
            $this->insertTestimonial(
                space: $space,
                name: 'Customer '.($i + 1),
                email: 'shiplog-c'.($i + 1).'.shl@example.test',
                body: 'Shiplog review '.($i + 1).'.',
                submittedAt: $generated[$i],
                consent: true,
                wallOfLove: $isWol,
                isFavorite: false,
                isHidden: false,
                softDelete: false,
            );
        }

        // Local references for IDE / static-analysis happiness
        unset($sara, $ana);
    }

    private function fillBetaTesters(Space $space): void
    {
        // 12 rows for beta: Priya + 11 generated. All consent=1,
        // wall_of_love=1, hidden=0 so the embed returns 12 items
        // per the spec.
        $slots = $this->slots(12);

        // Priya again — same email as in shiplog-reviews. This is
        // the cross-Space shared-email row that makes
        // COUNT(DISTINCT email) = 111 for Dev.
        $this->insertTestimonial(
            space: $space,
            name: 'Priya Raman',
            email: 'priya@nimbus.dev',
            body: 'Beta feedback: love the new map view.',
            submittedAt: $slots[0],
            consent: true,
            wallOfLove: true,
            isFavorite: false,
            isHidden: false,
            softDelete: false,
        );

        for ($i = 0; $i < 11; $i++) {
            $this->insertTestimonial(
                space: $space,
                name: 'Beta Tester '.($i + 1),
                email: 'beta-c'.($i + 1).'.bt@example.test',
                body: 'Beta review '.($i + 1).'.',
                submittedAt: $slots[$i + 1],
                consent: true,
                wallOfLove: true,
                isFavorite: false,
                isHidden: false,
                softDelete: false,
            );
        }
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    /**
     * Insert one Space row. Uses the model's static `creating` event
     * for `public_id` so the column is generated by the existing
     * code path (single source of truth). `field_config` is left to
     * the default; `rating_enabled` is on.
     */
    private function makeSpace(User $owner, array $attrs): Space
    {
        return Space::create(array_merge([
            'user_id' => $owner->id,
            'name' => $attrs['name'] ?? 'Untitled',
            'slug' => $attrs['slug'],
            'title' => $attrs['title'] ?? ($attrs['name'] ?? 'Untitled'),
            'subtitle' => $attrs['subtitle'] ?? null,
            'ask' => $attrs['ask'] ?? 'Tell us what you think.',
            'theme' => 'minimal_light',
            'rating_enabled' => true,
        ], $attrs));
    }

    /**
     * Insert one Testimonial row directly. Sets the consent audit
     * fields when consent=1, leaves them null when consent=0, and
     * uses a fixed address + the locked `is_hidden` / soft-delete
     * flag exactly as the spec demands.
     */
    private function insertTestimonial(
        Space $space,
        string $name,
        string $email,
        string $body,
        Carbon $submittedAt,
        bool $consent,
        bool $wallOfLove,
        bool $isFavorite,
        bool $isHidden,
        bool $softDelete,
    ): Testimonial {
        $row = Testimonial::create([
            'space_id' => $space->id,
            'name' => $name,
            'email' => $email,
            'address' => '1 Demo Street, Demo City',
            'company_name' => null,
            'social_url' => null,
            'profile_photo' => null,
            'testimonial' => $body,
            'rating' => 5,
            'consent_given' => $consent,
            // consented_at == submitted_at when consent=1 (Hard Rule:
            // the consent audit instant must match the submission
            // instant, not a separate later now()).
            'consented_at' => $consent ? $submittedAt : null,
            'consent_text_version' => $consent ? (string) config('consent.current', 'v1') : null,
            'is_favorite' => $isFavorite,
            'is_wall_of_love' => $wallOfLove,
            'is_hidden' => $isHidden,
            'submitted_at' => $submittedAt,
        ]);

        if ($softDelete) {
            $row->delete(); // SoftDeletes trait -> sets deleted_at
            $row->refresh();
        }

        return $row;
    }

    /**
     * Return $count DISTINCT Carbon timestamps within the
     * (now() - 90 days, now()] window, with at most 2 timestamps
     * per UTC calendar day. The schedule is fully deterministic
     * (no randomness, no DB NOW() at insert time) so the seed is
     * stable across runs and the embed-ordering tests can rely
     * on the relative order of submitted_at values.
     *
     * Slot i (0-indexed) maps to:
     *   day_index  = floor(i / 2)
     *   time_index = i % 2          (0 -> 09:00, 1 -> 15:00)
     *   submitted_at = now() - (day_index + 1) days, set to
     *                  09:00 or 15:00 UTC.
     *
     * The `+ 1` keeps day_index 0 at "1 day ago 09:00" so the
     * timestamp is always strictly in the past even if the seeder
     * runs at 00:30 in the morning. Worst case is day_index = 50
     * at 15:00 -> 51d 15h ago, still inside the (now-90d, now]
     * window.
     *
     * Distinctness: every (day_index, time_index) pair is unique,
     * so submitted_at values never collide within a Space.
     * Per-day cap: at most one (i%2=0) and one (i%2=1) per day, so
     * at most 2 per UTC calendar day.
     *
     * @return list<Carbon>
     */
    private function slots(int $count): array
    {
        $out = [];
        for ($i = 0; $i < $count; $i++) {
            $dayIndex = intdiv($i, 2);
            $slotInDay = $i % 2; // 0 = 09:00, 1 = 15:00
            $submitted = now()
                ->copy()
                ->subDays($dayIndex + 1)
                ->setTime($slotInDay === 0 ? 9 : 15, 0, 0);

            $out[] = $submitted;
        }

        return $out;
    }
}
