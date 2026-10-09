<?php

namespace App\Livewire;

use App\Exceptions\PhotoRejectedException;
use App\Exceptions\SubmissionThrottledException;
use App\Models\Space;
use App\Support\SubmissionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * /s/{slug} — the public, anonymous submission page.
 *
 * Binds to the Breeze `guest` layout (the auth layout would 404 for
 * guests; the guest layout has the responsive viewport meta tag and
 * the same font / stylesheet as the rest of the app).
 *
 * The component is intentionally thin. All pipeline work — validation,
 * honeypot, dedupe, lock, throttle, photo, insert, rollback — lives
 * in `App\Support\SubmissionService`. That separation makes the
 * pipeline unit-testable and keeps the Livewire surface small.
 */
#[Layout('layouts.guest')]
class PublicSubmission extends Component
{
    use WithFileUploads;

    #[Locked]
    public string $slug;

    public ?Space $space = null;

    public bool $submitted = false;

    public ?string $thanksMessage = null;

    public ?string $throttledMessage = null;

    public ?string $photoErrorMessage = null;

    public bool $locked = false;

    // Form fields. Validation is owned by SubmissionService (see the
    // FormRequest for the canonical rule list). Inline `#[Validate]`
    // attributes are intentionally NOT used here so the Livewire
    // test helper can `->set()` any value without the inline validator
    // pre-failing the test.
    public string $name = '';

    public string $email = '';

    public string $address = '';

    public string $testimonial = '';

    public bool $consentGiven = false;

    public ?int $rating = null;

    public ?string $companyName = null;

    public ?string $socialUrl = null;

    public $profilePhoto = null; // UploadedFile

    public string $website = ''; // honeypot

    public function mount(string $slug, Request $request): void
    {
        $this->slug = $slug;

        $space = Space::where('slug', $slug)->live()->first();
        if (! $space) {
            // 404 for unknown, soft-deleted, and tombstoned (soft-deleted
            // past retention) — all three are absent from `live()`.
            abort(404);
        }

        $this->space = $space;
        $this->locked = $this->isLocked($space);
    }

    public function submit(SubmissionService $service, Request $request): void
    {
        $this->photoErrorMessage = null;
        $this->throttledMessage = null;

        // collect Livewire file input into the form-data input
        // (camelCase to match the validator's rule keys)
        $payload = [
            'name' => $this->name,
            'email' => $this->email,
            'address' => $this->address,
            'testimonial' => $this->testimonial,
            'consentGiven' => $this->consentGiven,
            'rating' => $this->rating,
            'companyName' => $this->companyName,
            'socialUrl' => $this->socialUrl,
            'profilePhoto' => $this->profilePhoto,
            'website' => $this->website,
        ];

        try {
            $result = $service->store($this->space, $payload, $request);
        } catch (SubmissionThrottledException $e) {
            $this->throttledMessage = $e->getMessage();

            return;
        } catch (PhotoRejectedException $e) {
            $this->photoErrorMessage = $e->getMessage();

            return;
        }

        if (! $result['ok']) {
            // Validation / dedupe / lock / honeypot — they all share the
            // silent thank-you page UX, except `invalid` which surfaces
            // the validator's error bag.
            if ($result['reason'] === 'invalid') {
                // Re-throw as a ValidationException so Livewire highlights
                // the failing fields. We do this by manually adding
                // errors to the bag, which is what Livewire's
                // `validate()` does under the hood.
                foreach ($result['errors']->messages() as $key => $messages) {
                    foreach ((array) $messages as $msg) {
                        $this->addError($key, $msg);
                    }
                }

                return;
            }

            // duplicate / locked / honeypot — show the thank-you page
            $this->thanksMessage = (string) ($result['thanks'] ?? '');
            $this->submitted = true;

            return;
        }

        // ok
        $this->thanksMessage = (string) ($result['thanks'] ?? '');
        $this->submitted = true;

        // Reset volatile form state for visual continuity on a refresh.
        $this->reset(['name', 'email', 'address', 'testimonial', 'rating', 'companyName', 'socialUrl', 'profilePhoto', 'website']);
        $this->consentGiven = false;
    }

    private function isLocked(Space $space): bool
    {
        $cap = (int) config('limits.max_testimonials_per_space');

        return $space->testimonials()->count() >= $cap;
    }

    public function render(): View
    {
        return view('livewire.public-submission');
    }
}
