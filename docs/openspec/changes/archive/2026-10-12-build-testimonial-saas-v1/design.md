# Design: Build Testimonial Collection SaaS v1

## Context

The repo is greenfield. The product is a Laravel 11 + Livewire 3 + MySQL testimonial-collection SaaS. `final PRD.md` v2.5, `data-model.md` v2.5, and `build order.md` v2.6 are the source of truth for scope and sequencing. v1 ships the Free plan only; Pro, Stripe, Cashier, webhooks, `/billing`, `Plan` enum, and `subscribed('default')` are explicitly out of scope. Limits are hardcoded in `config/limits.php` (3 spaces / 100 testimonials per Space). The product must be production-shaped but built on a stack the owner can run without a card (no card-required third parties).

The design follows `build order.md` v2.6's 8 steps in order: setup → spaces → public submission → inbox → dashboard → purge → embed → demo fixtures. Each step's "Done means" checks must pass before the next step starts, because later steps build on earlier assumptions (e.g. Step 3 photo tests depend on Step 1's `storage:link`; Step 7 embed ordering must match Step 4 inbox sort).

## Goals / Non-Goals

**Goals:**
- Ship a working demo where the Free-plan caps, `is_public` STORED column, embed endpoint, dashboard counters, and deletion-request flow are all exercised by fixtures.
- Build a hard rule set (15 numbered rules in `build order.md`) into the code, tests, and config so they cannot be rediscovered as bugs.
- Provide a v2-ready extension surface (trusted-proxy switch, consent versioning, `purge` log channel with explicit 14-day retention) without shipping v2 features.

**Non-Goals:**
- No Stripe, no Cashier, no `Billable` trait, no `STRIPE_*` env keys, no `/billing` route, no upgrade CTA, no `Plan` enum, no `subscribed('default')`, no `stripe_events` table, no `stripe` log channel. (`build order.md` Hard rule 1 and 2.)
- No email verification. `MustVerifyEmail` is OFF in v1; the `email_verified_at` column is kept.
- No CAPTCHA. The public submission flow uses honeypot + IP/email rate limits + 5-minute dedupe.
- No EAV, no user-defined custom fields. The 3 fixed optional fields (`company_name`, `social_url`, `profile_photo`) are columns on `testimonials` and toggled via `field_config` JSON.
- No owner edit history. Edits overwrite the original; no "edited" marker.
- No Cloudflare, no real CDN in front. The `embed.js` is served directly by the app at `{APP_URL}/embed.js` with a short cache.
- No timezone-corrected graphs. UTC bucketing only.

## Decisions

### Decision: MySQL STORED generated column for `is_public`

**Why:** PRD §3.4, data-model §3.3, and `build order.md` Hard rule 3 all require `is_public` to be a STORED generated column. Hand-rolled visibility at four call sites (embed, live preview, WoL empty state, dashboard) would eventually disagree. The exact expression is canonical and must NOT be improvised.

**Alternatives considered:**
- Eloquent accessor that recomputes on every read — rejected for read cost and drift risk.
- Observer that mirrors `is_public` on a non-generated column — rejected for write-path fragility.
- A `visibility` enum combining `is_wall_of_love` and `is_hidden` — rejected because hide is an *override layered on a publish decision*, not a replacement; one enum would lose publication state on hide.

**Acceptance:** If the local MySQL refuses the expression (especially `deleted_at IS NULL` inside a STORED expression), stop and report the exact error. Do not improvise.

### Decision: Two public identifiers (`slug` + `public_id`)

**Why:** Slug is the shareable collection URL (`/s/{slug}`), mutable by the owner. `public_id` is the embed key, immutable. If embeds used slug, renaming a Space would silently break every embed already live on customer sites. PRD §3.2 and data-model §3.2 lock this.

**`public_id`:** `char(12)`, generated with `Str::random(12)` (alphanumeric, non-sequential, hard to guess), unique, no setter exposed.

**Slug:** varchar(60), kebab-case from title, editable. Globally unique; the database `unique(slug)` index is the source of truth. Soft-deleted and tombstoned slugs are claimed forever — soft-deleted Spaces are NOT purged by the FK cascade; the `purge:run` job keeps the Space row as a tombstone.

### Decision: Three locked fields are columns, not config

**Why:** Name, Email, Address are always required and never public. Making them columns on `testimonials` (instead of `field_config` toggles) makes the email/address guarantee *structural, not procedural*. No embed query path can ever reach an email value via a generic "dump every value" code path; the columns are simply not projected. `COUNT(DISTINCT email)` is an indexed aggregate. The 3 fixed optional fields (`company_name`, `social_url`, `profile_photo`) are nullable columns on `testimonials` and toggled via `field_config` JSON on `spaces`.

**Alternatives considered:**
- EAV (`space_fields` + `testimonial_values`) — rejected: over-engineered for a closed field set; embed queries must stay a single-table read.
- `field_config` JSON with arbitrary keys (implying user-defined custom fields) — rejected: out of scope for MVP.

### Decision: Auth throttles inside Livewire component actions

**Why:** Breeze's forgot-password, reset-password, and register are Volt/Livewire components, NOT POST routes. Route-level `throttle` middleware does not see the action body. Rate limiting must be enforced **inside** the action using `RateLimiter::tooManyAttempts` / `hit`, with two keys (per-IP and per-`email+IP`), 5 attempts per 15 minutes. Login keeps Breeze's built-in `ThrottlesLogins`.

Named limiters are defined in `AppServiceProvider::boot()` (Laravel 11 has no `RouteServiceProvider`). On limit hit, the component throws `ValidationException::withMessages(['email' => 'Too many attempts. Try again later.'])` and does not process the action.

**Test:** `AuthThrottleTest` calls the action directly 6 times and asserts the 6th call returns the validation error (NOT an HTTP 429, since the throttle is in the action, not on a route).

### Decision: Public embed API outside the `web` middleware group

**Why:** The endpoint is hit by third-party sites. It must not set cookies, must not start a session, and must not require CSRF. Laravel 11's default `config/cors.php` already allows `Access-Control-Allow-Origin: *` for `api/*` paths, which is the route family `/api/spaces/{public_id}/testimonials` belongs to.

Registered in `routes/web.php` **outside** the `web` middleware group. Throttled `120/min` per IP. `Cache-Control: max-age=60`. Lookup query intentionally omits `AND deleted_at IS NULL`; the branch is decided in code after the row is found (404 / 200 empty / live scope).

**Test:** Pest test sends an `Origin` header and asserts `Access-Control-Allow-Origin: *`. Another test asserts no `Set-Cookie` header and `Cache-Control: max-age=60`.

### Decision: Withdraw-consent as a Livewire method, NOT a POST route

**Why:** A controller-backed route would duplicate the authorization logic that Livewire already wires up. `InboxRow::withdrawConsent(Testimonial $t)` is the canonical method. It authorizes `$space->user_id === $user->id` (single hop), sets `consent_given=0` AND `is_wall_of_love=0` (so the UI never shows a WoL badge on a row that cannot be public), and KEEPS `consented_at` and `consent_text_version` as audit. The `is_public` STORED column recomputes automatically.

### Decision: Forget-now three-step order (fixed)

**Why:** The order matters. If `forceDelete()` runs first, the `testimonial_id` FK on `deletion_requests` is set to `null` (via `nullOnDelete`) BEFORE the request can be matched, breaking the audit. The order is:
1. `UPDATE deletion_requests SET status='acted', acted_at=NOW() WHERE space_id=:space_id AND status='open' AND (testimonial_id=:testimonial_id OR (testimonial_id IS NULL AND email=:testimonial_email))` — match by id OR (null id AND email).
2. Delete the photo file from the `public` disk (`Storage::disk('public')->delete($path)`). Ignore only "file not found"; any other error keeps the row for the next run.
3. `forceDelete()` the testimonial row.

`purge:run` runs the SAME order per testimonial before `forceDelete()`.

### Decision: Photo pipeline on the `public` disk

**Why:** Photos must be reachable by `embed.js` on third-party sites. The `public` disk is served from `storage/app/public` via `php artisan storage:link`. The symlink is a Step 1 deliverable (Hard rule 5) because Step 3's photo tests fail with confusing 404s without it.

**Pipeline:** `getimagesize()` header check → MIME `jpg|png|webp` only → 5 MB cap → hard-reject > 4000 px on either side → Intervention Image re-encode to webp ~400 px wide → random name generated BEFORE saving → store at `photos/{random}.webp` (flat path; original filename never stored) → if DB insert fails, delete the file. Error messages never reveal paths.

### Decision: Consent versioning

**Why:** The wording lives in `config/consent.php` under a version key (`current='v1'`, `texts=['v1'=>'…']`). When `consent_given=1`, write `consented_at=now()` and `consent_text_version=config('consent.current')`. Rows are NOT backfilled. Published versions are never edited; to change wording, add a new version key. `/privacy` links to current AND previous wording. Withdraw-consent keeps `consented_at` and `consent_text_version` as audit.

### Decision: Soft cap on testimonials, accepted boundary race

**Why:** At 99/100, two simultaneous submissions may both pass and the Space lands at 101. PRD §11 says "never silently discard submissions", so accepting a 101st is the right side to err on. Rejected: `SELECT … FOR UPDATE` (lock contention on the hot public endpoint, held across a multi-table write). Rejected: counter cache (`spaces.testimonials_count` — drift across create/soft-delete/restore/force-delete, needs a reconciliation command, and `COUNT(*)` over an index over at most 1,000 rows is sub-millisecond).

If counting ever becomes expensive, the counter cache is additive. The cap is a soft cap, not an invariant.

### Decision: UTC-only graph bucketing, no `users.timezone`

**Why:** Simpler query. Display uses owner locale. The graph query buckets `DATE(CONVERT_TZ(submitted_at, @@session.time_zone, '+00:00'))` at the database level — wrapping the column in `CONVERT_TZ` to a fixed UTC offset makes the bucket boundary session-tz-independent (the value `submitted_at` is written as a UTC string, but a MySQL `TIMESTAMP` column is session-tz-converted on read). Zero-count buckets are filled in application code from the requested range. Retrofit (adding `users.timezone`) is not free — it rebases every historical bucket on the day it ships. The MySQL connection's `timezone` config is `+00:00` so the session timezone is UTC by default; a Pest test forces `SET time_zone = '+06:00'` mid-request and asserts the bucket keys are identical.

### Decision: `purge` log channel with `days => 14` explicit

**Why:** The retention value is part of the contract. The Laravel default is implicit, so the channel's `'days' => 14` is set **explicitly** in `config/logging.php`. There is no `stripe` channel in v1 (returns with webhooks in v2). Purge audit entries contain only row IDs and counts — no personal data.

### Decision: Trusted-proxy + `CLOUDFLARE_HEADERS_ENABLED` switch stays off

**Why:** v2 prep. If a proxy/CDN is later placed in front, the IP list is committed in config (no fetch at boot) and header trust is behind a config switch, off by default. README warns "verify ranges before deploy."

### Decision: Embed server-side clamps are the source of truth

**Why:** Client-side clamps are UX; the server is the contract. `$limit = min((int) ($embedConfig->item_limit ?? 12), 50)`. Any `?limit=N` is also clamped to 50. `show_rating=false` omits `rating` server-side (API Resource, not the client).

### Decision: `embed.js` uses `textContent` only

**Why:** Respondent content is untrusted. `innerHTML` would let `<script>alert(1)</script>` in a testimonial fire on a customer's homepage. `element.textContent` renders the string as text. Pest test: a fixture with `<script>alert(1)</script>` in the testimonial text renders the script as text, not as HTML.

### Decision: `purge:run` is explicit, not cascade-driven

**Why:** The FK cascade on `embed_configurations.space_id` does NOT fire (the Space row is kept as a tombstone). The purge job is explicit. Per Space past retention: (1) for each of its testimonials (live AND soft-deleted) run the three-step order; (2) `delete()` the `embed_configurations` row; (3) KEEP the `spaces` row (tombstone).

## Risks / Trade-offs

- **[Risk] MySQL refuses the `is_public` STORED expression** (especially `deleted_at IS NULL` inside a STORED expression) → Mitigation: Step 1's `IsPublicGeneratedColumnTest` runs first; if MySQL refuses, **stop and report the exact error to the user** — do not improvise (Hard rule 3).
- **[Risk] `php artisan storage:link` is not run before Step 3's photo tests** → Mitigation: Step 1's "Done means" includes a `readlink(public_path('storage'))` check (Hard rule 5). Without this, Step 3 photo tests fail with confusing 404s.
- **[Risk] Soft-deleted slugs are claimed forever, and Dev can't reclaim a typo'd slug** → Mitigation: Restore within 30 days (Blocked at Space limit) + tombstone policy. The README documents this. Slug mangling on delete was rejected (corrupts the slug on restore).
- **[Risk] Testimonial cap can overshoot by one** (boundary race) → Mitigation: Documented and accepted. The cap is a soft cap; never silently discard submissions.
- **[Risk] Photo pipeline memory on > 4000 px images** (Intervention Image decode before the hard reject) → Mitigation: UNVERIFIED in `data-model.md` §12. README marks it UNVERIFIED. If it bites in v2, hard-reject before `getimagesize()` reads the full image.
- **[Risk] Animated GIF re-encode produces a static first-frame webp** → Mitigation: Accepted in `data-model.md` §12. The 4000 px header check passes on the first frame; the embed shows a non-animated thumbnail.
- **[Risk] v1 embed has no `frame-ancestors` CSP** → Mitigation: Third-party sites can embed the wall anywhere. Accepted in `data-model.md` §12. v2 may add a per-Space setting.
- **[Risk] Daily-graph day-boundary errors for non-UTC owners** (e.g. Dev in Lagos UTC+1) → Mitigation: Accepted. v1 stores and buckets in UTC only; the empty-state path and display use the owner locale. Retrofit (per-user timezone column) is a v2 path. Documented in `data-model.md` §7.1.
- **[Risk] `embed.js` is served directly by the app (not behind a CDN)** → Mitigation: v1 short cache. v2 may add a CDN behind `{APP_URL}/embed.js`. Documented in PRD §8.
- **[Risk] Breeze auth throttles need to be re-implemented inside Livewire components** (not on routes) → Mitigation: Hard rule 4 + named limiters in `AppServiceProvider::boot()`. `AuthThrottleTest` exercises the action directly, not via HTTP.
- **[Risk] Forget-now order is hard to keep straight** (acted-update must run BEFORE `forceDelete()`) → Mitigation: Step 4's "Done means" includes a Pest test that asserts the order. The same order is reused by `purge:run`.
- **[Risk] Unverified third-party limits (Cloudflare, R2, Oracle, Resend, Intervention Image GD webp, PHP intl, 4000×4000 decode memory)** → Mitigation: README marks each UNVERIFIED. v1 does not depend on any of them.
- **[Risk] Cross-user authorization drift** → Mitigation: Every Inbox action and embed-builder page authorizes `$space->user_id === $user->id` (single hop). Step 4's "Done means" includes a Pest test for each of: read, edit, delete, forget, withdraw-consent, embed-build.

## Migration Plan

This is a greenfield project. The "migration plan" is the 8-step build order:

1. **Step 1** — Project setup + migrations + auth + seeder + Pest `is_public` test. Establishes the schema and the test harness. Done means: `migrate` clean on dev + test DBs; `pest` green; `storage:link` runs; Breeze routes with throttles; consent config has `v1`; `purge` log channel has explicit `days => 14`.
2. **Step 2** — Space CRUD + themes + success page + free caps + Deleted Spaces tab. Done means: create/edit/soft-delete, hit cap at 3, warning banner at 80%, restore within 30 days works, restore blocked at cap, tombstone not in tab, slug-collision probe returns suffixed slug.
3. **Step 3** — Public submission + photo pipeline + consent + lock + dedupe. Done means: anon submit works; dedupe blocks within 5 min; lock message at cap; soft-deleted slug 404; unconsented row has `is_public=0`; photo upload produces webp and rolls back on DB insert failure; `consented_at` + `consent_text_version` set when `consent_given=1`.
4. **Step 4** — Inbox + Trash + Forget + Space Restore + WoL guard. Done means: favorite sorts to top; bulk-WoL with unconsented row blocked; withdraw-consent recomputes `is_public`; Trash Restore works at cap; Forget-now closes matching `deletion_requests` and force-deletes; cross-user authorization tests.
5. **Step 5** — Dashboard + counters + graph + Deleted Spaces tab + deletion-request list. Done means: counters update; deletion-request list shows the 100-testim Space's open request; graph renders with day/week/month × 7d/30d/90d/all; Free-plan info note has the exact copy with no CTA. Total customers formula pinned with a synthetic Pest fixture (4 customers across 2 Spaces).
6. **Step 6** — Purge job + scheduler + backups + trusted-proxy config. Done means: `purge:run` works locally on a soft-deleted > 30-day testimonial; Space > 30 days becomes a tombstone; restore from tab correctly fails for tombstones; backup + restore rehearsal works.
7. **Step 7** — Embed builder + `embed.js` + public embed API + caching. Done means: snippet in a plain external HTML file renders masonry/carousel; renaming slug doesn't break it; `?limit=200` returns at most 50; soft-deleted Space returns `{"config": null, "testimonials": []}`; unknown `public_id` returns 404; ordering is `is_favorite DESC, submitted_at DESC`; CORS `*`; `Cache-Control: max-age=60`; no `Set-Cookie`; `show_rating=false` omits `rating`; `embed.js` `textContent` neutralizes `<script>` tags.
8. **Step 8** — Landing + privacy + `/privacy/request-deletion` + fixture + README + demo. Done means: fresh clone + `.env` + `php artisan migrate --seed` + `php artisan serve` → Dev's `shiplog-reviews` is at the cap (100 LIVE), Dev's "Total customers" = 111, Dev's `shiplog-reviews` embed JSON has 21 items (Priya + 20 generated WoL), Dev's `beta-testers` embed JSON has 12 items (Priya + 11 generated), dashboard shows the open deletion request, Forget-now on Sara's testimonial marks the request `acted`, `purge:run` runs cleanly, restore-at-cap works, WoL guard works, cross-user authorization tests pass.

**Rollback:** Greenfield. If a step's "Done means" fails, the change is reverted to the previous passing step's `git` tag.

## Open Questions

- None. The `is_public` STORED expression, the slug-check route, the withdraw-consent method, the `{config, testimonials}` response shape, the photo path, and the 5-tries/15-min limit are all pinned in `final PRD.md` v2.5, `data-model.md` v2.5, and `build order.md` v2.6. The only UNVERIFIED items (Cloudflare, R2, Oracle, Resend, Intervention Image GD webp, PHP intl, 4000×4000 decode memory) are explicitly out of v1 scope and are documented in the README — they do not change the approach or the task breakdown.
