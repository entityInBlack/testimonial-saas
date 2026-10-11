# Build Order v2.6 — Free plan only (Stripe / Cashier / Pro removed from v1)

**Source documents (authoritative):**
- `final PRD.md`
- `data-model.md`
- `decision_log.md`
- `project building continuation file.md`

**v1 scope:** Free plan only. No Stripe, no Cashier, no Pro plan, no webhooks, no `/billing`, no `Plan` enum, no `subscribed('default')`, no `stripe_events` table, no `stripe` log channel. Limits are hardcoded in `config/limits.php`. Real email provider, real storage, real domain, real host — all v2 future paths.

---

## Stack rules (locked)

- **Laravel 11** only (`^11.0`).
- **Livewire 3** + Blade. No Inertia, no separate JS framework.
- **Breeze** (Livewire stack). `MustVerifyEmail` is OFF in v1. `email_verified_at` column kept.
- **MySQL 8** only (separate dev and test DBs).
- **No Cashier, no Stripe, no `Billable` trait, no Stripe columns on `users` (`stripe_id`, `pm_type`, `pm_last_four`, `trial_ends_at`).**
- File storage: **`public` disk** in v1 (served from `storage/app/public` via `php artisan storage:link`). Flat path `photos/{random}.webp`. Intervention Image. `photo_url = Storage::disk('public')->url($path)`. The `storage:link` step is documented in the README.
- Mail: **log mailer** in v1.
- Charts: **Chart.js via CDN**.
- Auth throttles: per-IP and per-email for login, forgot-password, reset, signup (Breeze default for some; we add the rest).
- Trusted-proxy + `CLOUDFLARE_HEADERS_ENABLED` config switch exists in v1, **off by default** (v2 prep).
- `purge` log channel with `'days' => 14` **explicit** in `config/logging.php`. No `stripe` channel in v1.
- All third-party limits marked **UNVERIFIED** in README.

---

## Hard rules (don't break these)

These are decisions that any build agent or human implementer must follow without re-deriving. Each rule names *what* and *where it lives*; the rationale is in the section referenced.

1. **No Cashier, ever.** Not in Step 1, not in any step. No `composer require laravel/cashier`, no `Billable` trait on `User`, no `subscriptions` / `subscription_items` migrations, no `php artisan cashier:install`. If a prompt or scaffold suggests it, reject it. (See rerise Addendum A1, Fix 15.)
2. **No `/billing` route, no `Plan` enum, no `subscribed('default')`, no upgrade CTA, no upgrade banner, no `STRIPE_*` env key, no `stripe` log channel.** Hardcoded Free limits (`config/limits.php` → 3 spaces / 100 testimonials) are the source of truth. (See rerise Addendum A1, A2.)
3. **`is_public` is a STORED generated column.** The exact `CASE WHEN ... THEN 1 ELSE 0 END` expression in Step 1 is canonical. No accessor, no observer-maintained mirror, no fallback expression. If MySQL refuses the expression, **halt and report the exact error to the user** — do not improvise. (See Step 1 "is_public expression (exact, no improvisation)".)
4. **Auth throttles live INSIDE Livewire component actions, not on routes.** Breeze forgot-password / reset-password / register are Volt/Livewire components; route-level `throttle` middleware cannot see the action body. Use `RateLimiter::tooManyAttempts` / `hit` in the component, with two keys (per-IP and per-email+IP), 5 attempts per 15 minutes. Login keeps Breeze's `ThrottlesLogins`. (See Step 1 "Auth throttles".)
5. **`storage:link` is a Step 1 deliverable.** Photos live on the `public` disk; without the symlink, Step 3 photo tests fail with confusing 404s. Add `readlink(public_path('storage')) !== false` to Step 1's "Done means" check.
6. **No physical file deletion on soft-delete.** Soft-deleted testimonials and Spaces keep their photo files. Only `purge:run` (after `config('purge.retention_days')` = 30 days) deletes files. The three-step order is fixed: close matching `deletion_requests` → delete photo → `forceDelete`. (See Step 2 soft-delete rule, Step 4 Forget-now, Step 6 purge.)
7. **Withdraw-consent is a Livewire method, not a POST route.** `InboxRow::withdrawConsent(Testimonial $t)` is called by the Livewire component directly. No controller-backed route — that would duplicate the authorization logic. Method sets `consent_given = 0` AND `is_wall_of_love = 0`; `consented_at` and `consent_text_version` are kept as audit. (See rerise D35, Step 4.)
8. **Embed API is in `routes/web.php` OUTSIDE the `web` middleware group.** No session, no cookie, no CSRF. Throttle `120/min` per IP. Laravel 11's default `config/cors.php` already allows `Access-Control-Allow-Origin: *` for `api/*` paths — that is the route family the test exercises. Cache `max-age=60`. (See Step 7 "Public API endpoint".)
9. **Embed server-side clamps are the source of truth.** `$limit = min((int) ($embedConfig->item_limit ?? 12), 50)`. A valid `?limit=N` (positive integer) overrides the saved `item_limit` for that request and may raise or lower it. The effective limit is `min(?limit if valid else item_limit, 50)`. An invalid `?limit` (0, negative, non-numeric, decimal) is ignored. `show_rating=false` omits the `rating` field server-side (API Resource, not the client). Embed JSON order is `is_favorite DESC, submitted_at DESC` — same rule as the Inbox; do not change one without changing the other. (See Step 7.)
10. **Eloquent API Resource whitelist for embed responses.** Only `name`, `testimonial`, `rating` (when `show_rating=true`), `submitted_at`, `company_name`, `social_url`, `photo_url` (full URL from `profile_photo`). **Never** `email`, `address`, or internal ids. (See Step 7 "Eloquent API Resource whitelist".)
11. **`embed.js` renders text with `textContent` only.** No `innerHTML`, no template literals that interpolate user content. Photo shown iff `profile_photo IS NOT NULL` — no "show photo" toggle. (See Step 7 "embed.js".)
12. **`CLOUDFLARE_HEADERS_ENABLED` is OFF by default.** The config switch exists for v2 prep only. README must warn "verify ranges before deploy." (See Step 1 trusted-proxy config.)
13. **Photo pipeline re-encodes to webp at ~400 px wide and rejects > 4000 px on either side before decoding.** Random filename is generated before save; original filename is never stored. If DB insert fails, delete the file. Re-encode destroys EXIF and any hidden payload. (See Step 3 photo pipeline.)
14. **The 1 open deletion request seeded in Step 8 for Sara has `testimonial_id = null` on purpose.** It exercises the "match by email" path in Forget-now. Do not "fix" it to a real id. (See Step 8 fixture.)
15. **Anytime the build reveals a deviation from PRD v2.5 / data-model v2.5, update all three documents (PRD, data model, this build order) in the same change.** Drift is the failure mode; silence is not a fix. (See `## Deviations from data-model.md / PRD.md`.)

---

## The 8 steps

### Step 1 — Project setup + migrations + auth + seeder + Pest is_public test

**Install**
- Laravel `^11.0` via `composer create-project`.
- Livewire `^3.0`.
- Breeze (Livewire stack, **decline email verification**).
- Pest `^3.0` + `pest-plugin-laravel`; run `./vendor/bin/pest --init`. Remove default PHPUnit example tests.

**Environment**
- MySQL 8 only. `.env.example` rewritten for MySQL (no hardcoded credentials).
- Separate DBs: `testimonial_saas` (dev) and `testimonial_saas_test` (test). `RefreshDatabase` uses the test one.

**Tables (one migration each)**
| Table | Source | Key bits |
|---|---|---|
| `users` | Laravel/Breeze default + `email_verified_at` | No Cashier columns |
| `password_reset_tokens` | Laravel default | — |
| `sessions` | Laravel default | — |
| `failed_jobs` | Laravel default | DB queue driver |
| `spaces` | data model §3.2 | `slug` unique, `public_id` char(12) unique, `theme` enum, `field_config` JSON, `deleted_at` soft delete, indexes `unique(slug)`, `unique(public_id)`, `index(user_id, deleted_at)` |
| `testimonials` | data model §3.3 | Includes `consented_at` + `consent_text_version` (canonical, NOT deviations). `is_public` STORED generated using the exact data-model expression |
| `embed_configurations` | data model §3.4 | `space_id` unique FK. No `display_options`. `item_limit` default 12 |
| `deletion_requests` | data model §3.6 | `space_id` nullable FK nullOnDelete, `testimonial_id` nullable FK nullOnDelete, `status` enum `open\|acted`, `space_slug` retained as typed |

**`is_public` expression (exact, no improvisation)**
```php
$table->boolean('is_public')->storedAs(
    'case when `consent_given` = 1 and `is_wall_of_love` = 1
           and `is_hidden` = 0 and `deleted_at` is null
           then 1 else 0 end'
);
```
After migrate, verify via `information_schema.columns` that `extra` includes `STORED GENERATED`. **If MySQL refuses the expression (especially `deleted_at IS NULL` inside a STORED expression), stop and report — do not improvise a fix.**

**`testimonials` indexes** — `idx_public` (`space_id`, `is_public`, `is_favorite`, `submitted_at`); `idx_space_live` (`space_id`, `deleted_at`, `submitted_at`); `idx_respondents` (`space_id`, `deleted_at`, `email`).

**Config files**
- `config/limits.php`: `['max_spaces' => 3, 'max_testimonials_per_space' => 100]`.
- `config/consent.php`:
  ```php
  return [
      'current' => 'v1',
      'texts' => [
          'v1' => 'I give permission for this testimonial (my name, my message, and — if I provided them — my photo, social URL, and company) to be displayed publicly on the testimonial wall and embedded on third-party sites. I can withdraw consent and request deletion at any time.',
      ],
  ];
  ```
  Rule: never edit a published version. Add a new key to change wording. The Pest test asserts the version constant, not the text.
- `config/logging.php`: add `purge` channel with `'days' => 14` **explicit**.
- `config/purge.php` (new): `retention_days` (env `PURGE_RETENTION_DAYS`, default 30), the single source of truth for the purge retention window; `batch_size` (env `PURGE_BATCH_SIZE`, default 50), the chunk size for the purge queries; `soft_warning_days` (env `PURGE_SOFT_WARNING_DAYS`, default 5), defined but not read by any code in v1. The purge job reads `retention_days` and `batch_size` only.
- Trusted-proxy + `CLOUDFLARE_HEADERS_ENABLED` config switch (off by default).
- `config/cors.php`: Laravel 11's default already allows `Access-Control-Allow-Origin: *` for `api/*` paths. No custom paths needed for v1 — the embed endpoint is at `/api/spaces/{public_id}/testimonials`. A Pest test sends an `Origin` header and asserts the response header.

**Models**
- `User` (no `Billable` trait), `Space`, `Testimonial` (`scopePubliclyVisible()` → `where('is_public', true)`), `EmbedConfiguration`, `DeletionRequest`.
- `Testimonial` casts include `consent_given`, `is_favorite`, `is_wall_of_love`, `is_hidden`, `is_public` → bool; `submitted_at`, `consented_at` → datetime.
- **No `is_public` accessor** on `Testimonial` — the generated column is the truth.

**Seeders** — Maya, Dev, Priya, Ben (all Free per PRD §15). No spaces/testimonials yet.

**Pest tests (required to pass)**
- `IsPublicGeneratedColumnTest` — 5 rows (Priya=1, Tom=0, Sara=0, Ben=0, Ana=0). Row 5 inserted normally, then `$row->delete()` (soft-delete), then **reloaded via `Testimonial::withTrashed()->find($row->id)`**. Each `is_public` value read directly from `DB::table(...)->value('is_public')`, not from Eloquent's in-memory copy.
- `DatabaseSchemaTest` — asserts `idx_public`, `idx_space_live`, `idx_respondents` exist on `testimonials` (via `SHOW INDEX FROM testimonials`), plus `unique(slug)` and `unique(public_id)` on `spaces`. Also asserts `testimonials.is_public` is STORED generated.
- `ConsentConfigTest` — `config('consent.current') === 'v1'`, `'v1'` exists in `config('consent.texts')`.
- `AuthThrottleTest` — call the forgot-password, reset-password, and register Livewire actions 6 times in 15 minutes from the same IP and same email+IP. The 6th call returns the validation error "Too many attempts. Try again later." (not an HTTP 429, since the throttle is inside the action, not on a route).

**Auth throttles** — Breeze ships forgot-password, reset-password and register as **Volt/Livewire components**, not POST routes. Route-level `throttle` middleware does **not** see the action body, so rate limiting is enforced **inside the component action**:
```php
RateLimiter::for('forgot-password', function ($request) {
    return Limit::perMinutes(15, 5)->by($request->ip());
});
RateLimiter::for('forgot-password-email', function ($request) {
    return Limit::perMinutes(15, 5)->by(strtolower($request->input('email')) . '|' . $request->ip());
});
// (same shape for reset-password and register)
```
At the start of each action, call:
```php
if (RateLimiter::tooManyAttempts($key1, 5) || RateLimiter::tooManyAttempts($key2, 5)) {
    throw ValidationException::withMessages(['email' => 'Too many attempts. Try again later.']);
}
RateLimiter::hit($key1, 15 * 60);
RateLimiter::hit($key2, 15 * 60);
```
Define the named limiters in **`AppServiceProvider::boot()`** (Laravel 11 has no `RouteServiceProvider`). Login keeps Breeze's built-in `ThrottlesLogins` (per email + per IP).

**Done means**
- `php artisan migrate` clean against dev DB.
- `php artisan migrate --env=testing` clean against test DB.
- `php artisan test` passes all four test files.
- `php artisan route:list` shows Breeze routes with throttle middleware applied.
- `storage/logs/laravel.log` captures a password-reset email after a forgot-password POST.
- `php artisan storage:link` has been run; `readlink(public_path('storage'))` resolves to `../storage/app/public`. (Step 3 photo tests depend on this — without it, photo URLs 404.)

---

### Step 2 — Space CRUD + themes + success page + free caps + Deleted Spaces tab

- `/spaces` CRUD. Form: **name (default = title)**, **Slug** — max 60 chars, auto-suggested from title (kebab-case), editable. Reserved list (defensive — return form error if matched): `s`, `api`, `admin`, `login`, `register`, `dashboard`, `billing`, `embed`, `assets`, `up`. On collision: the database `unique(slug)` index is the source of truth. The create form MUST probe the slug-check endpoint **`GET /spaces/check-slug?slug=X`** (auth required; lives in `routes/web.php`; see `final PRD.md` §5) which returns `{available: bool, suggested: string|null}`. Probe uses `Space::withTrashed()` (so soft-deleted and tombstoned slugs count as taken), checks the reserved list, and trims any appended `-N` suffix so the final slug is at most 60 chars. The form surfaces "slug already taken" and offers a suffixed slug (`slug-2`, `slug-3`, …). User can accept or edit.
- title, subtitle, ask, `rating_enabled`, `theme` (`minimal_light`/`minimal_dark`/`soft_color`), `field_config` toggle UI for `{enabled, required}` per field — default only `company_name.enabled=true`.
- `public_id` auto-generated 12-char on insert, **immutable** (no setter exposed).
- Cap check on `SpaceController@store`: live count >= `config('limits.max_spaces')` → graceful "delete a Space or contact support" notice. **No upgrade CTA in v1.**
- Warning banner at >= 80% cap.
- Soft-delete sets `deleted_at`. Slug stays claimed forever. **No file deletion on soft-delete** (purge job is Step 6).
- **Deleted Spaces tab** — Spaces deleted < 30 days ago. **Restore** action:
  - Allowed within 30 days of `deleted_at`.
  - Blocked if owner is already at Space limit → "delete a Space first" notice.
  - Tombstoned Spaces (> 30 days, after Step 6 purge runs) cannot be restored; not listed.
- Success page after create: public URL `/s/{slug}` with **Copy Link** + **Go to Dashboard**.

**Done means** — create/edit/soft-delete a Space, hit cap at 3 Spaces (Free), warning banner shows at 80%, restore within 30 days works, restore blocked at cap, tombstoned Space not in tab. **Submitting a slug that collides with a live OR tombstoned Space OR matches the reserved list returns form errors with a `-2`/`-3` suggestion; no 500; no DB write.**

---

### Step 3 — Public submission + photo pipeline + consent + lock + dedupe

- Route `/s/{slug}` (no login). Renders space's theme.
- Form fields: **name** (required, max 120), **email** (required, `email:rfc`, max 180), **address** (required, max 255), **testimonial** (required, max 2000, trimmed, zero-width chars `U+200B-U+200D`, `U+FEFF` stripped, reject whitespace-only/emoji-only/zero-width-only), **consent_given** (optional, default false), `company_name` / `social_url` / `profile_photo` per `field_config`, **rating** (1..5) when `rating_enabled`. **Honeypot** field. Validation in **one FormRequest**.
- **Consent:** when `consent_given = 1`, set `consented_at = now()` and `consent_text_version = config('consent.current')`. Wording comes from `config('consent.php')`. Withdraw consent is Step 4.
- **Throttles:** 5 submissions/hour/IP/space + 5 submissions/hour/email/space. Trusted-proxy config (Step 1's switch) honored so one proxy IP doesn't lock everyone.
- **Photo pipeline:** `getimagesize()` header check → MIME `jpg|png|webp` only → 5 MB cap → **hard-reject > 4000 px on either side** → Intervention Image re-encode to **webp ~400 px wide** → **flat path `photos/{random}.webp` on the `public` disk** → random name generated **before** saving → if DB insert fails, **delete the file from the `public` disk** → error messages never reveal paths. `php artisan storage:link` is run in Step 1; documented in the README. `photo_url = Storage::disk('public')->url($path)`. Pest test: a freshly uploaded photo gets a URL that returns `200` from the local server.
- **Lock:** when Space is at testimonial cap, show **"This Space is not accepting responses right now."** No silent accept. Cap from `config('limits.max_testimonials_per_space')`.
- **Dedupe:** same email + same testimonial text + same space within 5 minutes returns thank-you only, doesn't save, doesn't count.
- **Unknown or soft-deleted slug → 404.**
- Funny confirmation message after success. **No real email in v1.**

**Done means** — anon submit (incognito) works; dedupe blocks within 5 min; lock message appears at cap; soft-deleted slug returns 404; unconsented row stored with `is_public = 0`; photo upload produces webp + rolls back if DB insert fails; `consented_at` + `consent_text_version` set when `consent_given = 1`.

---

### Step 4 — Inbox + Trash + Forget + Space Restore + WoL guard

- `/inbox` (auth) with Space dropdown. **Live Spaces only.**
- Filters: All / Favorites / Wall of Love / Hidden. Sort `is_favorite DESC, submitted_at DESC`. Hidden items stay in inbox, flagged.
- **Trash** is its own tab.
- Per-row actions: **Favorite** (toggle), **Wall of Love** (**blocked while `consent_given = 0`, including bulk**), **Hide/Unhide**, **Edit** (testimonial text + rating, **no original kept, no "edited" marker**), **Withdraw consent** — Livewire method **`InboxRow::withdrawConsent(Testimonial $t)`** on the inbox row component. **No POST route** (Livewire calls the method directly; adding a controller-backed route would duplicate the authorization logic). Method: authorize `$space->user_id === $user->id`; set `consent_given = 0` **and** `is_wall_of_love = 0`; KEEP `consented_at` and `consent_text_version` unchanged as audit. The `is_public` STORED column recomputes automatically. No email; no row delete., **Delete** (soft).
- **Trash Restore** — clears `deleted_at`. `is_public` STORED recalculates automatically. Flags come back as they were. **Allowed even when Space is at testimonial cap.**
- **Forget now** (Trash only) — one confirm dialog showing the testimonial name (no typing). Livewire method `forget(Testimonial $t)` on `InboxRow`. **Three-step order, fixed:**
  1. **UPDATE matching `deletion_requests` to `acted`** FIRST so the `testimonial_id` FK is still populated:
     ```sql
     UPDATE deletion_requests
     SET status = 'acted', acted_at = NOW()
     WHERE space_id = :space_id
       AND status = 'open'
       AND (testimonial_id = :testimonial_id
            OR (testimonial_id IS NULL AND email = :testimonial_email));
     ```
  2. **DELETE the photo file** from the `public` disk (`Storage::disk('public')->delete($path)`). Ignore only "file not found"; any other error keeps the row for the next run.
  3. **Force-delete the testimonial row** (`$t->forceDelete()`).
- Card shows: name, email, address, company (if set), photo (if set), rating, testimonial text, submitted_at, **consent badge**.
- Pagination 20/page.

**Done means** — favorite sorts to top; bulk-WoL with an unconsented row in selection is blocked; **Withdraw-consent sets `consent_given = 0` **and** `is_wall_of_love = 0`; `consented_at` and `consent_text_version` keep their pre-submit values; the row's `is_public` STORED value recomputes (verified by a Pest test).** Trash Restore works at cap; **Forget-now on a testimonial with a matching open `deletion_requests` row sets that request to `status='acted'` with `acted_at` set; photo file removed from the `public` disk; testimonial row hard-deleted.** **Cross-user authorization:** user B cannot read, edit, delete, forget, withdraw-consent, or embed-build user A's data (Pest test for each).

---

### Step 5 — Dashboard + counters + graph + Deleted Spaces tab + deletion-request list

- `/dashboard` (auth). Counters (**live Spaces only**):
  - **Total testimonials**
  - **Total customers** (label exactly "Total customers"; `COUNT(DISTINCT email)`)
  - **Spaces used** (X of `config('limits.max_spaces')`)
  - **Average rating** (non-null ratings only)
  - **Open deletion requests count** (for owner's live Spaces)
- **Total customers formula, pinned:** `SELECT COUNT(DISTINCT email) FROM testimonials JOIN spaces ON ... WHERE spaces.user_id = :owner_id AND spaces.deleted_at IS NULL AND testimonials.deleted_at IS NULL`. Soft-deleted Spaces are excluded; soft-deleted testimonials inside a live Space are excluded. Same email across two of the owner's Spaces is counted **once**. This formula is what produces Dev's "Total customers = 111" once Step 8's fixtures are loaded (100 live in `shiplog-reviews` + 12 live in `beta-testers` − 1 for Priya counted once across both Spaces; Ana's soft-deleted row is not counted). **A Pest test in Step 5 must assert the formula on a small synthetic fixture (e.g. owner with 2 Spaces, 3 testimonials in Space A, 2 in Space B, 1 of the 3 in Space A shares an email with 1 in Space B → Total customers = 4) so the off-by-one is caught here, not in Step 8.**
- **Open deletion-requests list** — matched to owner's **live** Spaces by `space_id`. Requests whose `space_id` points at a soft-deleted Space are **not** shown until the owner restores the Space. (The README notes this so it is not rediscovered as a bug.)
- **Deleted Spaces tab** — list Spaces deleted < 30 days ago with Restore action (same widget as Step 2; redundant UX link here is fine).
- **Time-series graph:** granularity `day | week | month`, range `7d | 30d | 90d | all time`, optional space filter. **UTC bucketing** at DB level. Display in owner's locale. Zero-count days filled in app code. Chart.js via CDN.
- **Free-plan info note** — non-actionable informational line in the dashboard header (above the counter cards, below the page title). Exact copy:
  > You're on the Free plan (3 Spaces, 100 testimonials per Space).
  No CTA. No `/billing` link. No upgrade banner. Returns with Pro in v2. **Hard rule: there is no `/billing` route in v1; this note must not link anywhere.**
- Empty states: no spaces → "Create your first Space"; no testimonials → "Share your link to start collecting."

**Done means** — counters update; deletion-request list shows the 100-testim Space's open request; graph renders with day/week/month + 7d/30d/90d/all; switching ranges updates the X axis. **Dashboard header shows the Free-plan info note with the exact copy above; no link, no button, no CTA; the note only renders when the authenticated user owns at least one live Space.**

---

### Step 6 — Purge job + scheduler + backups + trusted-proxy config

- Scheduled command `purge:run` runs nightly with `withoutOverlapping`.
- **Config keys:** `config('purge.retention_days') = 30` (retention window) and `config('purge.batch_size') = 50` (chunk size for the purge queries). `soft_warning_days` (default 5) is also in `config/purge.php` but is not read by any code in v1.
- **Tombstone Spaces are never hard-deleted**, so the FK cascade on `embed_configurations.space_id` does **not** fire. The purge job is **explicit**, not cascade-driven.
- Per testimonial soft-deleted past retention: **three-step order, fixed** — (a) UPDATE matching `deletion_requests` to `status='acted'`, `acted_at = now`; (b) delete photo file from the `public` disk (ignore only "file not found"; any other error keeps the row for the next run); (c) `forceDelete()` the row.
- Per Space soft-deleted past retention, in order:
  1. For each of its testimonials (live **and** soft-deleted): run the three-step order above (close matching `deletion_requests` → delete photo → `forceDelete`).
  2. **Delete the `embed_configurations` row for that Space explicitly** (cascade does not fire — the Space row is kept).
  3. **KEEP the Space row (tombstone)** so the slug stays claimed.
- Audit goes to `purge` log channel, daily, `'days' => 14` **explicit** in `config/logging.php`. Row IDs and counts only — no personal data.
- Scheduler cron line documented in README.
- Nightly `mysqldump`-style backup + tested restore runbook.
- All third-party limits in README marked **UNVERIFIED** (Cloudflare, R2, Oracle Cloud Always Free, Resend domain, Intervention Image GD webp, PHP intl, 4000×4000 decode memory).
- CLOUDFLARE_HEADERS_ENABLED switch stays off by default. README warning: "verify ranges before deploy."

**Done means** — `php artisan purge:run` works locally on a soft-deleted > 30-day testimonial; Space > 30 days becomes a tombstone; restore from tab correctly fails for tombstones; backup + restore rehearsal works on a local DB. **After `php artisan purge:run` on a Space soft-deleted > 30 days: testimonials + photos + `embed_configurations` row are gone; the `spaces` row remains with `deleted_at` set and the original unique slug claimed forever.**

---

### Step 7 — Embed builder + embed.js + public embed API + caching

- `/spaces/{space}/embed` builder. Options (read from `embed_configurations`):
  - **layout** `masonry | carousel`
  - **dark_mode** toggle
  - **animation_enabled** toggle
  - **background_color** hex (`#RRGGBB`)
  - **show_rating** toggle
  - **item_limit** (default 12, **capped at 50**)
- **Server-side clamp:** in the embed API controller, do `$limit = min((int) ($embedConfig->item_limit ?? 12), 50)`. A valid `?limit=N` (positive integer) overrides the saved `item_limit` for that request and may raise or lower it. The effective limit is `min(?limit if valid else item_limit, 50)`. An invalid `?limit` (0, negative, non-numeric, decimal) is ignored. The embed builder UI clamps to 50 client-side too; the server clamp is the source of truth.
- **No "Source" option. No "Show avatar/photo" toggle.** Photo shown iff `profile_photo IS NOT NULL`.
- **Live preview** updates on every change using `scopePubliclyVisible()` real rows.
- Output snippet:
  ```html
  <div data-testimonial-space="{public_id}"></div>
  <script src="{APP_URL}/embed.js" async></script>
  ```
  Uses `public_id` (immutable). Renaming slug does not break the embed.
- `embed.js`: **vanilla, dependency-free, Shadow DOM or iframe isolation**, fetches `GET /api/spaces/{public_id}/testimonials`. Served at permanent URL `{APP_URL}/embed.js` with short cache in v1. **All text rendering uses `element.textContent` (never `innerHTML`)** to avoid XSS from respondent content.
- **Public API endpoint:** registered in `routes/web.php` **outside** the `web` middleware group (no session, no cookie, no CSRF). Apply a dedicated throttle of `120/min` per IP via `throttle:120,1`. Lookup Space **including soft-deleted rows**; branch in code:
  - Unknown `public_id` → **404**
  - Soft-deleted → **200** `{"config": null, "testimonials": []}`
  - Live → **200** `{"config": {…}, "testimonials": […]}` where `config` is the six option keys (`layout`, `dark_mode`, `animation_enabled`, `background_color`, `show_rating`, `item_limit`) taken from the saved row or from the defaults in `config/embed.php` when no row exists; never `id`, `space_id` or timestamps.
- **Server-side clamp:** `$limit = min((int) ($embedConfig->item_limit ?? 12), 50)`. A valid `?limit=N` (positive integer) overrides the saved `item_limit` for that request and may raise or lower it. The effective limit is `min(?limit if valid else item_limit, 50)`. An invalid `?limit` (0, negative, non-numeric, decimal) is ignored. The embed builder UI clamps to 50 client-side too; the server clamp is the source of truth.
- **Server-side filter:** when `embed_configurations.show_rating = 0`, the API **omits** `rating` from every testimonial item (do not rely on the client to hide it).
- **Ordering:** `ORDER BY is_favorite DESC, submitted_at DESC`. This is the same firmware rule as the Inbox (PRD §7). Do NOT order by `id`, `created_at`, or anything else.
- Eloquent API Resource whitelist: `name`, `testimonial`, `rating` (only when `show_rating=true`), `submitted_at`, `company_name`, `social_url`, `photo_url` (full URL built from `profile_photo`). **NEVER projects `email`, `address`, or internal ids.** `social_url` is returned only when its URL scheme is http or https (case-insensitive); otherwise it is `null`.
- **CORS open** (`Access-Control-Allow-Origin: *`). Laravel 11's default `config/cors.php` already allows `*` for `api/*` paths — covers `/api/spaces/{public_id}/testimonials`. **Pest test:** send an `Origin` header and assert the response carries `Access-Control-Allow-Origin: *`. **Cache headers:** `Cache-Control: public, max-age=60`. **Pest test:** assert the response has no `Set-Cookie` header (route is outside the `web` middleware group) and has `Cache-Control: public, max-age=60`.
- **Pest test (Firmware rule):** with two testimonials having the same `submitted_at` but different `is_favorite` values, the favorite appears first in the embed JSON.
- Existing photos still shown on embed if `profile_photo` is later disabled in `field_config` (display-time check, not a data purge).

**Done means** — snippet in a plain external HTML file renders masonry/carousel; renaming slug doesn't break it; **an embed API call with `?limit=200` returns at most 50 testimonials; the JSON array length matches the clamped limit; no exception is thrown.** Soft-deleted Space returns `{"config": null, "testimonials": []}`; unknown `public_id` returns 404; **with two testimonials having the same `submitted_at` but different `is_favorite` values, the favorite appears first in the embed JSON (verified by a Pest test).** Email/address never in JSON. **CORS test:** response carries `Access-Control-Allow-Origin: *` when `Origin` header is sent. **Caching test:** response has `Cache-Control: public, max-age=60` and **no** `Set-Cookie` header. **`show_rating=false` test:** every item in the response omits `rating`. **`embed.js` textContent test:** a fixture with `<script>alert(1)</script>` in the testimonial text renders the script as text, not as HTML.

---

### Step 8 — Landing + privacy + /privacy/request-deletion + fixture + README + demo

- **Public `/` landing** — hero, Free plan info, features, CTA to register.
- **`/privacy` static page** — what is collected, why, how long, how to request removal. Links to **current AND previous** versions of consent wording from `config/consent.php`.
- **`/privacy/request-deletion` form** — `email`, `space_slug`, optional `testimonial_id`, honeypot. Throttled 5/hr/IP. Writes `deletion_requests` row (server-side: `space_id` resolved from `space_slug` via `Space::withTrashed()`; if no Space matches, `space_id = null`; if `testimonial_id` is given, it is accepted only if it belongs to the resolved Space; `space_slug` stored as typed). Same thank-you page in every case — the form never reveals whether an ID exists.
- **Fixture seeders (exact, all users on Free):**
  - **Maya Sharma** (`maya@brightcopy.co`) → `brightcopy-wins` (6 testimonials), `course-launch` (3 testimonials).
  - **Dev Okafor** (`dev@shiplog.io`) → `shiplog-reviews` (**100 LIVE testimonials, exercises cap**), `beta-testers` (12 LIVE testimonials).
  - **`shiplog-reviews` named rows (live, ordered first):**
    - **Priya Raman** (`priya@nimbus.dev`) — `consent_given=1`, `is_wall_of_love=1`, public (`is_public=1`).
    - **Ben Fischer** — `consent_given=1`, `is_wall_of_love=1`, `is_hidden=1` → `is_public=0`.
    - **Tom Alvarez** — `consent_given=1`, `is_wall_of_love=0` → `is_public=0`.
    - **Sara Nkemi** — `consent_given=0` → `is_public=0`.
    - **Ana Ruiz** — `consent_given=1`, `is_wall_of_love=1`, soft-deleted (`deleted_at` set). Counts as not-live; not counted toward the 100.
  - The other **96 live rows** are generated with `consent_given=1`; the **first 20** of those 96 have `is_wall_of_love=1`.
  - **`beta-testers` named row:** **Priya Raman** (`priya@nimbus.dev` — **same email as in `shiplog-reviews`**, `consent_given=1`, `is_wall_of_love=1`, public). 11 generated live rows. (No tombstone in `beta-testers`.)
  - **1 open deletion request** seeded on `shiplog-reviews`: Sara's email, `testimonial_id = null`, `status='open'`.
- **README:**
  - Local MySQL 8 setup
  - `.env.example` rewritten for MySQL
  - Log mailer note
  - Intervention Image check (`gd_info()` writes WebP)
  - Scheduler cron line
  - `purge:run` documentation
  - mysqldump-style backup + restore rehearsal
  - **All third-party limits marked UNVERIFIED** (Cloudflare, R2, Oracle Cloud Always Free, Resend domain, PHP intl, 4000×4000 decode memory)
  - **CLOUDFLARE_HEADERS_ENABLED warning** (off; verify ranges before deploy)
  - **"Revisit before launch" list** (email verification, real email, Pro/Stripe, real CDN, load test, legal review)
- **Plain HTML demo page** (`public/demo-embed.html`) that pastes the snippet for any Space — proves the embed works on an external page.
- **Polish:** empty states, 403/404/500, Livewire loading states.

**Done means** — fresh clone + `.env` + `php artisan migrate --seed` + `php artisan serve` → a working demo where:
- Dev's `shiplog-reviews` Space is at the testimonial cap (100 LIVE testimonials; Ana's soft-deleted row keeps the live count at 100, not 101).
- **Dev's "Total customers" counter = 111** (100 live in `shiplog-reviews` + 12 live in `beta-testers` − 1 for Priya counted once across both Spaces; Ana's soft-deleted row is **not** counted).
- Dev's `shiplog-reviews` embed JSON includes Priya (WoL) + the **20 generated WoL rows** = 21 items, `is_favorite DESC, submitted_at DESC`. The cap of 50 is not hit. **Excludes** Ben (`is_hidden`), Tom (no WoL), Sara (no consent), and Ana (soft-deleted).
- Dev's `beta-testers` embed JSON includes Priya + 11 generated rows.
- Dashboard shows the open deletion request (Sara's email, `testimonial_id = null`).
- Forget-now on Sara's testimonial marks the request `acted` with `acted_at` set (Pest test).
- `purge:run` runs cleanly on a soft-deleted > 30-day testimonial and on a soft-deleted > 30-day Space.
- Restore-at-cap works; WoL guard works; deletion-request form writes a row with `withTrashed` resolution.
- Cross-user authorization tests: user B cannot read/edit/delete/forget/withdraw/embed-build user A's data (Pest test).

---

## v2 future path (NOT built in v1)

PRD §16 lists what ships in v2: Stripe + Pro plan, real domain + host with scheduler, real email provider, `MustVerifyEmail`, real file storage + CDN, production-grade backups + restore rehearsal, error tracking, legal review, security review, load test. None of these need build prompts in v1.

---

## Resolved in v2.5 (logged in D31, D34–D42)

- **G1 — Step 2 slug-check endpoint:** `GET /spaces/check-slug?slug=X` is the canonical name in `routes/web.php`. No `/api/` prefix. Probe uses `Space::withTrashed()` and trims to 60 chars. Resolved in v2.4 (D31), refined in v2.6 (D34).
- **G2 — Step 4 Withdraw-consent:** `InboxRow::withdrawConsent(Testimonial $t)` is the canonical name. **No POST route** (Livewire calls the method directly; a controller-backed route would duplicate the authorization logic). Method also sets `is_wall_of_love = 0`. Resolved in v2.6 (D35).

---

## Deviations from data-model.md / PRD.md

**None.** Both v2.5 docs agree on every high-impact decision (Free-only v1, exact `is_public` expression, `consented_at` + `consent_text_version` canonical, `deletion_requests` shape, no EAV, soft-delete with 30-day purge, restore rules, embed endpoint branching, withdraw-consent as a method only, embed response `{config, testimonials}` shape, `public` disk + `storage:link`). The build order above follows the PRD §15 sequence with Restore + Forget + deletion-request work folded into the relevant steps (2/4/8) rather than a separate step.

---

## What changed in v2.6

- **Step 1:** Auth throttles for forgot-password, reset-password, and register are now enforced **inside the Livewire component action** via `RateLimiter::tooManyAttempts` / `hit` (Breeze components, not POST routes). Two keys per IP and per email+IP. Limit: 5 attempts per 15 minutes. Named limiters in `AppServiceProvider::boot()`. `AuthThrottleTest` replaces `PasswordResetRateLimitTest`.
- **Step 1:** New `config/purge.php` (`retention_days = 30`, `batch_size = 50`, `soft_warning_days = 5`; the last one is not read by any code in v1).
- **Step 1:** `config/cors.php` default covers `api/*` paths with `Access-Control-Allow-Origin: *` (no custom paths).
- **Step 1:** `public` disk + `php artisan storage:link` documented as the file storage for v1 (was "local disk" in v2.5; clarified per D37).
- **Step 2:** Slug-check probe uses `Space::withTrashed()`, checks the reserved list, and trims the suffix to 60 chars (was `withTrashed` only).
- **Step 3:** Photo pipeline uses the `public` disk end-to-end (Forget, purge, "delete file on DB failure"). `storage:link` is run in Step 1.
- **Step 4:** Withdraw-consent is the `InboxRow::withdrawConsent(Testimonial $t)` method only — **no POST route** (D35). Also sets `is_wall_of_love = 0`. Forget SQL matches by `testimonial_id` OR (`testimonial_id IS NULL` AND `email`). Step 4 added a cross-user authorization test list.
- **Step 5:** Open deletion-requests list filters to the owner's **live** Spaces only. Requests for soft-deleted Spaces are not shown until the Space is restored (README note).
- **Step 6:** Purge job is **explicit, not cascade-driven**. Tombstone Spaces are never hard-deleted. Per-Space order: for each testimonial (live + soft-deleted) close matching `deletion_requests` → delete photo → `forceDelete`; then delete `embed_configurations` row; keep `spaces` row.
- **Step 7:** Embed response is `{config, testimonials}`. Soft-deleted Space returns `{config: null, testimonials: []}`. Live Space returns `{config: {…}, testimonials: […]}` with code defaults if no `embed_configurations` row. `show_rating=false` omits `rating` server-side. `embed.js` uses `textContent` only. Endpoint registered in `routes/web.php` **outside** the `web` middleware group (no session/cookie/CSRF) with throttle `120/min`. Cache `max-age=60`. New Pest tests: `Access-Control-Allow-Origin` header on `Origin` request; no `Set-Cookie`; `Cache-Control: public, max-age=60`; `?limit=200` returns ≤ 50; `show_rating=false` omits `rating`; `textContent` neutralizes `<script>` tags in fixture text.
- **Step 8:** Fixture seeders have exact data. `shiplog-reviews` is 100 LIVE (Priya, Ben, Tom, Sara) + 96 generated (first 20 WoL) + 1 soft-deleted Ana. `beta-testers` is 12 LIVE (Priya same email + 11 generated). 1 open deletion request on `shiplog-reviews` for Sara (`testimonial_id = null`). "Done means" updated: Dev's "Total customers" = 111; embed per-Space counts spelled out; cross-user authorization tests listed.

## What changed in v2.6a (post-`bmad-sprint-planning` readiness review)

- **New `Hard rules (don't break these)` section** between "Stack rules (locked)" and "The 8 steps." 15 numbered rules, each pointing at the source decision (riser addendum / decision log / specific Step). The list consolidates rules that previously lived in scattered sections so a build agent reading only `build order.md` sees them up front. No behavior change; this is a discoverability fix.
- **Step 1 "Done means":** added `php artisan storage:link` + `readlink(public_path('storage'))` check. Without this, Step 3's photo tests fail with a confusing 404 and look like a Step 3 bug.
- **Step 5 "Total customers" formula pinned.** Added the exact SQL, the soft-delete rules for the join, and a small synthetic Pest fixture (4 customers across 2 Spaces) that catches the off-by-one *here* instead of only surfacing in Step 8's "Done means" via the 111-customer assertion. Step 8's "Total customers = 111" assertion is unchanged; the off-by-one is now caught earlier.

## What changed in v2.5 (historical)

- **Step 2:** Resolved G1. The slug-check route is named `GET /spaces/check-slug?slug=X` directly inline (not flagged as TBD); references `final PRD.md` §5.
- **Step 4:** Resolved G2. The Withdraw-consent Livewire method and route are named directly inline (not flagged as TBD); references `final PRD.md` §7.
- **Flagged items section** replaced with a **Resolved in v2.4 (logged in D31)** section, which points at the canonical names in `final PRD.md` and `data-model.md`.
- No step instructions changed. The 8-step plan, "Done means" checks, and stack rules are unchanged from v2.4.

## What changed in v2.4 (historical)

- **G1 Step 2:** slug collision probe + `-2`/`-3` suggestion documented; exact route name flagged for PRD §5 / data-model §3.2 follow-up.
- **G2 Step 4:** Withdraw-consent mechanic documented; Livewire method and route names flagged for PRD §7 / data-model §3.3 follow-up.
- **G3 Step 4:** Forget-now pins the `deletion_requests` match query (`space_id + testimonial_id + status='open'`) and the update-then-force-delete order.
- **G4 Step 5:** Free-plan info note pinned (location: dashboard header; exact copy; no CTA; no `/billing` route in v1).
- **G5 Step 6:** Space-purge now documents the `embed_configurations` FK cascade removal (tombstone Space has no embed row).
- **G6 Step 7:** `item_limit = 50` enforced server-side in the embed controller; query-param clamp too.
- **G7 Step 7:** Embed JSON order pinned to `is_favorite DESC, submitted_at DESC` (firmware rule).
- **G8 Step 8:** Fixture Priya + Ben rationale captured (Priya = `COUNT(DISTINCT email)` = 1 across Dev's Spaces; Ben = hidden tests the publish-vs-hide separation).
- New **Flagged items** section listing G1 + G2 route/method names pending Agent 1 follow-up in PRD v2.4 / data-model v2.4.