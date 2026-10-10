# Proposal: Build Testimonial Collection SaaS v1 (Free Plan Only)

## Why

A new Laravel 11 + Livewire 3 + MySQL product is required so that business owners can create **Spaces**, share a public link to collect customer testimonials without requiring customer login, manage submissions in an **Inbox**, and display approved testimonials on external sites via a drop-in **embed**. The platform must be production-shaped but ship Free-only: limits are hardcoded, no Stripe, no Cashier, no Pro plan, no webhooks, no `/billing` route. v2 future paths (Cashier, Pro, real email provider, real CDN) are explicitly out of scope for this change.

This change is the foundation for the entire v1 product. It introduces the schema, the public submission flow, the owner inbox, the dashboard, the embed pipeline, the nightly purge, and the demo fixture. It must be implemented in the exact 8-step order in `build order.md` so that each step's tests are green before the next one starts.

## What Changes

- **New Laravel 11 project** with Breeze (Livewire stack, **decline email verification**), Livewire 3, Pest 3, MySQL 8.
- **Migrations** for `spaces`, `testimonials` (with `is_public` STORED generated column), `embed_configurations`, `deletion_requests`. `users` keeps Breeze's columns; **no Cashier columns**.
- **Config files**: `config/limits.php` (3 spaces / 100 testimonials per Space), `config/consent.php` (versioned wording), `config/purge.php` (30-day retention), `config/logging.php` (add `purge` channel, `days => 14` explicit), trusted-proxy + `CLOUDFLARE_HEADERS_ENABLED` switch (off by default).
- **Models**: `User` (no `Billable`), `Space` (soft delete, `public_id` immutable 12-char, slug globally unique), `Testimonial` (`scopePubliclyVisible()`), `EmbedConfiguration`, `DeletionRequest`.
- **Public submission page** at `/s/{slug}` with one FormRequest, photo pipeline (webp ~400 px, 5 MB cap, 4000 px hard-reject), consent versioning, dedupe (5 min), IP + email rate limits, lock message at cap.
- **Inbox** at `/inbox` with favorite / Wall-of-Love (blocked without consent) / hide / edit / withdraw-consent (`InboxRow::withdrawConsent(Testimonial $t)` Livewire method, no POST route) / soft-delete actions, Trash with Restore and Forget-now (three-step order: close matching `deletion_requests` → delete photo → `forceDelete`).
- **Dashboard** at `/dashboard` with counters (Total testimonials, **Total customers**, Spaces used, Average rating, Open deletion requests count), open deletion-request list, Deleted Spaces tab with Restore (blocked at Space limit), and Chart.js time-series graph (UTC day bucketing, day/week/month × 7d/30d/90d/all).
- **Embed builder** at `/spaces/{space}/embed` with live preview, snippet `<div data-testimonial-space="{public_id}"></div> <script src="{APP_URL}/embed.js" async></script>`, and `embed.js` (vanilla, Shadow DOM/iframe, `textContent` only).
- **Public embed API** at `/api/spaces/{public_id}/testimonials` registered in `routes/web.php` **outside** the `web` middleware group, throttled `120/min`, with `{config, testimonials}` response shape and an Eloquent API Resource that **never** projects `email`, `address`, or internal ids.
- **Nightly purge command** `purge:run` with `withoutOverlapping`, tombstone Spaces (kept forever), `purge` log channel daily 14 days.
- **Privacy page** at `/privacy` (links to current and previous consent wording) and **deletion request form** at `/privacy/request-deletion` (throttled 5/hr/IP, server-side `space_id` resolution with `withTrashed`).
- **Fixture seeders** for Maya (2 Spaces, 6+3 testimonials), Dev (2 Spaces, 100+12 testimonials with named Priya/Ben/Tom/Sara/Ana), and 1 open deletion request for Sara with `testimonial_id = null`.
- **Demo HTML page** at `public/demo-embed.html` and **README** with MySQL setup, log mailer note, scheduler cron, Intervention Image `gd_info()` check, UNVERIFIED-item list, and "Revisit before launch" list.
- **Pest tests** for: `is_public` STORED generated column, schema/indexes, consent config, auth throttles, Total customers formula, embed limit clamp, embed ordering, embed CORS / Cache-Control / no-Set-Cookie, `show_rating=false` server-side filter, `embed.js` `textContent` XSS neutralization, cross-user authorization, Forget-now three-step order, deletion-request form, free-plan info note, photo URL 200, soft-deleted slug 404, restore-at-cap.

**BREAKING**: nothing in this repo exists yet (greenfield). All four new tables are net-new. No deprecations.

## Capabilities

### New Capabilities

- `spaces`: Space CRUD with slug rules, reserved-list check, collision probe (`GET /spaces/check-slug`), `public_id` generation, soft delete with 30-day Restore, tombstone policy, free-plan cap of 3 spaces per owner.
- `testimonials`: Public submission at `/s/{slug}` with one FormRequest, photo pipeline (Intervention Image, webp ~400 px, 5 MB cap, 4000 px hard-reject), consent versioning, IP+email rate limits, dedupe, lock at cap, Inbox actions (favorite / Wall-of-Love / hide / edit / withdraw-consent / soft-delete), Trash Restore, Forget-now three-step order.
- `embed`: Embed builder UI, `embed.js` (vanilla, `textContent` only, Shadow DOM/iframe), public embed API at `/api/spaces/{public_id}/testimonials` outside the `web` middleware group, Eloquent API Resource whitelist, CORS `*`, `Cache-Control: max-age=60`, `?limit` clamp to 50, `show_rating=false` server-side filter.
- `dashboard`: Counters (Total testimonials, Total customers via `COUNT(DISTINCT email)`, Spaces used, Average rating, Open deletion requests count), open deletion-request list, Deleted Spaces tab with Restore, time-series graph (UTC day bucketing, day/week/month × 7d/30d/90d/all) using Chart.js, Free-plan info note.
- `privacy`: `/privacy` static page linking to current and previous consent wording, `/privacy/request-deletion` form (throttled 5/hr/IP, honeypot, server-side `space_id` resolution with `Space::withTrashed()`).
- `purge`: `purge:run` nightly command with `withoutOverlapping`, retention from `config('purge.retention_days')`, three-step order (close matching `deletion_requests` → delete photo → `forceDelete`), per-Space processing (for each testimonial, then delete `embed_configurations` row, then keep `spaces` row as tombstone), `purge` log channel with `days => 14` explicit.
- `auth`: Breeze (Livewire stack) login, register, forgot-password, reset-password, logout. `MustVerifyEmail` is OFF in v1. Auth throttles enforced **inside** Livewire component actions (per-IP and per-email+IP, 5 attempts / 15 min); named limiters in `AppServiceProvider::boot()`. Storage symlink (`php artisan storage:link`) ready for photo upload.
- `seeders`: Demo fixtures (Maya, Dev, Priya, Ben) for the local demo, README demo script, plain HTML demo page at `public/demo-embed.html`.

### Modified Capabilities

None. This is a greenfield change; no existing capabilities exist to modify.

## Impact

- **Affected code**: new Laravel 11 project at `C:\11111` (greenfield). Models, migrations, Livewire components, Blade views, routes, config, Pest tests, README, fixture seeders.
- **Affected APIs**: `/s/{slug}` (public submission), `/spaces/*` (owner), `/inbox`, `/dashboard`, `/privacy`, `/privacy/request-deletion`, `/spaces/{space}/embed`, `/api/spaces/{public_id}/testimonials` (public, outside `web` middleware group), `{APP_URL}/embed.js` (permanent).
- **Affected dependencies**: `laravel/framework:^11.0`, `livewire/livewire:^3.0`, `laravel/breeze` (Livewire stack), `pestphp/pest:^3.0`, `pestphp/pest-plugin-laravel`, `intervention/image`. No `laravel/cashier`, no `stripe/stripe-php`.
- **Affected systems**: MySQL 8 (separate `testimonial_saas` dev and `testimonial_saas_test` test databases), Laravel scheduler (cron line for `purge:run`), file storage on `public` disk (`storage/app/public` via `php artisan storage:link`).
- **Out of scope (v1)**: Stripe / Pro plan, webhooks, `/billing` route, `Plan` enum, `subscribed('default')`, `stripe_events` table, `stripe` log channel, email verification, real email provider, real CDN / Cloudflare in front, production hosting, OAuth, custom domains, multi-tenant accounts, user-defined custom fields, EAV, owner edit history, i18n, CAPTCHA.
