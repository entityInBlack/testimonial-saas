<?php

namespace App\Livewire\Spaces;

use App\Models\Space;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * /spaces/deleted — the Deleted Spaces tab (PRD §11 / build-order
 * Step 2). Lists the owner's Spaces soft-deleted within
 * `config('purge.retention_days')`. Offers Restore. Restore is blocked
 * if the owner is already at the cap (shows the "delete a Space first"
 * notice). Tombstoned Spaces (older than retention) are NOT listed.
 *
 * Soft-delete rule (Hard Rule 6 / PRD §3.2):
 *   - Set `deleted_at`. Do NOT touch photo files. Do NOT touch the
 *     `embed_configurations` row. Do NOT touch testimonials. Only the
 *     purge job (Step 6) physically deletes files / rows.
 *   - The slug stays claimed forever.
 */
#[Layout('layouts.app')]
class SpaceDeleted extends Component
{
    public function render(): View
    {
        return view('livewire.spaces.space-deleted');
    }

    /**
     * Restore a soft-deleted Space. Authorization: must be the owner.
     * The cap check is a business rule, not a policy — if the owner is
     * already at `max_spaces` LIVE Spaces, the restore sets a flash
     * notice and the row is NOT cleared.
     */
    public function restore(int $spaceId): void
    {
        $space = Space::withTrashed()->find($spaceId);

        if (! $space || $space->user_id !== Auth::id() || $space->deleted_at === null) {
            abort(404);
        }

        // Tombstoned Spaces cannot be restored (PRD §11).
        if ($space->isTombstoned()) {
            $this->addError('restore', 'This Space is past the restore window and cannot be restored.');

            return;
        }

        // Cap check — restore blocked if owner is already at max.
        $max = (int) config('limits.max_spaces', 3);
        $live = Space::live()->where('user_id', Auth::id())->count();

        if ($live >= $max) {
            $this->addError('restore', "You're at the Free plan limit of {$max} Spaces. Delete a Space first.");

            return;
        }

        $space->restore();

        session()->flash('status', 'Space restored.');
        $this->redirectRoute('spaces.deleted', navigate: true);
    }

    /**
     * Soft-deleted Spaces for the current owner, within the retention
     * window. Tombstoned Spaces are excluded via `recentlyDeleted()`.
     */
    #[Computed]
    public function spaces()
    {
        return Space::recentlyDeleted()
            ->where('user_id', Auth::id())
            ->orderByDesc('deleted_at')
            ->get();
    }

    #[Computed]
    public function liveSpaceCount(): int
    {
        return Space::live()->where('user_id', Auth::id())->count();
    }

    #[Computed]
    public function maxSpaces(): int
    {
        return (int) config('limits.max_spaces', 3);
    }

    #[Computed]
    public function retentionDays(): int
    {
        return (int) config('purge.retention_days', 30);
    }
}
