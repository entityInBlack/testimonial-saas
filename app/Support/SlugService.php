<?php

namespace App\Support;

use App\Models\Space;
use Illuminate\Support\Str;

/**
 * Slug policy for v1.
 *
 *  - Globally unique, enforced by a database unique index (the source of truth).
 *  - Reserved list (PRD §3.2): s, api, admin, login, register, dashboard,
 *    billing, embed, assets, up.
 *  - Trim any appended -N suffix so the final slug is at most 60 chars.
 *  - On collision: suggest the next free -2, -3 suffix.
 *  - Probe uses Space::withTrashed() — soft-deleted and tombstoned slugs
 *    count as taken, so a slug can never be re-claimed.
 *
 * The form is the only place the human sees this logic; the unique index
 * is the only thing that can really block a save.
 */
class SlugService
{
    /**
     * Check whether the given slug is available right now and, if not,
     * propose the next -N suffix. Returns the canonical shape:
     *   ['available' => bool, 'suggested' => string|null]
     *
     * If the input is empty or unparseable, this returns available=false
     * with a fresh suggestion derived from the input (or a generic token).
     */
    public static function probe(string $raw): array
    {
        $slug = self::normalize($raw);

        if ($slug === '') {
            return ['available' => false, 'suggested' => self::suggestBase($raw)];
        }

        if (self::isReserved($slug)) {
            return ['available' => false, 'suggested' => self::nextSuggestion($slug)];
        }

        if (self::isTaken($slug)) {
            return ['available' => false, 'suggested' => self::nextSuggestion($slug)];
        }

        return ['available' => true, 'suggested' => null];
    }

    /**
     * Normalize a free-text slug candidate to the canonical form:
     *   - lowercase
     *   - ASCII alphanumerics + hyphens only (everything else stripped)
     *   - leading/trailing hyphens stripped
     *   - collapsed internal whitespace to single hyphen
     *   - trimmed to SLUG_MAX_LENGTH
     */
    public static function normalize(string $raw): string
    {
        $slug = Str::lower(trim($raw));
        $slug = preg_replace('/\s+/u', '-', $slug) ?? $slug;
        $slug = preg_replace('/[^a-z0-9-]/', '', $slug) ?? $slug;
        $slug = trim($slug, '-');
        $slug = substr($slug, 0, Space::SLUG_MAX_LENGTH);
        $slug = rtrim($slug, '-');

        return $slug;
    }

    /**
     * Auto-suggest a slug from a free-text title (PRD §5). Kebab-cases the
     * input via Str::slug (which calls the same kind of normalize).
     */
    public static function suggestFromTitle(string $title): string
    {
        $slug = Str::slug($title, '-');
        $slug = substr($slug, 0, Space::SLUG_MAX_LENGTH);
        $slug = rtrim($slug, '-');

        return $slug;
    }

    public static function isReserved(string $slug): bool
    {
        return in_array($slug, Space::RESERVED_SLUGS, true);
    }

    /**
     * A slug is "taken" if it is reserved OR a Space (live, soft-deleted,
     * or tombstoned) already claims it. The `withTrashed()` lookup is the
     * rule — see PRD §3.2 / build-order Step 2.
     */
    public static function isTaken(string $slug): bool
    {
        if ($slug === '') {
            return true;
        }

        if (self::isReserved($slug)) {
            return true;
        }

        return Space::withTrashed()->where('slug', $slug)->exists();
    }

    /**
     * Walk -2, -3, ... until we find a free slug. The base of the
     * returned slug is the input, trimmed so the "-N" suffix keeps the
     * result at or under SLUG_MAX_LENGTH.
     */
    public static function nextSuggestion(string $base): string
    {
        $base = self::normalize($base);
        if ($base === '') {
            $base = 'space';
        }

        for ($n = 2; $n < 1000; $n++) {
            $candidate = self::appendSuffix($base, (string) $n);

            if (! self::isTaken($candidate)) {
                return $candidate;
            }
        }

        // Fallback: append a random suffix if we walked 1000 numbers.
        return self::appendSuffix($base, Str::lower(Str::random(4)));
    }

    /**
     * Pick a base suggestion when the input slug is empty. Uses the raw
     * text (e.g. a user-typed title) and falls back to "space" if the
     * normalize stripped it all.
     */
    public static function suggestBase(string $raw): string
    {
        $base = self::normalize($raw);
        if ($base === '') {
            $base = 'space';
        }

        return self::nextSuggestion($base);
    }

    /**
     * Append a -N suffix to a slug, keeping the total length within
     * SLUG_MAX_LENGTH by trimming the base.
     */
    public static function appendSuffix(string $base, string $suffix): string
    {
        $sep = '-';
        $room = Space::SLUG_MAX_LENGTH - strlen($sep) - strlen($suffix);
        $trimmedBase = substr($base, 0, max(0, $room));
        $trimmedBase = rtrim($trimmedBase, '-');

        return $trimmedBase.$sep.$suffix;
    }
}
