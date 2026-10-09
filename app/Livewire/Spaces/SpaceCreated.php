<?php

namespace App\Livewire\Spaces;

use App\Models\Space;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * /spaces/{space}/created — the success page shown after a Space is
 * created (PRD §5 / build-order Step 2).
 *
 * Renders:
 *   - the public URL `/s/{slug}` so the owner can copy it
 *   - a Copy Link button
 *   - a Go to Dashboard button
 *
 * Step 3 (the public /s/{slug} page itself) is NOT built here — this
 * page just shows the URL.
 */
#[Layout('layouts.app')]
class SpaceCreated extends Component
{
    #[Locked]
    public int $spaceId;

    public ?Space $space = null;

    public function mount(int|Space $space): void
    {
        // Livewire 3 resolves the route param `{space}` through
        // ImplicitRouteBinding, so a Space model is passed in. Tests
        // (and the legacy non-bound route) may pass an int id. Handle
        // both.
        $model = $space instanceof Space ? $space : Space::find($space);

        if (! $model || $model->user_id !== Auth::id()) {
            abort(404);
        }

        $this->spaceId = $model->id;
        $this->space = $model;
    }

    public function render(): View
    {
        return view('livewire.spaces.space-created', [
            'publicUrl' => url('/s/'.$this->space->slug),
        ]);
    }
}
