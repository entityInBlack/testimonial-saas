# Testimonial-SaaS

A Laravel 11 + Livewire 3 (Volt) + Blade + MySQL 8 app for collecting customer testimonials via a public link, managing them in an Inbox, and displaying them on third-party sites through an embed.

## 1. What it is

A self-hosted testimonial-collection SaaS. An owner signs up, creates a **Space**, shares the public link (`/s/{slug}`), and customers submit testimonials without logging in. The owner curates them in the **Inbox** (favorite, Wall of Love, hide, edit, soft-delete, **Withdraw consent**, **Forget now**), watches a **Dashboard** with counters and a Chart.js time-series, and copies a snippet to embed the wall on any external site.

v1 ships the **Free plan only**. Limits are hardcoded in `config/limits.php` (`max_spaces = 3`, `max_testimonials_per_space = 100`). There is no Stripe, no Cashier, no Pro plan, no `/billing` route, no `Plan` enum, no `subscribed('default')` derivation, and no upgrade path. The dashboard header shows a non-actionable "You're on the Free plan (3 Spaces, 100 testimonials per Space)." note with no CTA. Pro, billing, and the upgrade flow are a v2 future path.

Main features (Steps 1-8): Space CRUD with themes and per-Space field toggles, public submission form with consent versioning and a photo pipeline (Intervention Image, webp ~400 px), Inbox + Trash with restore and forget, dashboard counters (Total customers via `COUNT(DISTINCT email)`), time-series graph (Chart.js via CDN), embed builder + `public/embed.js` + a public JSON API, `/privacy` + `/privacy/request-deletion`, nightly `purge:run` and `backup:run` via the scheduler, and the fixture seeders that exercise the caps.

## 2. How to run

### Requirements

- PHP `^8.2` (composer.json `require.php`); tested on PHP 8.5.11
- MySQL 8 only
- Node; tested on Node 24.19.0
- The PHP `gd` extension must be able to write WebP. Verify before first use: in the repo root, `php -r "print_r(gd_info());"` and confirm `WebP Support` is enabled. If `WebP Support` is missing, photo uploads will fail.

Tested with: PHP 8.5.11, Composer 2.10.3, Node 24.19.0, MySQL 8.0.46, Laravel 11.57.0, Livewire 3.8.10, Volt 1.11.2, Breeze 2.4.2, Pest 3.8.7, Vite 6.4.4, Tailwind 3.4.19, intervention/image 4.3.4. Chart.js 4.4.1 loads from a CDN (the dashboard graph needs internet).

### Steps (Windows PowerShell, repo root)

1. Clone: `git clone https://github.com/entityInBlack/testimonial-saas.git`
2. `cd testimonial-saas`
3. `composer install`
4. `npm install`
5. `Copy-Item .env.example .env`
6. `php artisan key:generate`
7. Create the two databases. The app uses charset `utf8mb4` and collation `utf8mb4_unicode_ci` (from `config/database.php`).
   ```powershell
   # In a `mysql>` prompt:
   CREATE DATABASE IF NOT EXISTS testimonial_saas      CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE DATABASE IF NOT EXISTS testimonial_saas_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
8. In `.env` set:
   - `DB_HOST=127.0.0.1`
   - `DB_PORT=3306`
   - `DB_DATABASE=testimonial_saas`
   - `DB_USERNAME=` (your MySQL user)
   - `DB_PASSWORD=` (your MySQL password)
   - `APP_URL=http://127.0.0.1:8000`
   After any change to `.env`, run `php artisan config:clear` and restart `php artisan serve`.
9. Migrate with seed: `php artisan migrate --seed`
10. Link the public storage disk (required; photo URLs go through `storage/app/public`): `php artisan storage:link`
11. Run the app in two terminals from the repo root:
    ```powershell
    # terminal 1
    php artisan serve

    # terminal 2
    npm run dev
    ```
    Do not use `composer dev` — it starts pail, which is not verified on Windows.
12. Open http://127.0.0.1:8000.

### Test database (read this before running tests)

`tests/` use `RefreshDatabase`, which wipes the database they connect to. `phpunit.xml` sets `APP_ENV=testing` and does not override the database, so the test run reads `.env.testing`. `.env.testing` is gitignored and there is no `.env.testing.example`, so a fresh clone does not have it. If `.env.testing` is missing, the test run would fall back to `.env` and wipe the dev database.

Required steps, after `.env` is complete (key generated, DB credentials set, `APP_URL` set):

```powershell
# 1. Copy .env to .env.testing
Copy-Item .env .env.testing
```

In `.env.testing`, set:
- `APP_ENV=testing`
- `DB_DATABASE=testimonial_saas_test`

Never run two Pest sessions at once (the test database is shared).

### Run the tests

```powershell
php artisan test
```

At commit `f126509` the suite is 399 tests, 1736 assertions, exit code 0. On PHP 8.5 Pest prints "deprecated" instead of "passed" for each test because of a PDO deprecation notice; these are not failures. Check the exit code:

```powershell
php artisan test; echo $LASTEXITCODE
# expect: 0
```

### Seeded logins (password `password` for all)

- `maya@brightcopy.co` — Maya Sharma
- `dev@shiplog.io` — Dev Okafor (his `shiplog-reviews` Space is at the 100-testimonial cap; `beta-testers` has 12; Total customers = 111)
- `priya@nimbus.dev` — Priya Raman
- `ben@example.test` — Ben Fischer

Public IDs are generated at insert and are not listed here.

### Scheduler

`routes/console.php` schedules `php artisan purge:run` daily at 03:30 and `php artisan backup:run` daily at 04:00, both `withoutOverlapping` (backup runs an hour after purge, so a backup is always a copy of the post-purge state). Wire a host cron to call `php artisan schedule:run` every minute. See `docs/backup-restore.md` for the cron line and the Windows Task Scheduler setup (section "Run the scheduler").

### Mail

`MAIL_MAILER=log` — outbound mail (e.g. password reset) is written to `storage/logs/laravel.log`.

### Queue

No queue worker is needed. The app does not use `ShouldQueue`, `Queue::push`, `->queue(...)`, or `dispatch(JobClass)`; the only `dispatch(...)` calls in the codebase are Livewire browser events (e.g. `inbox-row-updated`), not queued jobs. `QUEUE_CONNECTION=database` is set in `.env.example` for future use, but nothing is enqueued today.

## 3. Done / not done

### Done (the 8 build steps)

- Step 1 — Project setup + migrations + auth + seeder + Pest `is_public` test.
- Step 2 — Space CRUD + themes + success page + free caps + Deleted Spaces tab.
- Step 3 — Public submission + photo pipeline + consent + lock + dedupe.
- Step 4 — Inbox + Trash + Forget + Space Restore + Wall-of-Love guard.
- Step 5 — Dashboard + counters + graph + Deleted Spaces tab + deletion-request list.
- Step 6 — Purge job + scheduler + backups + trusted-proxy config.
- Step 7 — Embed builder + `embed.js` + public embed API + caching.
- Step 8 — Landing + privacy + `/privacy/request-deletion` + fixture seeders + demo embed page (this README is also part of Step 8).

### Not done

Closed by this README: 6.5 (scheduler cron line), 6.7 (third-party limits marked UNVERIFIED), 6.8 (`CLOUDFLARE_HEADERS_ENABLED` warning), 8.19 (the README itself).

- 8.21 — Fresh-clone end-to-end verification (fresh clone + `.env` + `php artisan migrate --seed` + `php artisan serve`, then the Step 8 "Done means" checks). Not run yet.
- The OpenSpec change `docs/openspec/changes/build-testimonial-saas-v1/` is not archived yet.

Known minor docs drift: `config/purge.php` keys `batch_size` and `soft_warning_days` appear only in `tasks.md` 1.13, not in the PRD / data model / build order; the `deletion_requests` migration adds `updated_at` and two indexes (`idx_deletion_space_status`, `idx_deletion_email_status`) not listed in data-model §3.6. Not fixed in this change.

Known limitation (by design): the dashboard's "open deletion requests" list is matched to the owner's **live** Spaces by `space_id`. Requests whose `space_id` points at a soft-deleted Space are not shown until the owner restores the Space. The data model calls this out so it is not rediscovered as a bug.

Out of scope by design: v1 ships the Free plan only (no Stripe, no Cashier, no `/billing`, no `Plan` enum, no `STRIPE_*` env keys, no `stripe` log channel, no upgrade path). The PRD's own "Non-Goals / Out of Scope" list (§10 of `docs/final PRD.md`) is also not in v1: no social OAuth, no onboarding survey, no team workspaces, no custom domains, no video/NPS, no AI features, no user-defined custom fields, no i18n, no email verification (`MustVerifyEmail` is not used), no owner edit history, no CAPTCHA, no public Wall-of-Love standalone page, no CSV import/export, no approval workflows, no mobile apps, no social publishing, no timezone-corrected dashboard graphs (UTC bucketing only).

## 4. Agentic tools

| Tool | Status | Where used |
|---|---|---|
| OpenSpec | USED | Change folder `docs/openspec/changes/build-testimonial-saas-v1/` (proposal, design, tasks, specs: auth, dashboard, embed, privacy, purge, seeders, spaces, testimonials) drove Steps 1-8. `/openspec-verify-change` ran on 11 Oct 2026: 107/112 tasks done at that time; the open ones were README and fresh-clone items. The change is not archived yet. |
| Skill: code-review | USED | A user-level "code-review" skill (lives outside the repo) ran twice on 11 Oct 2026, report-only (no code changes by the skill). The skill spawns two parallel sub-agents: a Standards reviewer (CLAUDE.md + a code-smell baseline) and a Spec reviewer (PRD, build order, data model, OpenSpec change). Scope: changes since the root commit `5639e10` (so the root commit's own files, mostly the Laravel/Breeze skeleton plus Steps 1-2, were not in the diff). Outcome: about 30 findings across both runs; most were false, cited the wrong rule, or were already-decided design choices; two small real fixes came out: removed the unused Laravel welcome view (`329d220`) and aligned the consent v1 wording in the docs with `config/consent.php` (`4f79c8d`). |
| CLAUDE.md | USED | Present at repo root (commit `566f97d`); a fresh puku-cli session started in the repo root showed it loaded. |
| Hook | NOT used | — |
| MCP / Playwright | NOT used | — |

### How the workflow ran

Claude (chat) wrote master prompts; puku-cli (a Claude Code fork, v1.8.56) implemented them in the repo; the human (Jahin) reviewed the real diffs and code, ran the tests himself, and committed. Models: puku-ai-2.8 for Steps 1-7, Opus 4.8 for later fixes, `CLAUDE.md`, verify, and both code reviews. The loop was: plan (Claude) → implement (puku-cli) → verify (puku-cli + human run of `php artisan test`) → human review of the real diff → commit by the human.

### What human review caught

Not visible in any agent's prose reports, but found when the human read the real code and diffs:

- The Inbox Trash tab was missing the **Restore** and **Forget now** actions (added).
- **Forget now** was originally allowed on live rows; it is now Trash-only.
- `public/demo-embed.html` contained an inline script with a literal `</script>` substring (fixed).
- The first `CLAUDE.md` draft invented two features (removed).
- Pest cannot run browser JavaScript, so UI/JS behaviour was checked manually in the browser.

## 5. Parallel task

Two puku-cli agents ran at the same time in separate git worktrees (created with `git worktree add`) on branches `polish-nav` and `polish-public-links`, editing different files.

- `polish-nav` (`4594df6`): added an **Inbox** link in the top navigation (desktop + responsive menu) and a test at `tests/Feature/Navigation/InboxNavLinkTest.php`.
- `polish-public-links` (`332cec6`): added a **Back to home** link in each state of the public submission page (form, locked, thank-you) and a test at `tests/Feature/Submission/BackHomeLinkTest.php`.

Agents edited only; no tests ran inside the worktrees. The human reviewed both real diffs, committed, merged into `main` with `--no-ff` (`19556e7`, `f126509`), no conflicts, then ran the full suite once (399 pass, exit 0) and pushed `main` and both branches (branches kept as evidence).

Honest notes: one agent ran a disallowed `php -r` command that crashed immediately (no effect on the repo); the locked state of the public page was checked by test only, not in the browser.

## 6. Notes

### Third-party limits — UNVERIFIED

The following third-party items are **UNVERIFIED** and must be confirmed against the chosen provider before any v2 launch (per build order line 311). v1 avoids services that need a card on file:

- **Cloudflare** (free plan) — UNVERIFIED.
- **Cloudflare R2** — UNVERIFIED.
- **Oracle Cloud Always Free** — UNVERIFIED.
- **Resend (or any email provider)** — UNVERIFIED. v1 uses the log mailer.
- **PHP `intl` extension on the host** — UNVERIFIED.
- **4,000 × 4,000 decode memory** — UNVERIFIED.
- **GD WebP support on the host** — UNVERIFIED. Check with `php -r "print_r(gd_info());"` and look for `[WebP Support] => 1` in the output.

### `CLOUDFLARE_HEADERS_ENABLED`

`CLOUDFLARE_HEADERS_ENABLED=false` in `.env.example` (off by default; v2 prep only). The switch is read in `bootstrap/app.php`: when it is on, the global middleware trusts forwarded headers (`X-Forwarded-For` and related) so the app can see the real client IP behind a proxy. The code comment warns that turning the switch on while NOT behind Cloudflare would let any visitor set their own client IP via header. **Verify ranges before deploy** before turning it on.

### MySQL setup

Use MySQL 8 only. The dev DB is `testimonial_saas`, the test DB is `testimonial_saas_test`. Both use `utf8mb4` / `utf8mb4_unicode_ci` (from `config/database.php`). On Windows installers the mysql client binary is typically not on PATH; set `BACKUP_MYSQL_PATH` in `.env` to `C:\Program Files\MySQL\MySQL Server 8.0\bin\mysql.exe` (or use the `$MYSQL` shell variable in the rehearsal commands in `docs/backup-restore.md`).

### Log mailer

`MAIL_MAILER=log` in `.env.example`. Outbound mail (e.g. the Breeze "Reset Password Notification") is written to `storage/logs/laravel.log`. No real email is sent. Replace with a real provider in v2.

### GD check

Before the first photo upload, confirm the host's GD build can write WebP. In the repo root:

```powershell
php -r "print_r(gd_info());"
```

Look for `[WebP Support] => 1` in the output. If it is off, photo uploads will fail at re-encode time.

### Backup + restore rehearsal

`php artisan backup:run` writes a nightly `mysqldump`-style dump to `storage/app/backups/testimonial_saas-YYYYMMDD-HHMMSS.sql` (single-transaction, quick, no lock-tables). The maintainer should rehearse the restore into a scratch database `testimonial_saas_restore_check` and compare row counts between source and scratch, never restore into the live `testimonial_saas` or `testimonial_saas_test` databases by accident. The exact PowerShell rehearsal commands and the Windows Task Scheduler setup are in `docs/backup-restore.md`.

### Revisit before launch

- **Email verification** — `MustVerifyEmail` is not used in v1; enable and add a verify-mail flow in v2.
- **Real email provider** — v1 uses the log mailer; pick a real provider and configure SPF/DKIM/DMARC in v2. v2 must exclude any provider that needs a card on file.
- **Pro / Stripe** — v1 is Free-only; v2 installs Cashier, adds the `Billable` trait, exposes `/billing`, registers the Stripe webhook, and adds a `stripe_events` table for idempotency.
- **Real CDN** — v1 serves `embed.js` from the app; v2 may place a CDN behind the permanent URL `{APP_URL}/embed.js`.
- **Load test** — exercise `GET /api/spaces/{public_id}/testimonials` at realistic and burst traffic; confirm cache headers, CORS, and JSON payload sizing.
- **Legal review** — review consent wording in `config/consent.php`, `/privacy`, and the `/privacy/request-deletion` flow. This README is not legal advice.

### Step 5, 6 and 7 decisions

Step 5:
- Dashboard "Total customers" is `COUNT(DISTINCT email)` over live data (soft-deleted rows not counted). Same email across two of the owner's Spaces counts once.
- The time-series graph buckets by UTC at the database level using `CONVERT_TZ(submitted_at, @@session.time_zone, '+00:00')`; no timezone correction in v1.

Step 6:
- The purge retention boundary is strict: a row is purged when `deleted_at` is strictly older than `now()->subDays(config('purge.retention_days'))`. A row at exactly `retention_days` stays (boundary is not inclusive).
- `purge:run` is scheduled at 03:30 (daily, `withoutOverlapping`); `backup:run` is scheduled at 04:00 (daily, `withoutOverlapping`). The backup always runs an hour after the purge, so a backup is a copy of the post-purge state. The backup passes the MySQL password to the child mysqldump process via the `MYSQL_PWD` environment variable; the password is never on the command line.

Step 7 (five decisions):
1. A valid `?limit=N` may raise or lower the saved `item_limit` for that request. The effective limit is `min(?limit if valid else item_limit, 50)`. An invalid `?limit` (zero, negative, non-numeric, decimal) is ignored.
2. The `config` object in the API response contains only the six option keys: `layout`, `dark_mode`, `animation_enabled`, `background_color`, `show_rating`, `item_limit` (taken from the saved row or from the defaults in `config/embed.php`); never `id`, `space_id`, or timestamps.
3. `social_url` is returned only when its URL scheme is http or https (parsed, case-insensitive); otherwise it is `null`.
4. Unknown `public_id` returns 404 with body `{"error":"Not Found"}` and the response carries `Cache-Control: public, max-age=60`.
5. `background_color` is exactly `#RRGGBB` (six hex digits) or empty. The embed uses Shadow DOM, not an iframe.

### Core design rules

- `is_public` is a MySQL STORED generated column on `testimonials` (exact `CASE WHEN consent_given = 1 AND is_wall_of_love = 1 AND is_hidden = 0 AND deleted_at IS NULL THEN 1 ELSE 0 END` STORED). The visibility rule lives in the schema; the Eloquent scope `Testimonial::scopePubliclyVisible()` → `where('is_public', true)` is the only app-facing API.
- Dual public identifiers on a Space: `slug` is the mutable shareable collection URL (`/s/{slug}`); `public_id` (char(12), immutable) is the embed key. Renaming a Space must not break embeds already live on customer sites.
- Soft delete + 30-day purge + tombstone Space row. `deleted_at` is set on delete; soft-deleted rows do not count toward limits. The scheduled `purge:run` runs the three-step order (close matching `deletion_requests` → delete photo file from the `public` disk, ignore only "file not found" → `forceDelete()` the testimonial row). For Spaces past retention, the purge deletes the `embed_configurations` row but keeps the `spaces` row as a tombstone so the slug stays claimed forever.
- Withdraw consent is a Livewire method only (`InboxRow::withdrawConsent(Testimonial $t)` on the inbox row component). No POST route. The method authorizes `$space->user_id === $user->id`, sets `consent_given = 0` **and** `is_wall_of_love = 0`, and keeps `consented_at` and `consent_text_version` unchanged as audit. The `is_public` STORED column recomputes to 0 automatically.
- Embed JSON order is `is_favorite DESC, submitted_at DESC` — the same ordering used by the Inbox.
- `show_rating = false` omits `rating` from every embed item server-side (API Resource, not the client).
- `embed.js` uses `element.textContent` only — never `innerHTML` — to neutralize XSS from respondent content.
- The public embed endpoint is registered in `routes/web.php` outside the `web` middleware group (no session, no cookie, no CSRF), throttle `120/min` per IP, `Cache-Control: public, max-age=60`, CORS `*`, no `Set-Cookie` header. The Space lookup includes soft-deleted rows; the branch (404 / 200 empty / live scope) is decided in code after the row is found.
- API Resource whitelist on the embed response: only `name`, `testimonial`, `rating` (when `show_rating = true`), `submitted_at`, `company_name`, `social_url` (http/https only), `photo_url`. Never `email`, `address`, or internal ids.

### Repo

https://github.com/entityInBlack/testimonial-saas
