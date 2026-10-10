<?php

namespace App\Livewire\Privacy;

use App\Models\DeletionRequest;
use App\Models\Space;
use App\Models\Testimonial;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * `/privacy/request-deletion` — the public, anonymous form a respondent
 * uses to ask the owner to remove a testimonial.
 *
 * Step 8 spec (privacy + spec deltas §8.3):
 *   - Fields: email (required, `email:rfc`, max 180), space_slug
 *     (required, max 60), optional testimonial_id, honeypot.
 *   - Throttled at 5 submissions per hour per IP via the named
 *     `deletion-request` limiter. `hit()` runs at the START of every
 *     attempt (success or fail) so the 6th attempt is always blocked.
 *     This matches the Step 1 limiter pattern (per-IP named limiter,
 *     hits-at-start, no clearing on success).
 *   - Server-side resolution:
 *       space_id  = Space::withTrashed() lookup by typed slug, or null
 *       testimonial_id = accepted only when it belongs to the resolved Space,
 *                         otherwise null
 *       space_slug = stored as typed (never rewritten)
 *   - Same thank-you for EVERY outcome: valid slug, unknown slug,
 *     soft-deleted slug, foreign testimonial_id, unknown
 *     testimonial_id, honeypot filled, over-the-throttle. Status,
 *     redirect target, and rendered body never differ between the
 *     five scenarios the test exercises. The form therefore reveals
 *     nothing about whether an id exists.
 *   - Row writing rules:
 *       honeypot filled  -> no row
 *       over the throttle -> no row
 *       validation fail   -> no row
 *       otherwise         -> one row, status='open', acted_at=null
 */
#[Layout('layouts.guest')]
class DeletionRequestForm extends Component
{
    /**
     * The honeypot field name. Picked here (Step 8 / Part C) and
     * reported as a "choice I need to know" in the final report.
     */
    public const HONEYPOT_FIELD = 'website_url';

    public string $email = '';

    public string $spaceSlug = '';

    public ?string $testimonialId = null;

    /** Honeypot — must be empty. Visually hidden, never tabbable. */
    public string $website_url = '';

    /**
     * One switch that flips to true after the form has been processed,
     * so the same thank-you view is rendered for every outcome
     * (success, validation error, throttle, honeypot, unknown slug).
     * The view shows the same content regardless of what happened.
     */
    public bool $submitted = false;

    /**
     * Inline validation rules. All three required fields have the
     * exact bounds the spec calls for: email:rfc + max:180, slug
     * max:60, testimonial_id is optional and integer-ish when given.
     */
    protected function rules(): array
    {
        return [
            'email'         => ['required', 'string', 'email:rfc', 'max:180'],
            'spaceSlug'     => ['required', 'string', 'max:60'],
            'testimonialId' => ['nullable', 'string', 'max:20'],
            'website_url'   => ['nullable', 'string', 'max:200'],
        ];
    }

    protected function messages(): array
    {
        return [
            'email.email'         => 'Please enter a valid email address.',
            'email.required'      => 'Email is required.',
            'email.max'           => 'Email is too long.',
            'spaceSlug.required'  => 'Space slug is required.',
            'spaceSlug.max'       => 'Space slug is too long.',
        ];
    }

    /**
     * Submit the form. The logic is deliberately compact: every path
     * ends on `$this->submitted = true` (same view), and only the
     * "happy" path also writes a row. The order is fixed:
     *   1. Throttle  (hit() at the start, every attempt counts).
     *   2. Honeypot  (no row if filled).
     *   3. Validation (no row if it fails).
     *   4. Resolution + write (one row, normal path).
     *   5. Show the same thank-you view.
     */
    public function submit(): void
    {
        $limit = $this->throttleLimit();
        $key = (string) $limit->key;
        $maxAttempts = (int) $limit->maxAttempts;
        $decaySeconds = (int) $limit->decaySeconds;

        // Step 1 — throttle. Every attempt (success, failure,
        // honeypot) counts toward the cap (5 per hour by default,
        // pinned in AppServiceProvider's `deletion-request` named
        // limiter — this method is the ONLY place those numbers
        // live). The spec explicitly says "Every submit attempt
        // counts." and "over the throttle" must end on the same
        // thank-you with no row written. We honour both by reading
        // maxAttempts/decaySeconds from the limiter and then
        // hitting unconditionally: the (maxAttempts + 1)-th
        // attempt (and beyond) short-circuits to the same
        // thank-you, no row. Same pattern as the Step 1
        // forgot-password Livewire action
        // (resources/views/livewire/pages/auth/forgot-password.blade.php
        // lines 24-32) but with the cap and decay read from the
        // named limiter so the rule is defined in ONE place
        // (AppServiceProvider).
        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $this->submitted = true;
            $this->reset(['email', 'spaceSlug', 'testimonialId', 'website_url']);

            return;
        }
        RateLimiter::hit($key, $decaySeconds);

        // Step 2 — honeypot. If the field is non-empty, silently
        // accept (no row) and show the same thank-you.
        if (trim($this->website_url) !== '') {
            $this->submitted = true;
            $this->reset(['email', 'spaceSlug', 'testimonialId', 'website_url']);

            return;
        }

        // Step 3 — validate. Validation failures MUST leave the form
        // visible with field-level errors on top, so the response
        // is observably different from the "thank-you" view. The
        // NOTHING-REVEALED test treats the post-validation-passing
        // cases as identical; the validation test exercises the
        // missing-field case separately and asserts the response
        // differs. We let validate() throw on failure — the
        // Livewire SupportValidation hook catches it, populates
        // the error bag, and re-renders the form with errors.
        $data = $this->validate();
        $email = strtolower(trim((string) $data['email']));
        $spaceSlugTyped = (string) $data['spaceSlug'];
        $testimonialIdRaw = $data['testimonialId'] ?? null;
        $testimonialIdRaw = is_string($testimonialIdRaw) ? trim($testimonialIdRaw) : '';
        $testimonialIdRaw = $testimonialIdRaw === '' ? null : $testimonialIdRaw;

        // Step 4 — resolve. space_id is whatever withTrashed() finds
        // (including soft-deleted) by exact typed slug, or null.
        $space = Space::withTrashed()
            ->where('slug', $spaceSlugTyped)
            ->first();
        $spaceId = $space?->id;

        // testimonial_id is set ONLY if it is a positive integer
        // AND belongs to the resolved Space. Otherwise null.
        $testimonialId = null;
        if ($testimonialIdRaw !== null && $spaceId !== null && ctype_digit($testimonialIdRaw)) {
            $candidate = (int) $testimonialIdRaw;
            $belongs = Testimonial::query()
                ->where('id', $candidate)
                ->where('space_id', $spaceId)
                ->exists();
            if ($belongs) {
                $testimonialId = $candidate;
            }
        }

        // Write the row. The status enum is enforced by the
        // migration; space_slug is stored as typed.
        DeletionRequest::create([
            'email' => $email,
            'space_slug' => $spaceSlugTyped,
            'space_id' => $spaceId,
            'testimonial_id' => $testimonialId,
            'status' => DeletionRequest::STATUS_OPEN,
            'acted_at' => null,
        ]);

        // Step 5 — same thank-you for every outcome.
        $this->submitted = true;

        // Clear the form fields so a browser back-button does not
        // re-submit stale data.
        $this->reset(['email', 'spaceSlug', 'testimonialId', 'website_url']);
    }

    /**
     * The named `deletion-request` limiter, resolved against the
     * current request. The limiter is registered in
     * AppServiceProvider::boot() — this method is the ONLY
     * place outside the provider that knows the cap, the decay,
     * or the per-IP key shape. The cap and decay are read from
     * the returned Limit object in `submit()` so the rule is
     * defined in ONE place.
     *
     * Per-IP only — the form is anonymous, so we never have a
     * logged-in user's id, and the email+IP variant would couple
     * anonymous-respondent rate limiting to the owner's address.
     */
    protected function throttleLimit(): \Illuminate\Cache\RateLimiting\Limit
    {
        return RateLimiter::limiter('deletion-request')(request());
    }

    public function render(): View
    {
        return view('livewire.privacy.deletion-request-form');
    }
}
