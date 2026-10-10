<?php

namespace App\Livewire\Dashboard;

use App\Models\DeletionRequest;
use App\Models\Space;
use App\Models\Testimonial;
use App\Services\SpaceRestoreService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * /dashboard — the owner's overview.
 *
 * Step 5 scope (PRD §8 / build-order Step 5):
 *   - Counter cards (LIVE Spaces only — soft-deleted excluded; soft-
 *     deleted testimonials inside a live Space also excluded):
 *     Total testimonials
 *     Total customers        (label exactly "Total customers";
 *                             COUNT(DISTINCT email))
 *     Spaces used            (X of config('limits.max_spaces'))
 *     Average rating         (non-null ratings only)
 *     Open deletion requests count
 *   - Open deletion-requests list — `status='open'`, matched to owner's
 *     LIVE Spaces by `space_id`. Requests whose `space_id` is a soft-
 *     deleted Space are NOT shown. Requests with `testimonial_id =
 *     NULL` (Hard Rule 14 / Sara's seeded row) render fine — there is
 *     no name to look up, so the row shows the email instead.
 *   - Deleted Spaces tab — uses the same Restore widget as Step 2
 *     (SpaceDeleted component). Restore stays blocked at cap.
 *   - Time-series graph — Chart.js via CDN. Granularity
 *     day|week|month, range 7d|30d|90d|all time, optional Space
 *     filter. UTC bucketing at the DB level; zero-count buckets
 *     filled in application code. Display in owner's locale.
 *     Invalid granularity/range values fall back to defaults
 *     (granularity=day, range=30d).
 *   - Free-plan info note — exact copy, no link, no button, no CTA.
 *     Renders only when the owner has at least one LIVE Space.
 *   - Empty states: no spaces → "Create your first Space";
 *     spaces but no testimonials → "Share your link to start
 *     collecting." (matches the empty-state string from the spec).
 *
 * Hard Rules relevant here:
 *   - 1, 2 — no Stripe / Cashier / `/billing` / Plan enum. The
 *     Free-plan note must not link anywhere.
 *   - 6 — soft-deleting a Space never touches testimonials; the
 *     counters therefore use `Space::live()` and the soft-delete
 *     column on `testimonials`.
 *   - 14 — a `deletion_requests` row with `testimonial_id = NULL`
 *     is legitimate; the row renders the email instead of a name.
 */
#[Layout('layouts.app')]
class DashboardIndex extends Component
{
    /**
     * Granularity for the time-series graph. Allowed values:
     * 'day', 'week', 'month'. Anything else is coerced to 'day'
     * so a hostile URL cannot crash the page.
     */
    #[Url]
    public string $granularity = 'day';

    /**
     * Range for the time-series graph. Allowed values: '7d', '30d',
     * '90d', 'all'. Anything else is coerced to '30d'.
     */
    #[Url]
    public string $range = '30d';

    /**
     * Optional Space filter. The value is sanitized in `mount()`:
     * a foreign or soft-deleted Space id is dropped (so the graph
     * never leaks data from another owner or a soft-deleted Space).
     */
    #[Url]
    public ?int $spaceId = null;

    /**
     * Active tab on the dashboard. Allowed: 'active' (the default
     * — counters + list + graph), 'deleted' (Deleted Spaces tab).
     */
    #[Url]
    public string $tab = 'active';

    public const ALLOWED_GRANULARITY = ['day', 'week', 'month'];
    public const ALLOWED_RANGES = ['7d', '30d', '90d', 'all'];
    public const ALLOWED_TABS = ['active', 'deleted'];

    public function mount(): void
    {
        // Sanitize URL-bound inputs — a stale or hostile URL cannot
        // crash the page or leak data.
        if (! in_array($this->granularity, self::ALLOWED_GRANULARITY, true)) {
            $this->granularity = 'day';
        }
        if (! in_array($this->range, self::ALLOWED_RANGES, true)) {
            $this->range = '30d';
        }
        if (! in_array($this->tab, self::ALLOWED_TABS, true)) {
            $this->tab = 'active';
        }

        // Foreign / soft-deleted Space filter is silently dropped.
        // We do NOT 404 here — the dashboard must stay available
        // (a 404 would leak that the Space id exists).
        if ($this->spaceId !== null) {
            $owns = Space::live()
                ->where('user_id', Auth::id())
                ->whereKey($this->spaceId)
                ->exists();
            if (! $owns) {
                $this->spaceId = null;
            }
        }
    }

    public function updatedGranularity(): void
    {
        if (! in_array($this->granularity, self::ALLOWED_GRANULARITY, true)) {
            $this->granularity = 'day';
        }
        $this->dispatchGraphUpdated();
    }

    public function updatedRange(): void
    {
        if (! in_array($this->range, self::ALLOWED_RANGES, true)) {
            $this->range = '30d';
        }
        $this->dispatchGraphUpdated();
    }

    public function updatedSpaceId(): void
    {
        if ($this->spaceId === null) {
            $this->dispatchGraphUpdated();
            return;
        }
        $owns = Space::live()
            ->where('user_id', Auth::id())
            ->whereKey($this->spaceId)
            ->exists();
        if (! $owns) {
            $this->spaceId = null;
        }
        $this->dispatchGraphUpdated();
    }

    /**
     * Dispatch a `dashboard-graph-updated` window event with the
     * current graphData so the Alpine `x-data` component on the
     * canvas can mutate the existing Chart in place (no second
     * Chart on the same canvas → no "Canvas is already in use").
     */
    private function dispatchGraphUpdated(): void
    {
        $this->dispatch('dashboard-graph-updated',
            labels: $this->graphData['labels'],
            counts: $this->graphData['counts'],
        );
    }

    public function setTab(string $tab): void
    {
        if (! in_array($tab, self::ALLOWED_TABS, true)) {
            $tab = 'active';
        }
        $this->tab = $tab;
    }

    /**
     * Restore a soft-deleted Space from the Deleted Spaces tab.
     *
     * Delegates to the shared `SpaceRestoreService` — the same
     * service the standalone /spaces/deleted page uses. The cap
     * check, tombstone check, and owner authorization are defined
     * in ONE place. The user stays on /dashboard after restore
     * (no redirect).
     */
    public function restoreDeletedSpace(int $spaceId, SpaceRestoreService $service): void
    {
        $result = $service->restore($spaceId);

        if ($result->isNotFound()) {
            abort(404);
        }

        if ($result->isTombstoned()) {
            $this->addError('restore', 'This Space is past the restore window and cannot be restored.');

            return;
        }

        if ($result->isCapReached()) {
            $this->addError('restore', "You're at the Free plan limit of {$result->cap} Spaces. Delete a Space first.");

            return;
        }

        session()->flash('status', 'Space restored.');
    }

    public function render(): View
    {
        return view('livewire.dashboard.dashboard-index');
    }

    // -----------------------------------------------------------------
    // Counters — all against LIVE Spaces only.
    // -----------------------------------------------------------------

    /**
     * Base query that filters to the owner's LIVE Spaces. Every
     * counter reuses this so the soft-delete rule is in one place.
     */
    private function liveSpaceIdsQuery()
    {
        return Space::live()
            ->where('user_id', Auth::id())
            ->select('id');
    }

    /**
     * Total testimonials — all live testimonials in the owner's
     * live Spaces. Soft-deleted testimonials inside a live Space
     * are excluded.
     */
    #[Computed]
    public function totalTestimonials(): int
    {
        return Testimonial::query()
            ->whereIn('space_id', $this->liveSpaceIdsQuery())
            ->whereNull('testimonials.deleted_at')
            ->count();
    }

    /**
     * Total customers — `COUNT(DISTINCT email)` across the owner's
     * live Spaces' live testimonials. The pinned formula is:
     *
     *   SELECT COUNT(DISTINCT email)
     *   FROM testimonials
     *   JOIN spaces ON spaces.id = testimonials.space_id
     *   WHERE spaces.user_id = :owner_id
     *     AND spaces.deleted_at IS NULL
     *     AND testimonials.deleted_at IS NULL
     *
     * Same email across two of the owner's Spaces counts once.
     */
    #[Computed]
    public function totalCustomers(): int
    {
        return (int) DB::table('testimonials')
            ->join('spaces', 'spaces.id', '=', 'testimonials.space_id')
            ->where('spaces.user_id', Auth::id())
            ->whereNull('spaces.deleted_at')
            ->whereNull('testimonials.deleted_at')
            ->distinct()
            ->count('testimonials.email');
    }

    /**
     * Spaces used (live count).
     */
    #[Computed]
    public function spacesUsed(): int
    {
        return Space::live()->where('user_id', Auth::id())->count();
    }

    /**
     * Max spaces — read from config every render so a config change
     * is reflected (e.g. in a test that mutates `config('limits.max_spaces')`).
     */
    #[Computed]
    public function maxSpaces(): int
    {
        return (int) config('limits.max_spaces', 3);
    }

    /**
     * Max testimonials per Space — read from config every render so
     * a config change is reflected (the Free-plan note uses this so
     * the copy stays in sync with the cap).
     */
    #[Computed]
    public function maxTestimonialsPerSpace(): int
    {
        return (int) config('limits.max_testimonials_per_space', 100);
    }

    /**
     * Average rating — non-null ratings only. Returns null when
     * there are no rated testimonials in the owner's live Spaces
     * (the view renders an explicit "No ratings yet" empty value).
     */
    #[Computed]
    public function averageRating(): ?float
    {
        $avg = Testimonial::query()
            ->whereIn('space_id', $this->liveSpaceIdsQuery())
            ->whereNull('testimonials.deleted_at')
            ->whereNotNull('rating')
            ->avg('rating');

        return $avg === null ? null : (float) $avg;
    }

    /**
     * Open deletion-requests count — matched to the owner's LIVE
     * Spaces by `space_id`. A request whose `space_id` is a soft-
     * deleted Space is NOT counted.
     */
    #[Computed]
    public function openDeletionRequestsCount(): int
    {
        return DeletionRequest::query()
            ->where('status', DeletionRequest::STATUS_OPEN)
            ->whereIn('space_id', $this->liveSpaceIdsQuery())
            ->count();
    }

    // -----------------------------------------------------------------
    // Open deletion-requests list
    // -----------------------------------------------------------------

    /**
     * Open deletion requests, joined to the owning Space and (when
     * available) the testimonial so the view can show a name. The
     * `testimonial_id` may be NULL — the SQL LEFT JOIN and the
     * `nullOnDelete` migration handle that. The list is capped at
     * 200 rows; the dashboard is not meant to be paginated — the
     * Inbox is. We sort by `created_at DESC` (newest first).
     */
    #[Computed]
    public function openDeletionRequests(): EloquentCollection
    {
        return DeletionRequest::query()
            ->select('deletion_requests.*')
            ->join('spaces', 'spaces.id', '=', 'deletion_requests.space_id')
            ->where('spaces.user_id', Auth::id())
            ->whereNull('spaces.deleted_at')
            ->where('deletion_requests.status', DeletionRequest::STATUS_OPEN)
            ->orderByDesc('deletion_requests.created_at')
            ->with(['space:id,title,slug', 'testimonial:id,name,email'])
            ->limit(200)
            ->get();
    }

    // -----------------------------------------------------------------
    // Spaces for the graph filter
    // -----------------------------------------------------------------

    /**
     * Live Spaces for the graph's space-filter dropdown. Foreign
     * and soft-deleted Spaces are never returned.
     */
    #[Computed]
    public function liveSpaces(): EloquentCollection
    {
        return Space::live()
            ->where('user_id', Auth::id())
            ->orderBy('title')
            ->get(['id', 'title', 'slug']);
    }

    /**
     * Soft-deleted Spaces within the retention window for the
     * Deleted Spaces tab. Tombstoned Spaces are excluded via
     * `recentlyDeleted()`. The dashboard tab reuses the Step 2
     * semantics — same data, same `restore` logic — without
     * leaving the dashboard route.
     */
    #[Computed]
    public function deletedSpaces(): EloquentCollection
    {
        return Space::recentlyDeleted()
            ->where('user_id', Auth::id())
            ->orderByDesc('deleted_at')
            ->get();
    }

    // -----------------------------------------------------------------
    // Time-series graph
    // -----------------------------------------------------------------

    /**
     * The graph data: a flat list of buckets `{ label, count }` in
     * ascending date order. Bucketing happens at the database level
     * (`DATE(submitted_at)` evaluated in MySQL's session timezone,
     * which is UTC for v1 — see `config('app.timezone')` and the
     * design decision "UTC-only graph bucketing"). Zero-count
     * buckets are filled in application code from the requested
     * range so the X axis has the correct spacing.
     *
     * Returns: array{ labels: list<string>, counts: list<int> }
     */
    #[Computed]
    public function graphData(): array
    {
        [$rows, ] = $this->runGraphQuery();

        // Index actual counts by bucket string.
        $actual = [];
        foreach ($rows as $row) {
            $actual[(string) $row->bucket] = (int) $row->cnt;
        }

        // Zero-fill buckets from $start to $end in app code.
        $keys = [];
        $labels = [];
        $counts = [];
        [$start, $end] = $this->resolvedRangeBounds();
        $cursor = $this->bucketFloor($start, $this->granularity);
        $endBucket = $this->bucketFloor($end, $this->granularity);
        $locale = $this->ownerLocale();

        while ($cursor->lessThanOrEqualTo($endBucket)) {
            $key = $this->bucketKey($cursor, $this->granularity);
            $keys[] = $key;
            $labels[] = $cursor->locale($locale)->isoFormat($this->bucketIsoFormat($this->granularity));
            $counts[] = $actual[$key] ?? 0;
            $cursor = $this->bucketStep($cursor, $this->granularity);
        }

        return [
            'keys' => $keys,
            'labels' => $labels,
            'counts' => $counts,
        ];
    }

    /**
     * The raw SQL of the graph bucketing query (test/proof hook).
     * Returns the SQL string the database receives so the test can
     * assert that UTC bucketing happens at the DB level (`DATE(...)`).
     */
    #[Computed]
    public function graphSql(): string
    {
        [, $sql] = $this->runGraphQuery();

        return $sql;
    }

    /**
     * Run the graph query, return both the rows and the SQL string.
     * Centralizes the bucket expression so the live `graphData` and
     * the `graphSql` proof hook can never disagree.
     *
     * @return array{0: \Illuminate\Support\Collection, 1: string}
     */
    private function runGraphQuery(): array
    {
        $ownerId = Auth::id();
        [$start, $end] = $this->resolvedRangeBounds();

        // For MySQL: submitted_at is a TIMESTAMP column, so its date
        // part depends on the session timezone unless we force-convert
        // to UTC at the SQL layer. Wrapping the column in
        // CONVERT_TZ(col, @@session.time_zone, '+00:00') gives us a
        // stable UTC instant regardless of the connection's session
        // timezone — proves bucketing is session-tz independent.
        $dateExpr = "DATE(CONVERT_TZ(testimonials.submitted_at, @@session.time_zone, '+00:00'))";
        $tsExpr = "CONVERT_TZ(testimonials.submitted_at, @@session.time_zone, '+00:00')";

        $query = DB::table('testimonials')
            ->join('spaces', 'spaces.id', '=', 'testimonials.space_id')
            ->where('spaces.user_id', $ownerId)
            ->whereNull('spaces.deleted_at')
            ->whereNull('testimonials.deleted_at')
            // Use whereRaw because the column expression includes
            // CONVERT_TZ() which Laravel's whereBetween would
            // backtick-quote as if it were a column name.
            ->whereRaw("{$tsExpr} between ? and ?", [$start->toDateTimeString(), $end->toDateTimeString()]);

        if ($this->spaceId !== null) {
            $query->where('testimonials.space_id', $this->spaceId);
        }

        // ISO week: %x-%v = ISO year + ISO week number.
        $groupExpr = match ($this->granularity) {
            'day'   => $dateExpr,
            'week'  => "DATE_FORMAT(CONVERT_TZ(testimonials.submitted_at, @@session.time_zone, '+00:00'), '%x-%v')",
            'month' => "DATE_FORMAT(CONVERT_TZ(testimonials.submitted_at, @@session.time_zone, '+00:00'), '%Y-%m')",
        };

        $rows = $query
            ->selectRaw("{$groupExpr} as bucket, COUNT(*) as cnt")
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->get();

        // Capture the SQL via toSql() — bindings are not in the
        // string, but the bucket expression IS, which is what the
        // test asserts (UTC bucketing at DB level).
        $sql = $query->toSql();

        return [$rows, $sql];
    }

    /**
     * Resolve the [start, end] datetime bounds for the current
     * range. 'all' uses the earliest submission in the owner's
     * live Spaces (or today, if none). Fixed ranges use
     * `now - N + 1 days` as the start, end-of-today as the end.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function resolvedRangeBounds(): array
    {
        $now = CarbonImmutable::now('UTC');
        $range = $this->resolvedRange();

        if ($range === null) {
            // 'all'
            $first = DB::table('testimonials')
                ->join('spaces', 'spaces.id', '=', 'testimonials.space_id')
                ->where('spaces.user_id', Auth::id())
                ->whereNull('spaces.deleted_at')
                ->whereNull('testimonials.deleted_at')
                ->min('testimonials.submitted_at');

            $start = $first
                ? CarbonImmutable::parse($first, 'UTC')->startOfDay()
                : $now->startOfDay();
        } else {
            $start = $now->startOfDay()->subDays($range - 1);
        }

        $end = $now->endOfDay();

        return [$start, $end];
    }

    /**
     * Resolve the range into an integer day count. `'all'` returns
     * null and is handled inside `graphData()`.
     */
    private function resolvedRange(): ?int
    {
        return match ($this->range) {
            '7d'  => 7,
            '30d' => 30,
            '90d' => 90,
            'all' => null,
            default => 30,
        };
    }

    /**
     * Floor a datetime to the start of its bucket. Day → 00:00:00,
     * week → Monday 00:00:00 (ISO), month → 1st 00:00:00.
     */
    private function bucketFloor(CarbonImmutable $dt, string $granularity): CarbonImmutable
    {
        return match ($granularity) {
            'day'   => $dt->startOfDay(),
            'week'  => $dt->startOfWeek(CarbonImmutable::MONDAY),
            'month' => $dt->startOfMonth(),
        };
    }

    /**
     * String key for a bucket — must match the SQL `$groupExpr`
     * output byte-for-byte.
     */
    private function bucketKey(CarbonImmutable $dt, string $granularity): string
    {
        return match ($granularity) {
            'day'   => $dt->toDateString(),
            'week'  => $dt->format('o-W'),
            'month' => $dt->format('Y-m'),
        };
    }

    /**
     * Advance the cursor by one bucket. CarbonImmutable::addDay /
     * addWeek / addMonth all keep us in the right granularity.
     */
    private function bucketStep(CarbonImmutable $dt, string $granularity): CarbonImmutable
    {
        return match ($granularity) {
            'day'   => $dt->addDay(),
            'week'  => $dt->addWeek(),
            'month' => $dt->addMonth(),
        };
    }

    /**
     * ISO display format for the bucket label. Day → "Sep 14",
     * week → "Sep 8 — Sep 14" (the week of), month → "September
     * 2026". All in the owner's locale.
     */
    private function bucketIsoFormat(string $granularity): string
    {
        return match ($granularity) {
            'day'   => 'MMM D',
            'week'  => 'MMM D',
            'month' => 'MMMM YYYY',
        };
    }

    /**
     * The owner's preferred display locale. v1 has no per-user
     * setting; we fall back to the app locale.
     */
    private function ownerLocale(): string
    {
        return (string) config('app.locale', 'en');
    }

    // -----------------------------------------------------------------
    // Free-plan note
    // -----------------------------------------------------------------

    /**
     * Whether to show the Free-plan info note. The note is the
     * only place the dashboard mentions the Free plan; it must not
     * link anywhere and must not exist when the owner has zero
     * live Spaces (the empty Spaces state takes over).
     */
    #[Computed]
    public function showFreePlanNote(): bool
    {
        return $this->spacesUsed > 0;
    }

    /**
     * Retention window (days) — used by the deletion-requests copy
     * so the note matches the policy the user can actually act on.
     */
    #[Computed]
    public function retentionDays(): int
    {
        return (int) config('purge.retention_days', 30);
    }
}
