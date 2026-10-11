# CLAUDE.md

## 1. Project

A Laravel 11 + Livewire 3 (Volt) + Blade + MySQL testimonial-collection SaaS. v1 is free-plan only (`config/limits.php`: `max_spaces = 3`, `max_testimonials_per_space = 100`; plain constants, no env knob, no `Plan` enum, no plans table).

Core pieces:

- Spaces: CRUD with the free-plan cap, public slug, owner-only access.
- Public submission form per space (rating, body, name/role/photo, consent).
- Inbox (new) and Trash (soft-deleted) views; withdraw-consent, restore, Forget.
- Dashboard: counts, recent activity, Chart.js trend (loaded from CDN at runtime).
- Schedulers: `purge:run` (retention-based hard delete) and `backup:run`.
- Embed: builder, JSON API, and `public/embed.js` for third-party sites.
- Privacy: deletion requests.

## 2. Stack and versions

- PHP 8.5.11
- Composer 2.10.3
- Node 24.19.0
- MySQL 8.0.46
- Laravel 11.57.0
- Livewire 3.8.10
- Volt 1.11.2
- Breeze 2.4.2
- Pest 3.8.7
- intervention/image 4.3.4, intervention/image-laravel 4.1.1
- Vite 6.4.4
- Tailwind 3.4.19
- Chart.js 4.4.1 via CDN (dashboard loads it at runtime)

PHP 8.5 is newer than Laravel 11's official range; every Pest test shows a harmless "deprecated" mark (`PDO::MYSQL_ATTR_SSL_CA`). Any NEW PHP-version error is a real problem: stop and report.

## 3. Sources of truth

- `C:\11111\final PRD.md` (v2.5)
- `C:\11111\build order.md` (v2.6)
- `C:\11111\data-model.md` (v2.5)
- OpenSpec main specs `C:\11111\openspec\specs\` (auth, dashboard, embed, privacy, purge, seeders, spaces, testimonials); archived change `C:\11111\openspec\changes\archive\2026-10-12-build-testimonial-saas-v1\` (proposal, design, tasks, delta specs)

Those originals live outside this repo; if copies exist under `docs/`, the originals win. Agents read only these; never `_bmad-output`. Any deviation from PRD / data-model / build order means updating all three documents in the same change (Hard Rule 15). If a detail is not in the documents, ask; do not guess.

## 4. Hard rules (binding)

1. No Cashier, ever.
2. No `/billing`, Plan enum, `subscribed()`, upgrade CTA/banner, `STRIPE_*` env keys or stripe log channel.
3. `is_public` is the exact STORED generated column (`case when consent_given=1 and is_wall_of_love=1 and is_hidden=0 and deleted_at is null then 1 else 0 end`); never write it; if MySQL refuses, stop and report.
4. Auth throttles live INSIDE the Livewire actions (forgot-password, reset-password, register): two keys (per-IP, per-email+IP), 5 per 15 min, message "Too many attempts. Try again later.", hit at the start, no clear on success; named limiters in `AppServiceProvider::boot()`.
5. `storage:link` is done (Step 1).
6. No physical file deletion on soft-delete. Only `purge:run` (after retention) and Forget now delete files. Order: close matching `deletion_requests`, then delete the photo, then `forceDelete`.
7. Withdraw-consent is `InboxRow::withdrawConsent(Testimonial $t)`, no POST route; it also sets `is_wall_of_love=0` and keeps `consented_at` and `consent_text_version`.
8. Embed API is in `routes/web.php` OUTSIDE the web group: throttle 120/min, `Cache-Control public max-age=60`, CORS `*`, no `Set-Cookie`.
9. Embed limit is clamped server-side to 50; `show_rating=false` omits rating server-side; order `is_favorite DESC, submitted_at DESC`.
10. API Resource whitelist; never email, address or internal ids.
11. `embed.js` uses `textContent` only.
12. `CLOUDFLARE_HEADERS_ENABLED` is off by default.
13. Photo pipeline: webp about 400 px wide, reject over 4000 px before decoding, random filename, never store the original name, delete the file if the DB insert fails.
14. Sara's seeded open deletion request has `testimonial_id = null` on purpose.
15. Any deviation from PRD / data-model / build order means updating all three documents in the same change.

## 5. Working rules for agents

- DANGER: tests use `RefreshDatabase`, which WIPES the database. `phpunit.xml` sets `APP_ENV=testing` and tests read `.env.testing`; if `.env.testing` were missing they would fall back to `.env` and wipe the dev DB. Before any test run confirm `test -f .env.testing && echo True` prints True (do not read the file). Never run two Pest sessions at once (shared test DB).
- Never touch or print `.env` / `.env.testing`. Never edit `README.md` or `.env.example` unless the user explicitly asks.
- Never run `migrate`, `migrate:fresh`, `db:seed`, `tinker` writes or `php artisan serve` against the dev database unless the user explicitly asks.
- No `git add` / `commit` / `stash` / `restore` / `reset` / `push` unless the user explicitly asks. When the user commits, they stage by explicit paths, never `git add .`.
- Scope: edit only the files the prompt names. If something else needs changing, STOP and report.
- STOP means end the turn and report; never pick an alternative and continue.
- Never loosen or rewrite an assertion to make a test pass; if a real value differs, report it. Every new test needs a "would fail if" note. Pest cannot run browser JavaScript: UI/JS behaviour needs a manual browser check.
- No empty catch blocks and no swallowed errors; keep the real error message.
- Reports must include the requested output IN FULL (tool output is collapsed for the user). Do not paste whole files unless asked.
- Do not claim "the user told me to X" without quoting where.

## 6. Commands

From the repo root:

- `php artisan test`
- `php artisan test --filter=<Name>` (iterate with `--filter`; full suite only at pre-flight and at the end; do not pipe test output through `head`/`tail`)
- `php artisan serve` + `npm run dev` (two windows, http://127.0.0.1:8000)
- `php artisan purge:run`
- `php artisan backup:run`

Exit code in bash: `echo "exit=$?"`. Test summary prints "deprecated" instead of "passed" (expected). After `migrate:fresh --seed` the seeded users Maya, Dev, Priya, Ben exist; they share the password `password` (this is a local-dev seeder value, not a secret).
