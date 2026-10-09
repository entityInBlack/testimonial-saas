<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a photo upload is rejected by the pipeline (Hard Rule 13).
 *
 * The message is intentionally one of a small set of generic strings
 * defined in the constructor. NEVER include the uploaded file's path,
 * its original filename, or any disk-relative path in `$message` —
 * error strings bubble into the rendered page and the audit log.
 */
class PhotoRejectedException extends RuntimeException
{
    /** @var array<int, string> */
    public const MESSAGES = [
        'invalid'        => 'Invalid photo.',
        'too_large'      => 'Photo is too large.',
        'too_large_px'   => 'Photo is too large in pixels.',
        'wrong_format'   => 'Unsupported photo format.',
        'cannot_save'    => 'Photo could not be saved.',
    ];

    public static function invalid(): self
    {
        return new self(self::MESSAGES['invalid']);
    }

    public static function tooLarge(): self
    {
        return new self(self::MESSAGES['too_large']);
    }

    public static function tooLargePixels(): self
    {
        return new self(self::MESSAGES['too_large_px']);
    }

    public static function wrongFormat(): self
    {
        return new self(self::MESSAGES['wrong_format']);
    }

    public static function cannotSave(): self
    {
        return new self(self::MESSAGES['cannot_save']);
    }
}
