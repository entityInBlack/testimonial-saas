<?php

namespace App\Http\Controllers;

use App\Http\Resources\EmbedTestimonialResource;
use App\Models\Space;
use App\Models\Testimonial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public embed API endpoint.
 *
 * Route: GET /api/spaces/{public_id}/testimonials
 *   - Registered in routes/web.php with `withoutMiddleware('web')`,
 *     so no session, no cookies, no CSRF. The route IS in the
 *     `web.php` file (per spec), so it stays under one source of
 *     truth for routes, but it is NOT under the `web` middleware
 *     group. The 120/min throttle and `throttle:embed-api` limiter
 *     are applied per route.
 *   - `Access-Control-Allow-Origin: *` is supplied by Laravel's
 *     `HandleCors` global middleware, driven by `config/cors.php`
 *     (which has `'paths' => ['api/*']`). The controller does NOT
 *     set CORS headers itself — that is the whole point: the test
 *     that asserts the header proves the middleware path is wired.
 *   - `config/cors.php` allows `Access-Control-Allow-Origin: *`
 *     for `api/*` paths.
 *
 * Branch (after the lookup, INCLUDING soft-deleted rows):
 *   - public_id not found   -> 404 {"error": "Not Found"}
 *   - soft-deleted Space    -> 200 {"config": null, "testimonials": []}
 *   - live Space            -> 200 with the projected config and
 *                              the rows from scopePubliclyVisible()
 *
 * Hard rules honoured:
 *   - Hard rule 8: registered outside the `web` middleware group.
 *   - Hard rule 9: server-side clamp of $limit = min((int) item_limit, 50).
 *   - Hard rule 9: any ?limit=N is also clamped to 50; bad ?limit is
 *     ignored (not 500).
 *   - Hard rule 10: API resource whitelist, never projects email,
 *     address, or ids.
 *   - Hard rule 9: `show_rating=false` omits `rating` server-side.
 *
 * Cache headers: `Cache-Control: max-age=60` on 200 responses,
 * with NO `private` / `no-cache` / `no-store` and NO `Set-Cookie`.
 * The `web` group (which is what would set cookies) is not in play.
 */
class SpaceEmbedTestimonials
{
    /**
     * Hard ceiling for the limit (data-model §3.4 / build-order hard rule 9).
     */
    public const MAX_LIMIT = 50;

    public function __invoke(Request $request, string $public_id): JsonResponse
    {
        // 1. Look up the Space including soft-deleted rows. The
        //    `withTrashed()` is what lets us distinguish a 404 from
        //    a 200-with-empty-body.
        $space = Space::withTrashed()->where('public_id', $public_id)->first();

        if (! $space) {
            return response()->json(
                ['error' => 'Not Found'],
                404
            );
        }

        // 2. Soft-deleted Space: 200 with empty payload. Branched
        //    in code after the row is found (data-model §4.1).
        if ($space->deleted_at !== null) {
            return $this->respondCachedJson(
                ['config' => null, 'testimonials' => []]
            );
        }

        // 3. Live Space: project the config (saved row OR code
        //    defaults from config/embed.php — one source of truth).
        $config = $this->projectConfig($space->embedConfiguration);

        // 4. Effective limit: min(item_limit, 50) and a ?limit=N
        //    override (clamped to the same 50). Bad ?limit is
        //    ignored — NEVER 500.
        $effectiveLimit = $this->resolveLimit(
            itemLimit: (int) $config['item_limit'],
            queryLimit: $request->query('limit'),
        );

        // 5. Rows: scopePubliclyVisible() only (is_public = 1 via the
        //    STORED generated column), ordered is_favorite DESC,
        //    submitted_at DESC. Tie-breaker by id (same as the Inbox
        //    — see InboxIndex::testimonials()).
        $rows = $space->testimonials()
            ->publiclyVisible()
            ->orderByDesc('is_favorite')
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->limit($effectiveLimit)
            ->get();

        // 6. Resource: whitelisted keys only. Rating is omitted
        //    entirely (not null) when show_rating is false. The
        //    resource is constructed per row with the show_rating
        //    flag passed through the constructor.
        $items = $rows->map(
            fn (Testimonial $row) => (new EmbedTestimonialResource($row, (bool) $config['show_rating']))
                ->resolve($request)
        )->all();

        return $this->respondCachedJson(
            ['config' => $config, 'testimonials' => array_values($items)]
        );
    }

    /**
     * Project the embed_configurations row to the SIX public keys,
     * or fall back to config/embed.php when no row exists. Never
     * project id, space_id, created_at, updated_at.
     *
     * @return array<string, mixed>
     */
    protected function projectConfig($embedConfig): array
    {
        if ($embedConfig) {
            return [
                'layout'            => $embedConfig->layout,
                'dark_mode'         => (bool) $embedConfig->dark_mode,
                'animation_enabled' => (bool) $embedConfig->animation_enabled,
                'background_color'  => $embedConfig->background_color,
                'show_rating'       => (bool) $embedConfig->show_rating,
                'item_limit'        => (int) $embedConfig->item_limit,
            ];
        }

        // Defaults from config('embed.*') — the single source of
        // truth used by the builder too.
        return [
            'layout'            => (string) config('embed.layout', 'masonry'),
            'dark_mode'         => (bool) config('embed.dark_mode', false),
            'animation_enabled' => (bool) config('embed.animation_enabled', true),
            'background_color'  => config('embed.background_color'),
            'show_rating'       => (bool) config('embed.show_rating', true),
            'item_limit'        => (int) config('embed.item_limit', 12),
        ];
    }

    /**
     * Resolve the effective row cap.
     *
     *  effective = min(
     *      ?limit (when given AND a positive INTEGER) else item_limit,
     *      self::MAX_LIMIT
     *  )
     *
     * Bad ?limit (zero, negative, non-integer, non-numeric) is
     * ignored: the saved item_limit is used instead. This branch
     * never throws.
     */
    protected function resolveLimit(int $itemLimit, mixed $queryLimit): int
    {
        $base = $itemLimit;

        if ($queryLimit !== null && $queryLimit !== '' && is_string($queryLimit)) {
            // Must be a positive INTEGER. Decimals like "1.5" are
            // also rejected — a fractional cap is not meaningful
            // and silently truncating would hide the bug.
            if (preg_match('/^[1-9][0-9]*$/', $queryLimit) === 1) {
                $base = (int) $queryLimit;
            }
        }

        return min($base, self::MAX_LIMIT);
    }

    /**
     * Wrap a 200 response with the required cache headers.
     * Cache-Control contains max-age=60 and NOT private/no-cache/
     * no-store.
     *
     * CORS is handled ENTIRELY by Laravel's HandleCors global
     * middleware (driven by config/cors.php). The controller does
     * NOT set Access-Control-Allow-Origin so the test that asserts
     * that header is a real proof that the middleware path is wired.
     */
    protected function respondCachedJson(array $payload): JsonResponse
    {
        $response = response()->json($payload);

        $response->headers->set('Cache-Control', 'public, max-age=60');

        // Defence in depth: if some upstream code (we don't expect any)
        // tries to set a Set-Cookie header on this response, drop it.
        // The web group (which is the only place cookies come from) is
        // not in play for this route, so this is a belt-and-braces guard.
        $response->headers->remove('Set-Cookie');

        return $response;
    }
}
