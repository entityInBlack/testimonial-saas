<?php

namespace App\Livewire\Inbox;

use App\Models\Space;
use App\Models\Testimonial;
use Illuminate\Contracts\View\View;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * /inbox — the owner's per-Space list of testimonials.
 *
 * Step 4 scope:
 *   - Space dropdown (LIVE Spaces only; soft-deleted excluded)
 *   - Filters: All / Favorites / Wall of Love / Hidden / Trash
 *   - Default sort: is_favorite DESC, submitted_at DESC (firmware rule
 *     shared with the embed ordering — see Step 7 / PRD §7)
 *   - Hidden items stay in the inbox list, flagged; they are NOT
 *     removed from view.
 *   - Trash is its own tab/filter; soft-deleted rows are listed there
 *     and never in the main filters.
 *   - Paginated 20 per page.
 *   - Per-row actions are delegated to the InboxRow child component
 *     (toggle Favorite, Wall-of-Love, Hide/Unhide, Edit, Withdraw,
 *     soft-Delete, Restore, Forget now). Cross-user authorization
 *     lives there.
 *
 * Spec / build order:
 *   - PRD §7 / data-model §3.3 — sort and visibility rules.
 *   - Build order Step 4 — Inbox + Trash + Forget + Space Restore +
 *     WoL guard.
 *   - Hard Rule 6: no physical file deletion on soft-delete; the
 *     forget() method on InboxRow is the only place that touches
 *     public disk files.
 *   - Hard Rule 7: withdraw-consent is the InboxRow::withdrawConsent
 *     Livewire method, no POST route.
 */
#[Layout('layouts.app')]
class InboxIndex extends Component
{
    use WithPagination;

    /**
     * Selected Space id. `null` means "no Space selected" — the page
     * shows the empty Spaces message. Stored in the URL via `#[Url]`
     * so refresh and back-button preserve the selection.
     */
    #[Url]
    public ?int $spaceId = null;

    /**
     * Filter key. Allowed values: 'all', 'favorites', 'wall', 'hidden',
     * 'trash'. Anything else is treated as 'all' to be safe (a hostile
     * URL cannot crash the page).
     */
    #[Url]
    public string $filter = 'all';

    public const FILTERS = ['all', 'favorites', 'wall', 'hidden', 'trash'];

    /**
     * Listeners: the InboxRow child dispatches these after every
     * mutation so we re-render the list with the latest data (and the
     * STORED is_public value).
     */
    #[On('inbox-row-updated')]
    public function onRowUpdated(): void
    {
        // no-op: just triggers a re-render
    }

    #[On('inbox-row-forgotten')]
    public function onRowForgotten(int $id): void
    {
        // The row is hard-deleted; if it is currently in the paged
        // result set, refresh will drop it. Nothing else to do.
    }

    public function mount(): void
    {
        // Sanitize the filter on every request — a stale URL could
        // carry a value the component doesn't recognize.
        if (! in_array($this->filter, self::FILTERS, true)) {
            $this->filter = 'all';
        }

        // If the URL carries a spaceId that does not exist or is not
        // the current owner's, drop it. Defensive: the page should
        // also 404 in that case but we keep the UX soft here.
        if ($this->spaceId !== null) {
            $exists = Space::live()
                ->where('user_id', Auth::id())
                ->whereKey($this->spaceId)
                ->exists();
            if (! $exists) {
                $this->spaceId = null;
            }
        }
    }

    public function updatedFilter(): void
    {
        if (! in_array($this->filter, self::FILTERS, true)) {
            $this->filter = 'all';
        }
        $this->resetPage();
    }

    public function updatedSpaceId(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        return view('livewire.inbox.inbox-index');
    }

    // -----------------------------------------------------------------
    // Computed
    // -----------------------------------------------------------------

    /**
     * LIVE Spaces owned by the current user. Soft-deleted Spaces are
     * excluded via the `live()` scope (PRD §11).
     */
    #[Computed]
    public function spaces()
    {
        return Space::live()
            ->where('user_id', Auth::id())
            ->orderBy('title')
            ->get();
    }

    #[Computed]
    public function selectedSpace(): ?Space
    {
        if ($this->spaceId === null) {
            return null;
        }

        return Space::live()
            ->where('user_id', Auth::id())
            ->whereKey($this->spaceId)
            ->first();
    }

    /**
     * The paged result set for the current Space + filter.
     *
     * Sort: is_favorite DESC, submitted_at DESC. The build order says
     * this is the firmware rule shared with the embed — do not change
     * one without the other. (See Step 7.)
     *
     * Trash is its own dataset: only soft-deleted rows, the same sort.
     * All other filters are LIVE rows (deleted_at IS NULL) and may
     * apply the optional predicate.
     */
    #[Computed]
    public function testimonials(): LengthAwarePaginator
    {
        $space = $this->selectedSpace;
        if (! $space) {
            // Empty paged result.
            return Testimonial::query()
                ->whereRaw('1 = 0')
                ->paginate(20);
        }

        $q = Testimonial::query()
            ->where('space_id', $space->id);

        if ($this->filter === 'trash') {
            $q->onlyTrashed();
        } else {
            // Live filters: soft-deleted rows are hidden from the
            // main list. They live in the Trash filter only.
            $q->whereNull('deleted_at');

            match ($this->filter) {
                'favorites' => $q->where('is_favorite', true),
                'wall'      => $q->where('is_wall_of_love', true),
                'hidden'    => $q->where('is_hidden', true),
                default     => null, // 'all' — no predicate
            };
        }

        return $q->orderByDesc('is_favorite')
            ->orderByDesc('submitted_at')
            ->orderByDesc('id') // tie-breaker for tests with the same submitted_at
            ->paginate(20);
    }

    /**
     * Counts for the filter chips. Trash is excluded from these
     * because the chip shows live counts.
     */
    #[Computed]
    public function counts(): array
    {
        $space = $this->selectedSpace;
        if (! $space) {
            return ['all' => 0, 'favorites' => 0, 'wall' => 0, 'hidden' => 0, 'trash' => 0];
        }

        $base = Testimonial::query()->where('space_id', $space->id);

        return [
            'all'       => (clone $base)->whereNull('deleted_at')->count(),
            'favorites' => (clone $base)->whereNull('deleted_at')->where('is_favorite', true)->count(),
            'wall'      => (clone $base)->whereNull('deleted_at')->where('is_wall_of_love', true)->count(),
            'hidden'    => (clone $base)->whereNull('deleted_at')->where('is_hidden', true)->count(),
            'trash'     => (clone $base)->onlyTrashed()->count(),
        ];
    }

    // -----------------------------------------------------------------
    // Bulk Wall of Love — guarded (Step 4.2 spec)
    // -----------------------------------------------------------------

    /**
     * Apply Wall of Love to a set of testimonial ids. Blocked if ANY
     * row in the selection has consent_given = 0 — the build order
     * requires the whole action to be blocked (not silently skipped)
     * so the owner notices the unconsented row.
     */
    public function bulkWallOfLove(array $ids): void
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (empty($ids)) {
            return;
        }

        $rows = Testimonial::query()
            ->whereIn('id', $ids)
            ->whereHas('space', fn ($q) => $q->where('user_id', Auth::id()))
            ->get();

        if ($rows->where('consent_given', false)->isNotEmpty()) {
            session()->flash('inbox_error', 'Wall of Love is blocked — selection includes a row without consent.');

            return;
        }

        Testimonial::query()
            ->whereIn('id', $rows->pluck('id')->all())
            ->update(['is_wall_of_love' => true]);

        $this->dispatch('inbox-row-updated');
    }
}
