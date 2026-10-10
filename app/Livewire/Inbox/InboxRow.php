<?php

namespace App\Livewire\Inbox;

use App\Models\DeletionRequest;
use App\Models\Space;
use App\Models\Testimonial;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * Single row of the Inbox / Trash.
 *
 * Holds the row's "edit mode" state for the Edit action (text + rating
 * only — no original kept, no "edited" marker anywhere). Hosts the
 * per-row action Livewire methods (Favorite, Wall-of-Love, Hide/Unhide,
 * Delete, Restore, Withdraw consent, Forget now).
 *
 * Hard rules honoured:
 *   - Hard Rule 6: NO physical file deletion on soft-delete. Forget-now
 *     is the only place that touches the public disk.
 *   - Hard Rule 7: Withdraw-consent is a Livewire method, NOT a POST
 *     route. Method sets consent_given=0 AND is_wall_of_love=0; the
 *     audit fields (consented_at, consent_text_version) are untouched.
 *   - Hard Rule 14: deletion_requests may be seeded with
 *     testimonial_id = null (matched by email).
 *   - Single-hop authorization on every action: $space->user_id ===
 *     Auth::id(). Cross-user calls return 404 (no info leak).
 */
class InboxRow extends Component
{
    #[Locked]
    public int $testimonialId;

    public ?Testimonial $testimonial = null;

    public ?Space $space = null;

    public bool $editing = false;

    public string $editTestimonial = '';

    public ?int $editRating = null;

    public ?string $flash = null;

    /**
     * Per-row affordance flags, set by the parent InboxIndex. The
     * parent only sets showRestore/showForget when the current filter
     * is 'trash', so action affordances are filter-scoped. Defaults
     * match the row view's fallbacks so the row still renders when
     * mounted standalone (e.g. in Step 4's cross-user auth tests).
     */
    public bool $showRestore = false;

    public bool $showForget = false;

    public bool $showSoftDelete = true;

    public function mount(int $testimonialId): void
    {
        $this->testimonialId = $testimonialId;
        $this->refresh();
    }

    /**
     * Reload the testimonial and its Space, then authorize. Called on
     * mount and after every mutation so the UI re-renders the latest
     * flags and the STORED `is_public` value.
     */
    protected function refresh(): void
    {
        $row = Testimonial::withTrashed()->find($this->testimonialId);
        if (! $row) {
            abort(404);
        }

        $space = $row->space;
        if (! $space) {
            abort(404);
        }

        $this->authorizeOwner($space);

        $this->testimonial = $row;
        $this->space = $space;
    }

    /**
     * Single-hop ownership check. 404 (not 403) on failure — never
     * reveal that another user's testimonial exists.
     */
    protected function authorizeOwner(Space $space): void
    {
        abort_unless(Auth::check(), 404);
        if ($space->user_id !== Auth::id()) {
            abort(404);
        }
    }

    public function render(): View
    {
        return view('livewire.inbox.inbox-row');
    }

    // -----------------------------------------------------------------
    // Toggles
    // -----------------------------------------------------------------

    public function toggleFavorite(): void
    {
        $this->refresh();
        $this->testimonial->is_favorite = ! $this->testimonial->is_favorite;
        $this->testimonial->save();
        $this->dispatch('inbox-row-updated', id: $this->testimonialId);
    }

    public function toggleHidden(): void
    {
        $this->refresh();
        $this->testimonial->is_hidden = ! $this->testimonial->is_hidden;
        $this->testimonial->save();
        $this->dispatch('inbox-row-updated', id: $this->testimonialId);
    }

    /**
     * Wall of Love toggle. Blocked while consent_given=0 — the
     * build order requires the block to apply to BULK actions as
     * well; the parent Inbox component enforces the same guard
     * before calling this for a bulk selection.
     */
    public function toggleWallOfLove(): void
    {
        $this->refresh();
        if (! $this->testimonial->consent_given) {
            $this->flash = 'This testimonial has no consent — Wall of Love is blocked.';

            return;
        }

        $this->testimonial->is_wall_of_love = ! $this->testimonial->is_wall_of_love;
        $this->testimonial->save();
        $this->dispatch('inbox-row-updated', id: $this->testimonialId);
    }

    // -----------------------------------------------------------------
    // Edit
    // -----------------------------------------------------------------

    public function startEdit(): void
    {
        $this->refresh();
        $this->editTestimonial = $this->testimonial->testimonial;
        $this->editRating = $this->testimonial->rating;
        $this->editing = true;
    }

    public function cancelEdit(): void
    {
        $this->editing = false;
        $this->editTestimonial = '';
        $this->editRating = null;
    }

    /**
     * Edit replaces text + rating. No original kept, no "edited" marker
     * anywhere (UI or DB). Re-strips zero-width chars and emoji-only
     * bodies the same way the public submission FormRequest does, so
     * the inbox cannot write junk that would fail Step 3 validation.
     */
    public function saveEdit(): void
    {
        $this->refresh();

        $body = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $this->editTestimonial) ?? '';
        $body = trim($body);
        $body = preg_replace('/\s+/u', ' ', $body) ?? $body;

        if ($body === '' || preg_match('/^[\p{Extended_Pictographic}\s]+$/u', $body)) {
            $this->flash = 'Testimonial text cannot be empty, whitespace-only, or emoji-only.';

            return;
        }

        if (mb_strlen($body) > 2000) {
            $this->flash = 'Testimonial is too long (max 2000 chars).';

            return;
        }

        $rating = $this->editRating;
        if ($this->space->rating_enabled) {
            if ($rating === null || $rating < 1 || $rating > 5) {
                $this->flash = 'Rating must be between 1 and 5.';

                return;
            }
        } else {
            $rating = null;
        }

        $this->testimonial->testimonial = $body;
        $this->testimonial->rating = $rating;
        $this->testimonial->save();

        $this->editing = false;
        $this->editTestimonial = '';
        $this->editRating = null;
        $this->flash = 'Saved.';
        $this->dispatch('inbox-row-updated', id: $this->testimonialId);
    }

    // -----------------------------------------------------------------
    // Soft delete + Restore
    // -----------------------------------------------------------------

    /**
     * Soft-delete. Sets deleted_at only — does NOT touch the photo file
     * (Hard Rule 6). Forget-now is the only way a file is removed.
     */
    public function softDelete(): void
    {
        $this->refresh();
        $this->testimonial->delete();
        $this->dispatch('inbox-row-updated', id: $this->testimonialId);
    }

    /**
     * Restore a soft-deleted row. Clear deleted_at; flags come back as
     * they were. Allowed even when the Space is at the testimonial cap
     * (the cap only blocks NEW public submissions, not the return of
     * existing data — Step 3 / Step 4 §6).
     */
    public function restore(): void
    {
        $this->refresh();
        if ($this->testimonial->deleted_at === null) {
            return;
        }

        $this->testimonial->restore();
        $this->dispatch('inbox-row-updated', id: $this->testimonialId);
    }

    // -----------------------------------------------------------------
    // Withdraw consent — Hard Rule 7
    // -----------------------------------------------------------------

    /**
     * Withdraw consent.
     *
     * HARD RULE 7: Livewire method, no POST route. Single-hop authorize
     * via $space->user_id === Auth::id() (caller of mount already
     * checked, but we re-check after refresh() so an attacker cannot
     * race the row to another owner).
     *
     * Sets consent_given=0 AND is_wall_of_love=0 in the same UPDATE.
     * Does NOT touch consented_at or consent_text_version — those are
     * audit history. The STORED is_public column recomputes
     * automatically; we never write to is_public directly.
     */
    public function withdrawConsent(): void
    {
        $this->refresh();

        $this->testimonial->consent_given = false;
        $this->testimonial->is_wall_of_love = false;
        $this->testimonial->save();

        $this->dispatch('inbox-row-updated', id: $this->testimonialId);
    }

    // -----------------------------------------------------------------
    // Forget now — Hard Rule 6
    // -----------------------------------------------------------------

    /**
     * Forget-now. Hard Rule 6, three-step order, fixed:
     *
     *   1. UPDATE matching `deletion_requests` to `acted` / `acted_at=now`
     *      FIRST, so the testimonial_id FK is still populated.
     *   2. Delete the photo file from the `public` disk. Ignore ONLY
     *      "file not found"; any other error aborts and leaves the row
     *      intact for the next attempt.
     *   3. $this->testimonial->forceDelete() LAST. forceDelete() flips
     *      the testimonial_id FK on any remaining deletion_requests
     *      row to null via the nullOnDelete FK — so Step 1's match-by-
     *      id branch would be empty after forceDelete. That is why the
     *      order is fixed and not "for simplicity" reordered.
     */
    public function forget(): void
    {
        $this->refresh();

        // Forget is a Trash-only action. If the row is still live
        // (deleted_at IS NULL), refuse — the owner must softDelete
        // first. This mirrors the build order (Step 4) and the PRD
        // §7 "Forget now (Trash only)" rule.
        if ($this->testimonial->deleted_at === null) {
            $this->flash = 'Forget now is only available on trashed testimonials.';

            return;
        }

        $t = $this->testimonial;
        $spaceId = (int) $this->space->id;
        $testimonialId = (int) $t->id;
        $email = (string) $t->email;

        // Step 1 — close matching open deletion_requests.
        DB::table('deletion_requests')
            ->where('space_id', $spaceId)
            ->where('status', DeletionRequest::STATUS_OPEN)
            ->where(function ($q) use ($testimonialId, $email) {
                $q->where('testimonial_id', $testimonialId)
                    ->orWhere(function ($q2) use ($email) {
                        $q2->whereNull('testimonial_id')->where('email', $email);
                    });
            })
            ->update([
                'status' => DeletionRequest::STATUS_ACTED,
                'acted_at' => now(),
            ]);

        // Step 2 — delete photo file from public disk.
        $photo = $t->profile_photo;
        if ($photo) {
            try {
                Storage::disk('public')->delete($photo);
            } catch (Throwable $e) {
                // Real error (not "file not found") — leave the row
                // intact for the next run / retry. The forget call
                // fails; the UI re-renders the trashed row.
                $this->flash = 'Could not delete photo: '.$e->getMessage();

                return;
            }
        }

        // Step 3 — force-delete. nullOnDelete on deletion_requests
        // .testimonial_id will null the FK on any still-open row.
        $t->forceDelete();

        $this->dispatch('inbox-row-forgotten', id: $testimonialId);
    }
}
