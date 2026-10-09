<?php

namespace App\Livewire\Spaces;

use App\Models\Space;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * /spaces — the owner's list of LIVE Spaces.
 *
 * Step 2 scope: list, edit (link), soft-delete, and a "Deleted Spaces"
 * tab link. No counters, no testimonials, no embed builder — those
 * land in Steps 4 and 5.
 *
 * Soft-deleted Spaces are NOT listed here. They appear on the
 * `SpaceDeleted` component (Deleted tab).
 */
#[Layout('layouts.app')]
class SpaceIndex extends Component
{
    public function render(): View
    {
        return view('livewire.spaces.space-index');
    }

    /**
     * Soft-delete handler. The form is a one-click confirm — the Blade
     * shows a confirm() dialog. Authorization: only the owner can
     * delete, and only LIVE Spaces can be deleted (not already-soft-
     * deleted rows).
     */
    public function delete(int $spaceId): void
    {
        $space = Space::find($spaceId);

        if (! $space) {
            abort(404);
        }

        if ($space->user_id !== Auth::id()) {
            abort(404);
        }

        if ($space->deleted_at !== null) {
            abort(404);
        }

        $space->delete(); // soft delete (SoftDeletes trait)

        session()->flash('status', 'Space moved to Deleted.');
        $this->redirectRoute('spaces.index', navigate: true);
    }

    /**
     * LIVE Spaces for the current owner. Soft-deleted Spaces are
     * excluded via the `live()` scope (PRD §11).
     */
    #[Computed]
    public function spaces()
    {
        return Space::live()
            ->where('user_id', Auth::id())
            ->orderByDesc('updated_at')
            ->get();
    }

    #[Computed]
    public function liveSpaceCount(): int
    {
        return $this->spaces->count();
    }

    #[Computed]
    public function maxSpaces(): int
    {
        return (int) config('limits.max_spaces', 3);
    }

    /**
     * 80% warning — same rule as the form. With max=3 the banner fires
     * whenever the owner is at the cap (which is the only >=80% state
     * in v1).
     */
    #[Computed]
    public function showWarningBanner(): bool
    {
        $max = (int) config('limits.max_spaces', 3);
        $threshold = (int) ceil($max * 0.8);

        return $this->liveSpaceCount >= $threshold;
    }

    /**
     * "At cap" state — used by the index to hide the New Space link.
     * Computed from LIVE Spaces only (PRD §11).
     */
    #[Computed]
    public function isAtCap(): bool
    {
        $max = (int) config('limits.max_spaces', 3);

        return $this->liveSpaceCount >= $max;
    }
}
