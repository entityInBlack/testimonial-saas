<?php

namespace App\Policies;

use App\Models\Space;
use App\Models\User;

/**
 * Authorization for Space management.
 *
 * A Space can be created by any authenticated user (subject to the cap
 * check inside SpaceForm::save() — that's a business limit, not a
 * policy). Once a Space exists, every management action checks that the
 * Space belongs to the authenticated user.
 *
 * Build-order Step 2 / PRD §5: "User B must get 403 or 404 on user A's
 * Spaces (edit, update, delete, restore)."
 *
 * The 404 path is taken when a user is signed in but the Space doesn't
 * belong to them — we don't reveal that a Space with that id exists. The
 * 403 path is for authenticated users whose session is fine but the action
 * is denied. For Space management, the deny always reads as a 404 because
 * leaking the existence of someone else's Space is a small information
 * disclosure.
 */
class SpacePolicy
{
    /**
     * Whether the user can view this Space in the management UI
     * (edit form, dashboard list, etc.). Soft-deleted Spaces are
     * NOT viewable here — they go through the Deleted tab instead.
     */
    public function view(User $user, Space $space): bool
    {
        return $space->user_id === $user->id && $space->deleted_at === null;
    }

    /**
     * Whether the user can edit this Space. Same rules as `view` —
     * the Space must be theirs and must not be soft-deleted.
     */
    public function update(User $user, Space $space): bool
    {
        return $space->user_id === $user->id && $space->deleted_at === null;
    }

    /**
     * Whether the user can soft-delete this Space. Soft-deleted Spaces
     * cannot be re-soft-deleted.
     */
    public function delete(User $user, Space $space): bool
    {
        return $space->user_id === $user->id && $space->deleted_at === null;
    }

    /**
     * Whether the user can restore this soft-deleted Space. The cap
     * check (is the owner at `max_spaces` already?) is a BUSINESS
     * check, not a policy check — it lives in the restore action and
     * returns a notice, not a 403.
     */
    public function restore(User $user, Space $space): bool
    {
        return $space->user_id === $user->id && $space->deleted_at !== null;
    }
}
