<?php

namespace App\Support;

use App\Exceptions\PhotoRejectedException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Encoders\WebpEncoder;

/**
 * Hard Rule 13 photo pipeline. Every check is intentionally cheap and
 * ordered fail-fast. The pipeline NEVER reveals a path, an original
 * filename, or a disk location in any error message.
 *
 * Pipeline order (matches `design.md` line 87 + build order Step 3.4):
 *   1. Size cap (5 MB) — read from the in-memory UploadedFile.
 *   2. getimagesize() header check (BEFORE Intervention decodes).
 *      - false → invalid.
 *      - either dimension > 4000 → too-large-pixels.
 *   3. MIME allowlist (jpg|png|webp).
 *   4. Generate random name BEFORE save.
 *   5. Intervention re-encode to webp ~400 px wide.
 *   6. Save at `photos/{random}.webp` on the public disk.
 *
 * Returns the relative path (`photos/...webp`). The caller is
 * responsible for passing it to `Testimonial::profile_photo` and for
 * rolling the file back if the DB insert fails.
 */
class PhotoProcessor
{
    private const MAX_BYTES = 5 * 1024 * 1024;

    private const MAX_DIMENSION = 4000;

    private const TARGET_WIDTH = 400;

    private const ALLOWED_MIMES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    public function handle(UploadedFile $file): string
    {
        // 1) size
        if ($file->getSize() > self::MAX_BYTES) {
            throw PhotoRejectedException::tooLarge();
        }

        // 2) header (no Intervention decode yet)
        $info = @getimagesize($file->getRealPath());
        if ($info === false) {
            throw PhotoRejectedException::invalid();
        }
        [$width, $height] = $info;
        if ($width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION) {
            throw PhotoRejectedException::tooLargePixels();
        }

        // 3) mime
        $mime = (string) $file->getMimeType();
        if (! in_array($mime, self::ALLOWED_MIMES, true)) {
            throw PhotoRejectedException::wrongFormat();
        }

        // 4) random name BEFORE save
        $name = Str::lower(Str::random(32));
        $path = 'photos/'.$name.'.webp';

        // 5) re-encode via Intervention (GD driver)
        $manager = new ImageManager(new GdDriver());
        $encoded = $manager
            ->decodePath($file->getRealPath())
            ->scaleDown(width: self::TARGET_WIDTH)
            ->encode(new WebpEncoder(quality: 80));

        // 6) write
        $bytes = (string) $encoded;
        if ($bytes === '') {
            throw PhotoRejectedException::cannotSave();
        }

        $ok = Storage::disk('public')->put($path, $bytes);
        if (! $ok) {
            throw PhotoRejectedException::cannotSave();
        }

        return $path;
    }

    /**
     * Best-effort delete used by `SubmissionService` when the DB insert
     * fails after the file was already written. Never throws.
     */
    public function rollback(string $path): void
    {
        try {
            Storage::disk('public')->delete($path);
        } catch (\Throwable) {
            // intentionally swallowed — the rollback is best-effort
            // and the row stays for the next run / manual cleanup.
        }
    }
}
