<?php

use App\Http\Controllers\SpaceEmbedTestimonials;
use App\Http\Controllers\SpaceSlugCheckController;
use App\Livewire\Dashboard\DashboardIndex;
use App\Livewire\Inbox\InboxIndex;
use App\Livewire\PublicSubmission;
use App\Livewire\Spaces\EmbedBuilder;
use App\Livewire\Spaces\SpaceCreated;
use App\Livewire\Spaces\SpaceDeleted;
use App\Livewire\Spaces\SpaceForm;
use App\Livewire\Spaces\SpaceIndex;
use App\Models\Space;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

// /dashboard — Step 5: counters + open deletion-requests list +
// deleted Spaces tab + time-series graph + free-plan info note.
// Auth-only (the previous `verified` middleware is removed:
// Breeze's email verification is OFF in v1 per Hard Rule and the
// Step 1 design decision "no email verification").
Route::get('dashboard', DashboardIndex::class)
    ->middleware('auth')
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

// --- Public submission page (Step 3) -------------------------------------
//
// No middleware: anonymous visitors can submit a testimonial. The
// {slug} parameter is a string (NOT a model id) so Livewire does NOT
// trigger implicit route binding; the component's mount() loads the
// live Space and aborts 404 for unknown, soft-deleted, or tombstoned
// slugs. The slug pattern restricts to URL-safe characters only.
Route::get('s/{slug}', PublicSubmission::class)
    ->where('slug', '[a-z0-9-]+')
    ->name('public.show');

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

    // Embed builder — Step 7. Owner-only. {space} is the id;
    // EmbedBuilder::mount() loads the model and 404s for non-owners
    // and soft-deleted Spaces (single source of truth: same policy
    // pattern as SpaceForm / InboxIndex).
    Route::get('spaces/{space}/embed', EmbedBuilder::class)
        ->whereNumber('space')
        ->name('spaces.embed');

    // /inbox — Step 4: per-Space list of testimonials (auth only).
    Route::get('inbox', InboxIndex::class)->name('inbox.index');
});

// --- Public embed API (Step 7) -----------------------------------------
//
// Registered in routes/web.php but EXPLICITLY outside the `web`
// middleware group. Hard rule 8: no session, no cookies, no CSRF.
// The route sits in web.php for the single-source-of-truth reason
// but is opted out of the web middleware group via
// `withoutMiddleware('web')`. HandleCors (global middleware) still
// adds `Access-Control-Allow-Origin: *` from config/cors.php. The
// 120/min throttle is applied via the `embed-api` named limiter
// defined in AppServiceProvider::boot().
Route::get('api/spaces/{public_id}/testimonials', SpaceEmbedTestimonials::class)
    ->where('public_id', '[A-Za-z0-9]+')
    ->middleware('throttle:embed-api')
    ->withoutMiddleware(['web'])
    ->name('api.spaces.testimonials');

// --- embed.js (Step 7) --------------------------------------------------
//
// The embed snippet references a permanent URL — `{APP_URL}/embed.js` —
// that the customer pastes verbatim on their own site. v1 serves the
// file from `public/embed.js` via this route so we can set the
// `Cache-Control: max-age=60` header. v2 may put a CDN in front of
// this URL or swap the file for a versioned one.
//
// `withoutMiddleware('web')` strips the session/start-session path so
// no Set-Cookie leaks. The file is read at request time; if it is
// missing the route 404s. The file at `public/embed.js` is also
// served directly by the PHP web server (and any production web
// server) when the route is not consulted — that fallback uses the
// web server's default cache headers, which is fine for v1.
Route::get('embed.js', function () {
    $path = public_path('embed.js');
    abort_unless(is_file($path), 404);

    return response()->file($path, [
        'Content-Type'  => 'application/javascript; charset=utf-8',
        'Cache-Control' => 'public, max-age=60',
    ]);
})
    ->withoutMiddleware(['web'])
    ->name('embed.js');

require __DIR__.'/auth.php';
