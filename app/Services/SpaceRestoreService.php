<?php

namespace App\Services;

use App\Models\Space;
use Illuminate\Support\Facades\Auth;

/**
 * Single source of truth for restoring a soft-deleted Space.
 *
 * Used by BOTH the /spaces/deleted page and the dashboard's
 * Deleted Spaces tab. The cap check, tombstone check, and owner
 * authorization are enforced in ONE place; each caller decides
 * its own post-restore UX (redirect or stay).
 */
class SpaceRestoreService
{
    /**
     * Attempt to restore a soft-deleted Space. Returns a
     * `RestoreResult` describing the outcome so the caller can
     * decide what to do (set an error, flash a status, redirect,
     * or just stay put).
     */
    public function restore(int $spaceId): RestoreResult
    {
        $space = Space::withTrashed()->find($spaceId);

        // Auth + existence + soft-delete-state check.
        if (! $space || $space->user_id !== Auth::id() || $space->deleted_at === null) {
            return RestoreResult::notFound();
        }

        // Tombstoned Spaces cannot be restored.
        if ($space->isTombstoned()) {
            return RestoreResult::tombstoned();
        }

        // Cap check — restore blocked if owner is already at max.
        $max = (int) config('limits.max_spaces', 3);
        $live = Space::live()->where('user_id', Auth::id())->count();

        if ($live >= $max) {
            return RestoreResult::capReached($max);
        }

        $space->restore();

        return RestoreResult::restored($space);
    }
}
