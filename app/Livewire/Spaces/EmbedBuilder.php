<?php

namespace App\Livewire\Spaces;

use App\Models\EmbedConfiguration;
use App\Models\Space;
use App\Models\Testimonial;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * /spaces/{space}/embed — the embed builder (Step 7).
 *
 * Owner-only. Non-owners and soft-deleted Spaces get 404 (NOT 403:
 * the same information-leak policy as SpaceForm::mount and
 * InboxIndex). A 404 from the same Livewire action surface (real
 * HTTP /livewire/update POST) is the proof target for task 7.17.
 *
 * Options persisted to `embed_configurations` (one row per Space,
 * created on first save):
 *   layout            : masonry | carousel
 *   dark_mode         : bool
 *   animation_enabled : bool
 *   background_color  : exactly #RRGGBB or null
 *   show_rating       : bool
 *   item_limit        : int  (1..50; above 50 is CLAMPED, not an error)
 *
 * Defaults when no row exists: config('embed.*') — the single source
 * of truth (used by the API too).
 *
 * Live preview on the right of the form uses
 * `scopePubliclyVisible()` for that Space with the same ordering
 * and limit the API uses (is_favorite DESC, submitted_at DESC, min(
 * item_limit, 50)).
 *
 * Snippet: the immutable public_id + APP_URL. Never the slug.
 */
#[Layout('layouts.app')]
class EmbedBuilder extends Component
{
    #[Locked]
    public ?int $spaceId = null;

    /**
     * The loaded Space. NOT typed `?Space` because Livewire tries
     * to assign the constructor's `space` parameter to a public
     * property of the same name, which would fail when the URL
     * passes an int id. We load the model inside `mount()` and
     * keep it as a public, untyped property; Livewire's serialiser
     * never needs to round-trip a `Space` instance, and we re-load
     * it from `$this->spaceId` whenever we need it.
     */
    public $space = null;

    // form-bound fields
    public string $layout = 'masonry';

    public bool $darkMode = false;

    public bool $animationEnabled = true;

    public ?string $backgroundColor = null;

    public bool $showRating = true;

    public int $itemLimit = 12;

    /**
     * Form-bound, kept as raw input so the user can type whatever
     * they want. We DO NOT rewrite it on every keystroke (typing
     * "0" would otherwise snap to "1" mid-typing, and typing "abc"
     * would prevent the validation error from ever appearing).
     * The clamp happens in `save()`; the preview below uses a
     * safely-clamped derived value.
     */
    public string $itemLimitInput = '12';

    public function mount(int|Space|null $space = null): void
    {
        $model = $space instanceof Space ? $space : Space::find($space);

        if (! $model) {
            abort(404);
        }

        $this->authorizeOwner($model);

        $this->space = $model;
        $this->spaceId = $model->id;

        $this->fillFromConfig($model->embedConfiguration);
    }

    /**
     * Single-hop owner check, same shape as InboxRow::authorizeOwner
     * and SpaceForm::authorizeEdit. 404 on non-owner, 404 on
     * soft-deleted (the URL reveals nothing).
     */
    protected function authorizeOwner(Space $space): void
    {
        abort_unless(Auth::check(), 404);

        if ($space->user_id !== Auth::id()) {
            abort(404);
        }

        if ($space->deleted_at !== null) {
            abort(404);
        }
    }

    /**
     * Load the saved row (or config/embed.php defaults when none)
     * into the form-bound fields. The form re-renders the live
     * preview on every change.
     */
    protected function fillFromConfig(?EmbedConfiguration $config): void
    {
        $defaults = config('embed');

        $this->layout            = $config?->layout            ?? $defaults['layout'];
        $this->darkMode          = (bool) ($config?->dark_mode         ?? $defaults['dark_mode']);
        $this->animationEnabled  = (bool) ($config?->animation_enabled ?? $defaults['animation_enabled']);
        $this->backgroundColor   = $config?->background_color   ?? $defaults['background_color'];
        $this->showRating        = (bool) ($config?->show_rating       ?? $defaults['show_rating']);
        $itemLimit               = (int) ($config?->item_limit     ?? $defaults['item_limit']);
        $this->itemLimit         = $itemLimit;
        $this->itemLimitInput    = (string) $itemLimit;
    }

    public function render(): View
    {
        return view('livewire.spaces.embed-builder');
    }

    /**
     * NO updatedItemLimitInput() — the input is left alone while the
     * user types, so the validation error can actually surface and
     * the user can finish typing "0" before the clamp on save kicks
     * in. The preview below uses a safe derived value (the typed
     * value clamped, or the saved/default value when the input is
     * not a positive integer).
     */

    /**
     * The value used by the preview. If the user is mid-typing an
     * invalid string, we fall back to the saved itemLimit so the
     * preview still renders. On save, the typed value is validated
     * AND clamped.
     */
    protected function previewLimit(): int
    {
        $raw = $this->itemLimitInput;
        if (is_numeric($raw) && (int) $raw >= 1) {
            return min((int) $raw, 50);
        }
        return $this->itemLimit;
    }

    /**
     * Hard rule: item_limit above 50 is CLAMPED to 50, below 1
     * becomes 1. Called from `save()` only — the input itself is
     * never rewritten as the user types.
     */
    protected function clampItemLimit(mixed $value): int
    {
        if (! is_numeric($value)) {
            return (int) config('embed.item_limit', 12);
        }

        $n = (int) $value;

        if ($n < 1) {
            return 1;
        }

        return min($n, 50);
    }

    /**
     * True iff the current backgroundColor input is a valid hex
     * colour (exactly #RRGGBB). Empty / null is NOT a valid
     * background colour for the preview — those render transparent.
     */
    public function isValidBackgroundColor(): bool
    {
        return is_string($this->backgroundColor)
            && preg_match('/^#[0-9A-Fa-f]{6}$/', $this->backgroundColor) === 1;
    }

    /**
     * Save the options. The model is upserted on `space_id` (the
     * migration has `unique(space_id)` on the embed_configurations
     * table), so the first save creates the row and later saves
     * update it.
     *
     * The CLAMP happens INSIDE save() too — even if the form is
     * bypassed (e.g. an HTTP test POSTing the wire:model fields
     * directly), the saved value cannot exceed 50.
     */
    public function save(): void
    {
        if (! $this->space) {
            abort(404);
        }

        $this->authorizeOwner($this->space);

        $data = $this->validate($this->rules(), $this->messages());

        // Clamp AFTER validation has passed (so non-integer input
        // is a validation error rather than a silent clamp).
        $data['itemLimit'] = $this->clampItemLimit($this->itemLimitInput);

        $row = EmbedConfiguration::firstOrNew(['space_id' => $this->space->id]);
        $row->fill([
            'layout'            => $data['layout'],
            'dark_mode'         => (bool) $data['darkMode'],
            'animation_enabled' => (bool) $data['animationEnabled'],
            'background_color'  => $data['backgroundColor'] !== '' ? $data['backgroundColor'] : null,
            'show_rating'       => (bool) $data['showRating'],
            'item_limit'        => (int) $data['itemLimit'],
        ])->save();

        $this->itemLimit = (int) $row->item_limit;
        $this->itemLimitInput = (string) $row->item_limit;

        session()->flash('embed_saved', 'Embed settings saved.');
        $this->dispatch('embed-saved');
    }

    /**
     * Validation rules. layout enum, hex color (exactly #RRGGBB or
     * empty), integer for item_limit (the clamp happens after
     * validation; we accept 0 here so the clamp can promote it to
     * 1 without the form throwing).
     *
     * Note: `itemLimitInput` is validated as integer (rejects
     * non-numeric) but NOT for range — out-of-range values are
     * clamped, not errored. This matches the spec.
     */
    protected function rules(): array
    {
        return [
            'layout'           => ['required', Rule::in(['masonry', 'carousel'])],
            'darkMode'         => ['boolean'],
            'animationEnabled' => ['boolean'],
            'showRating'       => ['boolean'],
            // Exactly #RRGGBB or empty (stored as null). Reject
            // "abc", "#", "12", "#12345", "#1234567", "#GGGGGG".
            'backgroundColor'  => ['nullable', 'string', 'max:7', 'regex:/^(#[0-9A-Fa-f]{6})?$/'],
            'itemLimitInput'   => ['required', 'integer'],
        ];
    }

    protected function messages(): array
    {
        return [
            'layout.in'              => 'Layout must be masonry or carousel.',
            'backgroundColor.regex'  => 'Background color must be a hex value like #1a2B3c.',
            'itemLimitInput.integer' => 'Item limit must be a whole number.',
        ];
    }

    /**
     * Live preview rows. Same scope, same ordering, same clamp as
     * the public API. Preview is server-rendered Blade so the
     * tests can assert on the HTML (escaped `{{ }}` form for
     * testimonial text — see view).
     */
    #[Computed]
    public function previewTestimonials()
    {
        if (! $this->space) {
            return collect();
        }

        $limit = $this->previewLimit();

        return $this->space->testimonials()
            ->publiclyVisible()
            ->orderByDesc('is_favorite')
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * The snippet shown to the owner. Always uses the immutable
     * public_id and config('app.url'). Never the slug.
     */
    public function snippet(): string
    {
        $publicId = $this->space?->public_id ?? '';
        $base = rtrim((string) config('app.url'), '/');

        return '<div data-testimonial-space="'.$publicId.'"></div>'."\n"
            .'<script src="'.$base.'/embed.js" async></script>';
    }
}
