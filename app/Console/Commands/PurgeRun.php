<?php

namespace App\Console\Commands;

use App\Services\PurgeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Step 6 — `php artisan purge:run`.
 *
 * The retention window comes from `config('purge.retention_days')`
 * ONLY. soft_warning_days is NOT used here (it is a dashboard
 * warning, not a cutoff).
 *
 * Order of work is fixed:
 *   1. Soft-deleted testimonials past retention — three-step order.
 *   2. Soft-deleted Spaces past retention — three-step order for
 *      every testimonial of the Space, then delete embed_config,
 *      then KEEP the Space row.
 *
 * Audit goes to the `purge` channel; entries contain row IDs and
 * counts only. No personal data.
 *
 * Exit code: 0 on a normal run, including runs where some photo
 * deletes failed (counts are reported, not raised).
 */
class PurgeRun extends Command
{
    protected $signature = 'purge:run';

    protected $description = 'Hard-delete soft-deleted testimonials and their photos past retention; tombstone their Spaces.';

    public function handle(PurgeService $service): int
    {
        $startedAt = now();
        $retention = (int) config('purge.retention_days', 30);

        $this->line(sprintf(
            '[%s] purge:run starting (retention_days=%d)',
            $startedAt->toDateTimeString(),
            $retention,
        ));

        // Phase 1 — soft-deleted testimonials past retention.
        [$purgedT, $purgedPhotosT, $failedT] = $service->purgeEligibleTestimonials();

        // Phase 2 — soft-deleted Spaces past retention (becomes tombstone).
        [$purgedS, $purgedTestimonialsS, $purgedPhotosS, $failedS] = $service->purgeEligibleSpaces();

        $purgedTestimonials = $purgedT + $purgedTestimonialsS;
        $purgedPhotos = $purgedPhotosT + $purgedPhotosS;
        $failed = $failedT + $failedS;

        $this->line(sprintf(
            '[%s] purge:run done — spaces_tombstoned=%d, purged_testimonials=%d, purged_photos=%d, failed=%d',
            now()->toDateTimeString(),
            $purgedS,
            $purgedTestimonials,
            $purgedPhotos,
            $failed,
        ));

        Log::channel('purge')->info('purge:run complete', [
            'spaces_tombstoned' => $purgedS,
            'purged_testimonials' => $purgedTestimonials,
            'purged_photos' => $purgedPhotos,
            'failed' => $failed,
            'retention_days' => $retention,
        ]);

        return self::SUCCESS;
    }
}
