<?php

namespace App\Livewire\Spaces;

use App\Models\Space;
use App\Support\SlugService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * /spaces/new and /spaces/{space}/edit
 *
 * Single Livewire form component for both create and edit. When
 * `$spaceId` is null we render the create form; when it is set we
 * load an existing Space, authorize via the policy, and save updates.
 *
 * The form surfaces:
 *   - name (max 120, defaults to title)
 *   - slug (max 60, auto-suggested from title via the kebab-case rule,
 *     editable; on collision the form probes /spaces/check-slug)
 *   - title, subtitle, ask, rating_enabled, theme
 *   - field_config: {enabled, required} for company_name, social_url,
 *     profile_photo. The locked fields (name, email, address) are NOT
 *     in field_config — they live on the testimonials table.
 *
 * Hard rules honoured:
 *   - PRD §11: cap of `config('limits.max_spaces')` live Spaces per
 *     owner. Soft-deleted Spaces do not count. The block message is
 *     "Delete a Space or contact support." — no upgrade CTA in v1.
 *   - PRD §11: warning banner at `>= 80%` of the cap. With
 *     `max_spaces=3`, `ceil(3 * 0.8) = 3`, so the warning fires at 3
 *     (which is the same as the cap in v1).
 *   - PRD §3.2: public_id is generated on insert and is immutable.
 *   - Build-order Step 2: slug is auto-suggested from the title
 *     (kebab-case) and trimmed to 60 chars on any -N suffix.
 */
#[Layout('layouts.app')]
class SpaceForm extends Component
{
    /** When set we are editing; null means "create". */
    #[Locked]
    public ?int $spaceId = null;

    // ---- form fields ----
    public string $name = '';
    public string $slug = '';
    public string $title = '';
    public ?string $subtitle = null;
    public string $ask = '';
    public bool $ratingEnabled = true;
    public string $theme = 'minimal_light';

    /** Two-key shape: field => {enabled, required} */
    public array $fieldConfig = [];

    /**
     * Title-bound suggestion hint shown above the slug field. The user
     * can accept it or type their own.
     */
    public string $slugHint = '';

    /**
     * Soft UI state from the check-slug probe. The form re-runs the
     * probe when the slug field loses focus (wire:blur) or when the
     * title changes (the suggestion updates).
     */
    public ?bool $slugAvailable = null;
    public ?string $slugSuggestion = null;

    public function mount(int|Space|null $space = null): void
    {
        if ($space !== null) {
            // Livewire 3 resolves `{space}` to a Space model via
            // ImplicitRouteBinding. Tests (and the `whereNumber` route
            // constraint) may pass an int id. Handle both.
            $model = $space instanceof Space ? $space : Space::find($space);

            if (! $model) {
                abort(404);
            }

            $this->authorizeEdit($model);
            $this->spaceId = $model->id;
            $this->fillFromModel($model);

            return;
        }

        $this->authorizeCreate();
        $this->fieldConfig = Space::defaultFieldConfig();
    }

    public function render(): View
    {
        return view('livewire.spaces.space-form');
    }

    /**
     * Auto-fill the slug from the title (PRD §5). The user can still
     * type a different slug — this only updates the hint.
     */
    public function updatedTitle(string $value): void
    {
        // Only auto-fill if the user has not typed in the slug field.
        if ($this->slug === '' || $this->slug === $this->slugHint) {
            $this->slugHint = SlugService::suggestFromTitle($value);
            $this->slug = $this->slugHint;
            $this->probeSlug();
        }
    }

    /**
     * Re-run the slug probe when the slug field changes or loses focus.
     * Uses the in-process SlugService directly so the test suite can
     * drive the form without HTTP. The HTTP endpoint at
     * /spaces/check-slug exercises the same code path.
     */
    public function probeSlug(): void
    {
        $result = SlugService::probe($this->slug);
        $this->slugAvailable = $result['available'];
        $this->slugSuggestion = $result['suggested'];
    }

    /**
     * Apply the suggested slug (if any) into the form field.
     */
    public function applySuggestion(): void
    {
        if ($this->slugSuggestion) {
            $this->slug = $this->slugSuggestion;
            $this->probeSlug();
        }
    }

    /**
     * Create or update the Space. Returns void; on success the
     * component redirects to the success page (create) or the index
     * (update). On failure it sets `$errorBanner` so the Blade can
     * render a notice (cap, validation, etc.).
     */
    public function save(): void
    {
        $data = $this->validate($this->rules());

        // Slug collision guard. The DB unique index is the source of
        // truth — this probe just keeps the form honest. The model
        // `creating` event generates public_id; we don't set it here.
        $normalizedSlug = SlugService::normalize($data['slug']);
        $exists = Space::withTrashed()
            ->where('slug', $normalizedSlug)
            ->when($this->spaceId, fn ($q) => $q->where('id', '!=', $this->spaceId))
            ->exists();

        if ($exists || in_array($normalizedSlug, Space::RESERVED_SLUGS, true)) {
            $this->addError('slug', 'This slug is already taken. Try the suggested one below.');

            $this->slugSuggestion = SlugService::nextSuggestion($normalizedSlug);
            $this->slugAvailable = false;

            return;
        }

        $data['slug'] = $normalizedSlug;
        $data['name'] = $data['name'] !== '' ? $data['name'] : $data['title'];
        $data['subtitle'] = $data['subtitle'] ?? null;
        $data['rating_enabled'] = $data['ratingEnabled'] ?? true;
        $data['field_config'] = $this->normalizeFieldConfig($data['fieldConfig'] ?? []);

        // Cap check on CREATE only. Updates never trip the cap because
        // the Space already counted toward it. Soft-deleted Spaces do
        // NOT count (PRD §11).
        if ($this->spaceId === null) {
            $max = (int) config('limits.max_spaces', 3);
            $live = Space::live()->where('user_id', Auth::id())->count();
            if ($live >= $max) {
                $this->addError('cap', 'Delete a Space or contact support.');

                return;
            }
        }

        try {
            if ($this->spaceId === null) {
                $space = Space::create($data + ['user_id' => Auth::id()]);
                session()->flash('status', 'Space created.');

                $this->redirectRoute('spaces.created', ['space' => $space->id], navigate: true);

                return;
            }

            $space = Space::findOrFail($this->spaceId);
            $this->authorizeEdit($space);
            $space->update($data);
            session()->flash('status', 'Space updated.');

            $this->redirectRoute('spaces.index', navigate: true);
        } catch (Throwable $e) {
            // Race against the unique index: another tab created the
            // same slug between the probe and the save. Surface as a
            // form error and offer a fresh suggestion.
            $this->addError('slug', 'This slug is already taken. Try the suggested one below.');
            $this->slugSuggestion = SlugService::nextSuggestion($normalizedSlug);
            $this->slugAvailable = false;
        }
    }

    /**
     * Owner-side cap check, used by the Blade to show the
     * "Delete a Space or contact support." notice BEFORE the user
     * submits. Returns true when the owner is at or over `max_spaces`
     * LIVE Spaces.
     */
    #[Computed]
    public function isAtCap(): bool
    {
        $max = (int) config('limits.max_spaces', 3);
        $live = Space::live()->where('user_id', Auth::id())->count();

        return $live >= $max;
    }

    /**
     * Warning banner threshold. With max_spaces=3, ceil(3 * 0.8) = 3
     * (3.0 rounds down to 3; we use ceil explicitly so the rule is
     * obvious: "warn when >= 80% of cap, rounded UP"). In v1 the
     * warning therefore fires whenever the user is at the cap — same
     * condition as `isAtCap` — which is the intent: with a cap of 3
     * there is no "3/3 but not at cap" state.
     */
    #[Computed]
    public function showWarningBanner(): bool
    {
        $max = (int) config('limits.max_spaces', 3);
        $threshold = (int) ceil($max * 0.8);
        $live = Space::live()->where('user_id', Auth::id())->count();

        return $live >= $threshold;
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

    /**
     * Validation rules. Kept in one place so the probe and the save
     * can both refer to them.
     */
    protected function rules(): array
    {
        return [
            'name'          => ['nullable', 'string', 'max:120'],
            'slug'          => ['required', 'string', 'max:'.Space::SLUG_MAX_LENGTH],
            'title'         => ['required', 'string', 'max:160'],
            'subtitle'      => ['nullable', 'string', 'max:255'],
            'ask'           => ['required', 'string', 'max:2000'],
            'ratingEnabled' => ['boolean'],
            'theme'         => ['required', Rule::in(['minimal_light', 'minimal_dark', 'soft_color'])],
            'fieldConfig'   => ['array'],
        ];
    }

    protected function fillFromModel(Space $space): void
    {
        $this->name = $space->name;
        $this->slug = $space->slug;
        $this->slugHint = $space->slug;
        $this->title = $space->title;
        $this->subtitle = $space->subtitle;
        $this->ask = $space->ask;
        $this->ratingEnabled = (bool) $space->rating_enabled;
        $this->theme = $space->theme;
        $this->fieldConfig = $space->field_config ?: Space::defaultFieldConfig();
        $this->probeSlug();
    }

    /**
     * Coerce the field_config array into the canonical shape. Missing
     * keys get the default `{enabled: false, required: false}`. The
     * locked fields (name, email, address) are never accepted here.
     */
    protected function normalizeFieldConfig(array $input): array
    {
        $base = Space::defaultFieldConfig();

        foreach (['company_name', 'social_url', 'profile_photo'] as $field) {
            $row = $input[$field] ?? [];
            $base[$field] = [
                'enabled'  => (bool) ($row['enabled'] ?? false),
                'required' => (bool) ($row['required'] ?? false),
            ];
        }

        return $base;
    }

    /**
     * Authorize a CREATE attempt. v1 has no special role — any
     * authenticated user can attempt to create. The cap check fires
     * inside `save()`.
     */
    protected function authorizeCreate(): void
    {
        abort_unless(Auth::check(), 403);
    }

    /**
     * Authorize an EDIT attempt. Returns 404 (not 403) so the URL does
     * not leak whether someone else's Space exists.
     */
    protected function authorizeEdit(Space $space): void
    {
        abort_unless(Auth::check(), 403);

        if ($space->user_id !== Auth::id()) {
            abort(404);
        }

        if ($space->deleted_at !== null) {
            abort(404);
        }
    }
}
