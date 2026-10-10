<?php

namespace App\Livewire\Spaces;

use App\Models\Space;
use App\Services\SpaceRestoreService;
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
 *
 * The Restore action delegates to `SpaceRestoreService` — the SAME
 * service the dashboard's Deleted Spaces tab uses, so the cap check,
 * tombstone check, and owner authorization are defined in ONE place.
 * After a successful restore, this page redirects back to itself so
 * the post-restore flash survives a page reload.
 */
#[Layout('layouts.app')]
class SpaceDeleted extends Component
{
    public function render(): View
    {
        return view('livewire.spaces.space-deleted');
    }

    /**
     * Restore a soft-deleted Space. The actual cap / tombstone /
     * authorization logic lives in `SpaceRestoreService`. This method
     * is just a thin Livewire adapter.
     */
    public function restore(int $spaceId, SpaceRestoreService $service): void
    {
        $result = $service->restore($spaceId);

        if ($result->isNotFound()) {
            abort(404);
        }

        if ($result->isTombstoned()) {
            $this->addError('restore', 'This Space is past the restore window and cannot be restored.');

            return;
        }

        if ($result->isCapReached()) {
            $this->addError('restore', "You're at the Free plan limit of {$result->cap} Spaces. Delete a Space first.");

            return;
        }

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
