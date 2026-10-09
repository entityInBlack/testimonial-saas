<?php

namespace App\Http\Controllers;

use App\Support\SlugService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Probe the slug-check endpoint (PRD §5 / build-order Step 2).
 *
 * Lives in `routes/web.php` (no /api prefix) and requires auth.
 * Returns:
 *   {
 *     "available": bool,
 *     "suggested": string|null   // next free slug if the input is taken
 *   }
 *
 * The `unique(slug)` index on the `spaces` table is the source of truth
 * for "is this slug taken". The probe is a UX nicety so the create form
 * can show the suggested suffix without round-tripping through a save.
 *
 * Uses `Space::withTrashed()` so soft-deleted and tombstoned slugs count
 * as taken — once a slug is claimed, it stays claimed forever.
 */
class SpaceSlugCheckController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'slug' => ['nullable', 'string', 'max:200'],
        ]);

        $result = SlugService::probe((string) $request->query('slug', ''));

        return response()->json($result);
    }
}
