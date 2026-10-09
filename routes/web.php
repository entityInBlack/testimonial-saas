<?php

use App\Http\Controllers\SpaceSlugCheckController;
use App\Livewire\Spaces\SpaceCreated;
use App\Livewire\Spaces\SpaceDeleted;
use App\Livewire\Spaces\SpaceForm;
use App\Livewire\Spaces\SpaceIndex;
use App\Models\Space;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

// --- Space management (auth only) ----------------------------------------
//
// The slug-check probe lives at /spaces/check-slug (no /api prefix) and
// is registered as a normal web route. The check is auth-gated so
// anonymous visitors cannot enumerate slugs.
//
// Soft-deleted Spaces are routed to the same {space} controller action
// — the controller itself is what decides "you can't see this".
Route::middleware('auth')->group(function () {
    Route::get('spaces/check-slug', SpaceSlugCheckController::class)
        ->name('spaces.check-slug');

    Route::get('spaces', SpaceIndex::class)->name('spaces.index');
    Route::get('spaces/new', SpaceForm::class)->name('spaces.new');
    Route::get('spaces/deleted', SpaceDeleted::class)->name('spaces.deleted');

    // The success page after create. We use an explicit int route so
    // the {space} parameter is the id, then resolve to the model in
    // the component. Soft-deleted Spaces return 404 from the component.
    Route::get('spaces/{space}/created', SpaceCreated::class)
        ->whereNumber('space')
        ->name('spaces.created');

    // Edit form — {space} is the id; SpaceForm::mount() loads the model
    // and 404s if it doesn't belong to the current user.
    Route::get('spaces/{space}/edit', SpaceForm::class)
        ->whereNumber('space')
        ->name('spaces.edit');
});

require __DIR__.'/auth.php';
