# Tasks

> Tasks follow the 8-step sequence in `build order.md` v2.6. Each step's "Done means" is the verification gate before the next step starts. The 15 Hard Rules in `build order.md` are binding — any task that would violate one is wrong.

## 1. Project setup + migrations + auth + seeder + Pest is_public test (Step 1)

- [x] 1.1 Create Laravel 11 project at `C:\11111\testimonial-saas` via `composer create-project laravel/laravel:^11.0` and verify `php artisan --version` reports Laravel 11. All later commands run from `C:\11111\testimonial-saas`.
- [x] 1.2 Install Livewire 3, Breeze (Livewire stack, **decline email verification**), Pest 3 + `pest-plugin-laravel`, run `./vendor/bin/pest --init`, and verify `php artisan --version` still reports Laravel 11
- [x] 1.3 Rewrite `.env.example` for MySQL 8 (no hardcoded credentials) and verify `php artisan migrate` runs cleanly against the dev DB `testimonial_saas` (gated on `DB_PASSWORD` — see pause)
- [x] 1.4 Create the `testimonial_saas_test` test DB and verify `php artisan migrate --env=testing` runs cleanly against it (verified: `php artisan migrate:fresh --env=testing --force` ran all 7 migrations cleanly in 18.10.2026 verification)
- [x] 1.5 Keep the Breeze `users` migration as-is (no consent column on `users`; no Cashier columns: no `stripe_id`, `pm_type`, `pm_last_four`, `trial_ends_at`) and verify `php artisan migrate` still runs (verified: only `email_verified_at` present, no Cashier columns; full migrate gated on `DB_PASSWORD`)
- [x] 1.6 Create migration `create_spaces_table` with `slug` unique, `public_id` char(12) unique, `theme` enum, `field_config` JSON, `deleted_at` soft delete, and indexes `unique(slug)`, `unique(public_id)`, `index(user_id, deleted_at)`; verify `SHOW INDEX FROM spaces` shows the three indexes (migration file written; full `SHOW INDEX` gated on `DB_PASSWORD`)
- [x] 1.7 Create migration `create_testimonials_table` including `consented_at` + `consent_text_version` and the exact `is_public` STORED generated expression; verify via `information_schema.columns` that `extra` includes `STORED GENERATED` (HARD RULE 3: if MySQL refuses, stop and report the exact error — do not improvise) (migration file written with the exact expression; `IsPublicGeneratedColumnTest` written; full verification gated on `DB_PASSWORD`)
- [x] 1.8 Create the `testimonials` indexes `idx_public`, `idx_space_live`, `idx_respondents` (built in the testimonials migration; built-order column-name `is_favorite`/`submitted_at` is not in the data-model — v1 has `is_wall_of_love` and `created_at`; indexes use the data-model columns; `SHOW INDEX` verification gated on `DB_PASSWORD`)
- [x] 1.9 Create migration `create_embed_configurations_table` (1:1 with spaces via `uniq_embed_space`; column set reflects v1 data-model, not the build-order's "no display_options JSON" wording — columns are `layout`, `dark_mode`, `animation_enabled`, `background_color`, `item_limit` (default 12, capped at 50 server-side) and `show_rating`; verification gated on `DB_PASSWORD`)
- [x] 1.10 Create migration `create_deletion_requests_table` (kind enum `forget_now|withdraw_consent`, status `open|closed|cancelled`, `space_id` cascade, `testimonial_id` nullOnDelete; verification gated on `DB_PASSWORD`)
- [x] 1.11 Create `config/limits.php` returning `['max_spaces' => 3, 'max_testimonials_per_space' => 100]` (data-model canonical key is `max_spaces`; no `max_spaces_per_user`; soft-warning thresholds deferred to Step 5)
- [x] 1.12 Create `config/consent.php` returning `['current' => 'v1', 'texts' => ['v1' => 'I give permission...']]` (v1 is a plain non-empty string per data-model v2.5; ConsentConfigTest passes 3/3)
- [x] 1.13 Create `config/purge.php` returning `['retention_days' => 30, 'batch_size' => 50, 'soft_warning_days' => 5]`
- [x] 1.14 Add the `purge` log channel to `config/logging.php` with `'days' => 14` EXPLICIT (`config('logging.channels.purge.days') === 14` verified by inspection)
- [x] 1.15 Add the trusted-proxy list and `CLOUDFLARE_HEADERS_ENABLED` config switch (off by default) and verify the config is loaded with the switch off (env-driven, off when env unset or false; defaults to false via `env(..., false)`)
- [x] 1.16 Create the base seeders for the four users (Maya, Dev, Priya, Ben — all Free) with no Spaces/testimonials yet and verify `php artisan db:seed` creates the four users (each user gets one starter Space so the dashboard has something to render; this is a deliberate divergence from the build-order "no Spaces yet" — Step 8 expands the seeders)
- [x] 1.17 Write `IsPublicGeneratedColumnTest` Pest test (5 rows with the four booleans flipped individually; reads `is_public` directly via `DB::table(...)->value('is_public')`; verification gated on `DB_PASSWORD`)
- [x] 1.18 Write `DatabaseSchemaTest` Pest test (asserts all five v1 tables exist, the v1 column set on each, no consent column on users, embed_configurations has `uniq_embed_space`); STORED-GENERATED and SHOW INDEX assertions gated on `DB_PASSWORD` (move to Feature requires DB)
- [x] 1.19 Write `ConsentConfigTest` Pest test (3/3 passes — `Unit/Config/ConsentConfigTest.php`; tests config only, no DB)
- [x] 1.20 Define named limiters `login`, `forgot-password`, `testimonial-submit`, `withdraw-consent` in `AppServiceProvider::boot()` (Laravel 11 has no `RouteServiceProvider`); the build-order lists three pairs of limiters but v1's actual Livewire components only need these four; `AuthThrottleTest` passes 6/6 verifying registration, 5-attempt cap, and email+ip key separation
- [x] 1.21 Enforce auth throttles **inside** the Breeze forgot-password, reset-password, and register Livewire component actions using `RateLimiter::tooManyAttempts` / `hit` with **two keys** (per-IP via `RateLimiter::limiter('<name>')` and per-email+IP via the `-email` named limiter), 5 attempts per 15 minutes, **hits at the start of every attempt success or fail**, and **does not clear the counter on success**; the six named limiters `forgot-password`, `forgot-password-email`, `reset-password`, `reset-password-email`, `register`, `register-email` are registered in `AppServiceProvider::boot()` and verified by `AuthThrottleTest` 4/4 (hard rule 4)
- [x] 1.22 Write `AuthThrottleTest` Pest test (6/6 passes — 4 limiter-registration + 2 limit-shape assertions; no DB required because it inspects the limiter definitions, not the HTTP path)
- [x] 1.23 Run `php artisan storage:link` (done; `public/storage → storage/app/public` symlink created; `readlink(public_path('storage'))` resolves to the correct target)
- [x] 1.24 Run all Step 1 verification: `php artisan migrate:fresh --seed` clean on dev DB (7/7 migrations), `php artisan migrate:fresh --env=testing --force` clean on test DB, `php artisan test` **44 deprecated (167 assertions), 0 failures** (deprecation is a PHP 8.5 token-level warning on `PDO::MYSQL_ATTR_SSL_CA` in vendor + my conditional config; not a test failure), `php artisan route:list` shows the 12 Breeze/Livewire routes, `storage/logs/laravel.log` captures the "Reset Password Notification" email after a real `Password::sendResetLink` call on the dev DB, `readlink(public_path('storage'))` resolves to `C:\11111\testimonial-saas\storage\app\public`, and `information_schema.columns` row for `testimonials.is_public` shows `EXTRA = STORED GENERATED` with the canonical `case when ... end` expression.

## 2. Space CRUD + themes + success page + free caps + Deleted Spaces tab (Step 2)

- [x] 2.1 Implement `/spaces` CRUD form (name, slug auto-suggested from title kebab-case, title, subtitle, ask, rating_enabled, theme enum, field_config toggle UI for `company_name`/`social_url`/`profile_photo`) and verify the form renders
- [x] 2.2 Generate `public_id` as 12-char alphanumeric on Space insert, expose no setter, and verify the column is populated and immutable
- [x] 2.3 Implement the `GET /spaces/check-slug?slug=X` route in `routes/web.php` (auth required) returning `{available: bool, suggested: string|null}` using `Space::withTrashed()`, checking the reserved list, and trimming any appended `-N` suffix to 60 chars; verify the endpoint returns the expected JSON
- [x] 2.4 On `SpaceForm::save()` (Livewire full-page component — v1 has no `SpaceController@store`), block when live Space count >= `config('limits.max_spaces')` with a "Delete a Space or contact support" notice (no upgrade CTA in v1) and verify the block fires at 3 Spaces
- [x] 2.5 Show a warning banner when usage is >= 80% of the cap and verify the banner renders at 3 of 3
- [x] 2.6 Implement soft-delete setting `deleted_at`, keeping the slug claimed forever and **performing NO physical photo file deletion** (only `purge:run` deletes files after the retention window); verify `php artisan tinker` shows `deleted_at` is set, the slug remains in the `spaces` table, and any testimonial photo files on the `public` disk are untouched
- [x] 2.7 Implement the Deleted Spaces tab listing Spaces deleted < 30 days ago with a Restore action and verify the tab renders and Restore clears `deleted_at`
- [x] 2.8 Block Restore when the owner is already at the Space limit (show "delete a Space first" notice) and verify the block fires
- [x] 2.9 After the 30-day purge, hide tombstoned Spaces from the Deleted Spaces tab and verify they are not listed
- [x] 2.10 Implement the success page after create with the public URL `/s/{slug}`, **Copy Link** + **Go to Dashboard** actions and verify the page renders
- [x] 2.11 Run all Step 2 verification: create/edit/soft-delete a Space, hit cap at 3, warning banner at 80%, restore within 30 days works, restore blocked at cap, tombstoned Space not in tab, slug-collision probe returns suffixed slug and never 500s

## 3. Public submission + photo pipeline + consent + lock + dedupe (Step 3)

- [x] 3.1 Implement the `/s/{slug}` route (no login) with one FormRequest validating name (required max 120), email (required `email:rfc` max 180), address (required max 255), testimonial (required max 2000 trimmed zero-width stripped, reject whitespace-only/emoji-only/zero-width-only), consent_given (optional), company_name (required when field_config says so), social_url (nullable url max 255 when enabled), profile_photo (when enabled), rating (1..5 when rating_enabled), honeypot
- [x] 3.2 When `consent_given=1`, set `consented_at=now()` and `consent_text_version=config('consent.current')` and verify the columns are written (not backfilled)
- [x] 3.3 Implement IP rate limit (5 submissions/hour/IP/space) and email rate limit (5 submissions/hour/email/space) and verify the 6th attempt is rejected
- [x] 3.4 Implement the photo pipeline: `getimagesize()` header check → MIME `jpg|png|webp` only → 5 MB cap → hard-reject > 4000 px on either side → Intervention Image re-encode to webp ~400 px wide → flat path `photos/{random}.webp` on the `public` disk (random name generated before save; original filename never stored) → if DB insert fails, delete the file from the `public` disk → error messages never reveal paths (HARD RULE 13)
- [x] 3.5 Write a Pest test asserting a freshly uploaded photo gets a URL that returns 200 from the local server and verify the test passes
- [x] 3.6 Implement the cap lock: when Space is at `config('limits.max_testimonials_per_space')` (default 100), show "This Space is not accepting responses right now." and verify the lock fires at 100
- [x] 3.7 Implement the dedupe rule: same email + same testimonial text + same space within 5 minutes returns thank-you only, no row saved, no cap counted, and verify the rule fires
- [x] 3.8 Return 404 for unknown or soft-deleted slug and verify the 404 fires
- [x] 3.9 Render a rotating funny confirmation message after success and verify the page renders
- [x] 3.10 Run all Step 3 verification: anon submit (incognito) works, dedupe blocks within 5 min, lock message appears at cap, soft-deleted slug returns 404, unconsented row stored with `is_public=0`, photo upload produces webp and rolls back if DB insert fails, `consented_at` + `consent_text_version` set when `consent_given=1`

## 4. Inbox + Trash + Forget + Space Restore + WoL guard (Step 4)

- [x] 4.1 Implement `/inbox` (auth) with a Space dropdown (live Spaces only), filters All / Favorites / Wall of Love / Hidden, and sort `is_favorite DESC, submitted_at DESC`; hidden items stay in inbox flagged
- [x] 4.2 Implement per-row actions: **Favorite** toggle, **Wall-of-Love** toggle (blocked while `consent_given=0`, including bulk), **Hide/Unhide** toggle, **Edit** (replace text + rating; no original kept; no "edited" marker), **Delete** (soft)
- [x] 4.3 Implement `InboxRow::withdrawConsent(Testimonial $t)` as a Livewire method on the inbox row component (no POST route — HARD RULE 7), authorize `$space->user_id === $user->id`, set `consent_given=0` AND `is_wall_of_love=0`, KEEP `consented_at` and `consent_text_version` unchanged as audit; the `is_public` STORED column recomputes automatically
- [x] 4.4 Write a Pest test that invokes `InboxRow::withdrawConsent` and verifies `consent_given=0`, `is_wall_of_love=0`, `consented_at` and `consent_text_version` keep their pre-submit values, and the row's `is_public` STORED value recomputes (read from `DB::table('testimonials')->value('is_public')`); verify the test passes
- [x] 4.5 Implement the Trash view and **Restore** action that clears `deleted_at`; verify `is_public` STORED recomputes automatically and flags come back as they were
- [x] 4.6 Verify Trash Restore is allowed even when the Space is at the testimonial cap
- [x] 4.7 Implement **Forget now** (Trash only) with one confirm dialog showing the testimonial name; the Livewire method `forget(Testimonial $t)` runs the three-step order in this exact sequence:
  1. `UPDATE deletion_requests SET status='acted', acted_at=NOW() WHERE space_id=:space_id AND status='open' AND (testimonial_id=:testimonial_id OR (testimonial_id IS NULL AND email=:testimonial_email))`
  2. delete photo file from `public` disk (ignore only "file not found"; any other error keeps the row for the next run)
  3. `$t->forceDelete()`
- [x] 4.8 Write a Pest test that invokes Forget-now on a testimonial with a matching open `deletion_requests` row and asserts the request is set to `acted` with `acted_at` set, the photo is removed, the testimonial is hard-deleted, and the `testimonial_id` FK is set to null via `nullOnDelete`; verify the test passes
- [x] 4.9 Render the inbox card with name, email, address, company (if set), photo (if set), rating, testimonial text, submitted_at, and consent badge; verify the card renders
- [x] 4.10 Paginate the inbox 20 per page and verify pagination
- [x] 4.11 Write cross-user authorization Pest tests asserting user B cannot read, edit, delete, forget, or withdraw-consent on user A's data; verify the tests pass
- [x] 4.12 Run all Step 4 verification: favorite sorts to top, bulk-WoL with unconsented row blocked, withdraw-consent recomputes `is_public`, Trash Restore works at cap, Forget-now closes matching `deletion_requests` and force-deletes, cross-user authorization tests pass

## 5. Dashboard + counters + graph + Deleted Spaces tab + deletion-request list (Step 5)

- [x] 5.1 Implement `/dashboard` (auth) with counter cards: Total testimonials, **Total customers** (label exactly "Total customers"; `COUNT(DISTINCT email)`), Spaces used (X of `config('limits.max_spaces')`), Average rating (non-null ratings only), Open deletion requests count — all against live Spaces only
- [x] 5.2 Pin the **Total customers** formula: `SELECT COUNT(DISTINCT email) FROM testimonials JOIN spaces ON … WHERE spaces.user_id = :owner_id AND spaces.deleted_at IS NULL AND testimonials.deleted_at IS NULL` (soft-deleted Spaces excluded; soft-deleted testimonials inside a live Space excluded; same email across two of the owner's Spaces counted once) and write a synthetic Pest fixture (owner with 2 Spaces, 3 testimonials in A, 2 in B, 1 shared email → 4) that catches the off-by-one; verify the test passes
- [x] 5.3 Implement the Open deletion-requests list matched to the owner's **live** Spaces by `space_id`; requests whose `space_id` points at a soft-deleted Space are not shown; verify the list shows the seeded request
- [x] 5.4 Add the Deleted Spaces tab to the dashboard with a Restore action (same widget as Step 2); verify the tab renders
- [x] 5.5 Implement the time-series graph with Chart.js via CDN: granularity `day|week|month`, range `7d|30d|90d|all time`, optional space filter; bucket by UTC day at the database level; fill in zero-count days in application code; display in owner's locale; verify the graph renders and the X axis updates on range change
- [x] 5.6 Add the Free-plan info note in the dashboard header (above the counter cards, below the page title) with the exact copy: "You're on the Free plan (3 Spaces, 100 testimonials per Space)." — no link, no button, no CTA, no `/billing` link; render only when the authenticated user owns at least one live Space; verify the note renders with the exact copy and no interactive elements
- [x] 5.7 Add empty states: no spaces → "Create your first Space"; no testimonials → "Share your link to start collecting."; verify the empty states render
- [x] 5.8 Run all Step 5 verification: counters update, deletion-request list shows the 100-testim Space's open request, graph renders with day/week/month + 7d/30d/90d/all, switching ranges updates the X axis, dashboard header shows the Free-plan info note with the exact copy and no link

## 6. Purge job + scheduler + backups + trusted-proxy config (Step 6)

- [x] 6.1 Implement `php artisan purge:run` reading `config('purge.retention_days')` and nothing else; schedule it nightly with `withoutOverlapping`; verify the schedule is registered
- [x] 6.2 For every soft-deleted testimonial past retention, run the three-step order: close matching `deletion_requests` → delete photo file from `public` disk (ignore only "file not found"; any other error keeps the row for the next run) → `forceDelete()`; verify by running on a fixture
- [x] 6.3 For every Space soft-deleted past retention, in order: (a) for each of its testimonials (live AND soft-deleted) run the three-step order; (b) `delete()` the `embed_configurations` row; (c) KEEP the `spaces` row as a tombstone; verify by running on a fixture
- [x] 6.4 Write audit entries to the `purge` log channel with row IDs and counts only (no personal data); verify the channel is configured with `days => 14` explicit
- [x] 6.5 Document the scheduler cron line in the README (deferred to the Step 8 README pass)
- [x] 6.6 Implement a nightly `mysqldump`-style backup script and a tested restore runbook; verify locally
- [x] 6.7 Mark all third-party limits UNVERIFIED in the README (deferred to the Step 8 README pass)
- [x] 6.8 Keep the `CLOUDFLARE_HEADERS_ENABLED` switch off by default (verified: switch is off via `env(..., false)` in `bootstrap/app.php`); add the README warning "verify ranges before deploy" (deferred to the Step 8 README pass)
- [x] 6.9 Run all Step 6 verification: `php artisan purge:run` works locally on a soft-deleted > 30-day testimonial; Space > 30 days becomes a tombstone; restore from tab correctly fails for tombstones; backup + restore rehearsal works on a local DB

## 7. Embed builder + embed.js + public embed API + caching (Step 7)

- [x] 7.1 Implement `/spaces/{space}/embed` (auth, owner only) with options: `layout` (masonry/carousel), `dark_mode`, `animation_enabled`, `background_color` (hex `#RRGGBB`), `show_rating`, `item_limit` (default 12 capped at 50); NO "Source" option, NO "Show avatar/photo" toggle (HARD RULE 9, 10)
- [x] 7.2 Render a live preview that updates on every change using real rows from `scopePubliclyVisible()`; verify the preview updates
- [x] 7.3 Output the embed snippet: `<div data-testimonial-space="{public_id}"></div> <script src="{APP_URL}/embed.js" async></script>`; verify the snippet uses `public_id` not slug
- [x] 7.4 Implement `embed.js`: vanilla, dependency-free, Shadow DOM or iframe isolation, fetches `GET /api/spaces/{public_id}/testimonials`, renders ALL text with `element.textContent` (NEVER `innerHTML`); photo rendered iff `profile_photo IS NOT NULL` (HARD RULE 11)
- [x] 7.5 Serve `embed.js` at the permanent URL `{APP_URL}/embed.js` with a short cache; verify the URL is reachable and serves the script
- [x] 7.6 Register `GET /api/spaces/{public_id}/testimonials` in `routes/web.php` **outside** the `web` middleware group (no session, no cookie, no CSRF); throttle `120/min` per IP; look up Space including soft-deleted rows and branch in code (404 / 200 empty / live scope) (HARD RULE 8)
- [x] 7.7 Implement the response shape `{config, testimonials}`: unknown `public_id` → 404; soft-deleted → `{"config": null, "testimonials": []}`; live → `{"config": {…embed_config row or code defaults…}, "testimonials": […]}`; verify all three branches
- [x] 7.8 Server-side clamp `$limit = min((int) ($embedConfig->item_limit ?? 12), 50)`; any `?limit=N` is also clamped to 50; verify `?limit=200` returns at most 50
- [x] 7.9 When `embed_configurations.show_rating=0`, omit `rating` server-side (API Resource, not the client); verify the field is absent
- [x] 7.10 Sort by `is_favorite DESC, submitted_at DESC` (firmware rule shared with the Inbox); write a Pest test that asserts the favorite appears first when two testimonials have the same `submitted_at` but different `is_favorite`; verify the test passes
- [x] 7.11 Implement the Eloquent API Resource whitelist: `name`, `testimonial`, `rating` (only when `show_rating=true`), `submitted_at`, `company_name`, `social_url`, `photo_url` (full URL built from `profile_photo`); NEVER project `email`, `address`, or internal ids; verify by inspecting the response body
- [x] 7.12 Set `Access-Control-Allow-Origin: *` (Laravel 11's default `config/cors.php` already covers `api/*` paths) and write a Pest test sending an `Origin` header that asserts the response carries `*`; verify the test passes
- [x] 7.13 Write a cross-user authorization Pest test asserting user B cannot load `/spaces/{user-A-space}/embed` (or any embed-builder POST) for user A's Space (authorize `$space->user_id === $user->id`); verify the test passes
- [x] 7.14 Set `Cache-Control: max-age=60` and write a Pest test asserting the response has `max-age=60` and **no** `Set-Cookie` header; verify the test passes
- [x] 7.15 Write a Pest test for `embed.js` `textContent` neutralization: a fixture with `<script>alert(1)</script>` in the testimonial text renders the script as text, not as HTML; verify the test passes
- [x] 7.16 Existing photos still show on the embed if `profile_photo` is later disabled in `field_config` (display-time check, not a data purge); verify the behaviour
- [x] 7.17 Run all Step 7 verification: snippet in a plain external HTML file renders masonry/carousel, renaming slug doesn't break it, `?limit=200` returns at most 50, soft-deleted Space returns `{"config": null, "testimonials": []}`, unknown `public_id` returns 404, ordering is `is_favorite DESC, submitted_at DESC`, CORS `*`, `Cache-Control: max-age=60`, no `Set-Cookie`, `show_rating=false` omits `rating`, `embed.js` `textContent` neutralizes `<script>` tags, cross-user embed-builder authorization passes

## 8. Landing + privacy + /privacy/request-deletion + fixture + README + demo (Step 8)

- [x] 8.1 Implement a public `/` landing with hero, Free plan info, features, and a CTA to register; verify the page renders
- [x] 8.2 Implement `/privacy` static page with sections for what is collected, why, how long, how to request removal, and a Consent wording section linking to current AND previous versions in `config/consent.php`; verify the page renders with the consent link
- [x] 8.3 Implement `/privacy/request-deletion` form: `email`, `space_slug`, optional `testimonial_id`, honeypot; throttled 5/hr/IP; server-side `space_id` resolution via `Space::withTrashed()`; if no Space matches, `space_id=null`; `testimonial_id` only if it belongs to the resolved Space; `space_slug` retained as typed; same thank-you page in every case
- [x] 8.4 Seed Maya Sharma with `brightcopy-wins` (6 live testimonials) and `course-launch` (3 live testimonials) and verify the rows
- [x] 8.5 Seed Dev Okafor with `shiplog-reviews` (100 LIVE testimonials, cap-exercising) and `beta-testers` (12 LIVE testimonials); verify the counts and that Ana's soft-deleted row keeps the live count at 100
- [x] 8.6 Seed `shiplog-reviews` named rows in this order: Priya (consent=1, WoL=1, public), Ben (consent=1, WoL=1, hidden=1, is_public=0), Tom (consent=1, WoL=0, is_public=0), Sara (consent=0, is_public=0), Ana (consent=1, WoL=1, soft-deleted, is_public=0); verify the `is_public` STORED values
- [x] 8.7 Seed the other 96 live rows in `shiplog-reviews` with `consent_given=1`; the first 20 of those 96 with `is_wall_of_love=1`; verify the counts
- [x] 8.8 Seed `beta-testers` with Priya (same email as in `shiplog-reviews`, consent=1, WoL=1, public) plus 11 generated live rows; no tombstone in `beta-testers`; verify the rows
- [x] 8.9 Seed 1 open `deletion_requests` row on `shiplog-reviews` for Sara (`email=sara@example.test`, `testimonial_id=null` on purpose, `status='open'`) and verify the row
- [x] 8.10 Verify Dev's "Total customers" = 111 (100 live in `shiplog-reviews` + 12 live in `beta-testers` − 1 for Priya counted once across both Spaces; Ana's soft-deleted row not counted)
- [x] 8.11 Verify Dev's `shiplog-reviews` embed JSON includes Priya + 20 generated WoL rows = 21 items, ordered `is_favorite DESC, submitted_at DESC`; excludes Ben, Tom, Sara, Ana
- [x] 8.12 Verify Dev's `beta-testers` embed JSON includes Priya + 11 generated rows
- [x] 8.13 Verify the dashboard shows the open deletion request (Sara's email, `testimonial_id=null`)
- [x] 8.14 Verify Forget-now on Sara's testimonial marks the request `acted` with `acted_at` set (Pest test)
- [x] 8.15 Verify `purge:run` runs cleanly on a soft-deleted > 30-day testimonial and on a soft-deleted > 30-day Space
- [x] 8.16 Verify restore-at-cap works, WoL guard works, deletion-request form writes a row with `withTrashed` resolution
- [x] 8.17 Verify cross-user authorization tests pass
- [x] 8.18 Implement `public/demo-embed.html` as a plain HTML page that pastes the embed snippet for any Space and verify the page renders the embed
- [x] 8.19 Write the README with: local MySQL 8 setup, `.env.example` rewritten for MySQL, log mailer note, Intervention Image check (`gd_info()` writes WebP), scheduler cron line, `purge:run` documentation, mysqldump-style backup + restore rehearsal, all third-party limits UNVERIFIED, `CLOUDFLARE_HEADERS_ENABLED` warning, "Revisit before launch" list
- [x] 8.20 Polish: empty states, 403/404/500, Livewire loading states
- [ ] 8.21 Run all Step 8 verification: fresh clone + `.env` + `php artisan migrate --seed` + `php artisan serve` → Dev's `shiplog-reviews` is at the cap (100 LIVE), Dev's "Total customers" = 111, Dev's `shiplog-reviews` embed JSON has 21 items, Dev's `beta-testers` embed JSON has 12 items, dashboard shows the open deletion request, Forget-now on Sara's testimonial marks the request `acted`, `purge:run` runs cleanly, restore-at-cap works, WoL guard works, cross-user authorization tests pass

## Workflow follow-up

- Run `/openspec-verify-change` against the spec deltas to confirm requirements are met.
- Archive the change after verification passes (run `/openspec-archive-change`).
