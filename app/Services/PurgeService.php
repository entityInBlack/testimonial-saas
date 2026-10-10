<?php

namespace App\Services;

use App\Models\DeletionRequest;
use App\Models\EmbedConfiguration;
use App\Models\Space;
use App\Models\Testimonial;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Step 6 — purge:run core.
 *
 * Single source of truth for the three-step order. Forget-now in
 * InboxRow already runs the same order; this class is the only
 * place that orchestrates the *job* (per-testimonial past retention
 * and per-Space past retention).
 *
 * Hard Rule 6 (three-step order) is fixed; see Purge spec §3.3.
 *
 * Audit logs go to the `purge` channel and contain ONLY row IDs
 * and counts. Never name, email, address, testimonial text, or
 * social URL.
 */
class PurgeService
{
    /**
     * Hard-cap a single Space's three-step order to this many
     * testimonials, so a Space with thousands of testimonials does
     * not blow up one transaction. The job re-runs until all rows
     * are processed.
     */
    public const PER_SPACE_TESTIMONIAL_CHUNK = 200;

    /**
     * Outcome of `forgetTestimonial`. `purged=true` means the row
     * was force-deleted. `purged=false` means Step 2 (photo delete)
     * failed for a real reason and the row stays for the next run.
     */
    public function forgetTestimonial(Testimonial $t): bool
    {
        $testimonialId = (int) $t->id;
        $spaceId = (int) $t->space_id;
        $email = (string) $t->email;

        // Step 1 — close matching open deletion_requests FIRST, so
        // the testimonial_id FK is still populated. The match is by
        // testimonial_id OR (testimonial_id IS NULL AND email).
        DB::table('deletion_requests')
            ->where('space_id', $spaceId)
            ->where('status', DeletionRequest::STATUS_OPEN)
            ->where(function ($q) use ($testimonialId, $email) {
                $q->where('testimonial_id', $testimonialId)
                    ->orWhere(function ($q2) use ($email) {
                        $q2->whereNull('testimonial_id')->where('email', $email);
                    });
            })
            ->update([
                'status' => DeletionRequest::STATUS_ACTED,
                'acted_at' => now(),
            ]);

        // Step 2 — delete the photo file from the `public` disk.
        // Ignore ONLY "file not found"; any other error keeps the
        // row intact for the next run.
        $photo = $t->profile_photo;
        if ($photo) {
            try {
                Storage::disk('public')->delete($photo);
            } catch (Throwable $e) {
                // Real failure (not "file not found") — abort; the
                // job will retry this row on the next run.
                Log::channel('purge')->warning('purge:run photo delete failed', [
                    'testimonial_id' => $testimonialId,
                    'space_id' => $spaceId,
                    'error' => substr($e->getMessage(), 0, 200),
                ]);

                return false;
            }
        }

        // Step 3 — forceDelete LAST. The nullOnDelete FK on
        // deletion_requests.testimonial_id will null the FK on any
        // still-open row pointing at this testimonial, but Step 1
        // already touched every open row matching this testimonial.
        $t->forceDelete();

        return true;
    }

    /**
     * Purge all testimonials whose `deleted_at` is older than
     * `retention_days`. Returns
     *   [purged_count, purged_photos, failed_count].
     *
     * Eligibility: deleted_at STRICTLY older than
     * `now()->subDays(retention_days)`. The boundary is NOT
     * inclusive (a row at exactly retention_days stays).
     */
    public function purgeEligibleTestimonials(): array
    {
        $retentionDays = (int) config('purge.retention_days', 30);
        $cutoff = now()->subDays($retentionDays);

        $purged = 0;
        $purgedPhotos = 0;
        $failed = 0;

        // Process in chunks so a Space with hundreds of past-retention
        // rows does not lock the table. One testimonial at a time
        // (each is its own transaction boundary).
        Testimonial::onlyTrashed()
            ->where('deleted_at', '<', $cutoff)
            ->orderBy('id')
            ->chunkById((int) config('purge.batch_size', 50), function ($chunk) use (&$purged, &$purgedPhotos, &$failed) {
                foreach ($chunk as $row) {
                    $hadPhoto = ! empty($row->profile_photo);

                    $ok = $this->forgetTestimonial($row);

                    if ($ok) {
                        $purged++;
                        if ($hadPhoto) {
                            $purgedPhotos++;
                        }
                    } else {
                        $failed++;
                    }
                }
            });

        return [$purged, $purgedPhotos, $failed];
    }

    /**
     * Purge every Space soft-deleted past retention. Per Space:
     *   1. for each of its testimonials (live AND soft-deleted,
     *      regardless of their own deleted_at) run the three-step
     *      order;
     *   2. delete() the embed_configurations row for that Space;
     *   3. KEEP the spaces row as a tombstone (deleted_at and
     *      slug stay).
     *
     * Decision on a testimonial that fails Step 1 (photo delete):
     * if even one testimonial of the Space fails, we STILL proceed
     * to Step 2 (delete embed_configurations) — the embed config is
     * the public side of the Space, and the Space is being
     * tombstoned anyway. The failed testimonials are kept (with
     * deleted_at set where they had it, live where they didn't) and
     * the next purge:run will retry them; the embed endpoint sees
     * `embed_configurations=null` and returns the empty
     * `{config: null, testimonials: []}` shape.
     *
     * Returns
     *   [purged_spaces, purged_testimonials, purged_photos, failed].
     */
    public function purgeEligibleSpaces(): array
    {
        $retentionDays = (int) config('purge.retention_days', 30);
        $cutoff = now()->subDays($retentionDays);

        $purgedSpaces = 0;
        $purgedTestimonials = 0;
        $purgedPhotos = 0;
        $failed = 0;

        // Per Space — load with Trashed so we can iterate soft-deleted
        // Spaces (we only want Spaces past retention, but pull all of
        // their testimonials, live + trashed).
        Space::onlyTrashed()
            ->where('deleted_at', '<', $cutoff)
            ->orderBy('id')
            ->chunkById((int) config('purge.batch_size', 50), function ($spaces) use (&$purgedSpaces, &$purgedTestimonials, &$purgedPhotos, &$failed) {
                foreach ($spaces as $space) {
                    $spaceId = (int) $space->id;

                    // Step 1 — every testimonial of this Space, live
                    // AND soft-deleted, regardless of the testimonial's
                    // own deleted_at. Chunked so we don't OOM a huge
                    // Space.
                    $tChunkSize = self::PER_SPACE_TESTIMONIAL_CHUNK;
                    $lastId = 0;
                    do {
                        $rows = Testimonial::withTrashed()
                            ->where('space_id', $spaceId)
                            ->where('id', '>', $lastId)
                            ->orderBy('id')
                            ->limit($tChunkSize)
                            ->get();

                        if ($rows->isEmpty()) {
                            break;
                        }

                        foreach ($rows as $row) {
                            $hadPhoto = ! empty($row->profile_photo);

                            $ok = $this->forgetTestimonial($row);

                            if ($ok) {
                                $purgedTestimonials++;
                                if ($hadPhoto) {
                                    $purgedPhotos++;
                                }
                            } else {
                                $failed++;
                            }
                        }

                        $lastId = (int) $rows->last()->id;
                    } while ($rows->count() === $tChunkSize);

                    // Step 2 — drop the embed_configurations row. Use
                    // a direct query (not a model) so the deletion is
                    // unconditional. We do NOT model-cascade — the FK
                    // is plain (no cascade), per the data model.
                    $deletedEmbed = DB::table('embed_configurations')
                        ->where('space_id', $spaceId)
                        ->delete();

                    // Step 3 — KEEP the spaces row. The slug and
                    // deleted_at are preserved. No action.

                    $purgedSpaces++;

                    Log::channel('purge')->info('purge:run space tombstoned', [
                        'space_id' => $spaceId,
                        'purged_testimonials' => $purgedTestimonials,
                        'purged_photos' => $purgedPhotos,
                        'deleted_embed_configurations' => $deletedEmbed,
                    ]);
                }
            });

        return [$purgedSpaces, $purgedTestimonials, $purgedPhotos, $failed];
    }
}
