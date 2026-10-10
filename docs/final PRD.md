# PRD: Minimal Testimonial Collection SaaS

**Stack:** Laravel 11 + Livewire + Blade + MySQL
**Audience:** Practicing coding agent / Laravel developers
**Status:** MVP, ready for implementation
**Version:** 2.5 (Free plan only; Stripe, Cashier, Pro plan removed from v1)
**v2.5 alignment:** applied rules from the v2.6 review pass — Rule 1 (auth throttles moved inside Livewire components via RateLimiter), Rule 2 (Withdraw-consent is the `InboxRow::withdrawConsent` method only; no POST route; also sets `is_wall_of_love = 0`), Rule 3 (`GET /spaces/check-slug` in `routes/web.php`, withTrashed, 60-char trim; embed endpoint at `/api/spaces/{public_id}/testimonials` placed in `routes/web.php` outside the `web` middleware group with a Pest test asserting no `Set-Cookie` and `Cache-Control: max-age=60`), Rule 4 (purge order: close matching `deletion_requests` → delete photo → forceDelete testimonial; `embed_configurations` row deleted; Space row kept as tombstone), Rule 5 (embed response `{config, testimonials}`; soft-deleted Space returns `{config: null, testimonials: []}`; `show_rating=false` omits `rating` server-side; `?limit=` clamped to 50; `embed.js` uses `textContent` only), Rule 6 (`public` disk + `php artisan storage:link` documented), Rule 7 (deletion request resolves slug with `withTrashed`; Forget/purge run the acted-update BEFORE forceDelete), Rule 8 (fixture seeders exact data; "Total customers" = 111 for Dev; embeds per-Space as specified), Rule 9 (cross-user authorization tests). Dropped `App\Support\Limits` helper; raw `config('limits.*')` only. `build order.md` bumped to v2.6; data-model bumped to v2.5. Decisions D34–D42 logged.

---

## 1. Overview & Goal

A minimal SaaS where business owners create **Spaces**, share a public link to collect customer testimonials (no login for customers), manage them in an **Inbox**, and display them on external websites via an **embed**. **v1 ships the Free plan only.** Pro plan, Stripe, Cashier, webhooks, and `/billing` are v2 future paths.

Primary goal: a new user can sign up, create a Space, share the public link, collect testimonials, manage them in the inbox, and build an embed — all within minutes, without developer intervention. **v1 ships the Free plan only — there is no upgrade path in v1.**

---

## 2. User Roles

| Role | Description |
|---|---|
| **Owner** | Authenticated user. Creates Spaces, curates testimonials, builds embeds. (v1 ships Free plan only — no billing to manage.) |
| **Customer** | Anonymous visitor who submits a testimonial via a public link. No account required. |

---

## 3. Data Model (Reconciled)

The authoritative schema. Where the original PRD and `data-model-testimonial-saas.md` differed, this section is the source of truth. Key Technical Decisions (§4) explains each non-obvious choice.

### 3.1 `users`
Standard Laravel auth columns only. **No Cashier, no `Billable` trait, no Stripe columns.** v1 has no billing integration.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `name` | varchar(120) | |
| `email` | varchar(180) | unique |
| `email_verified_at` | timestamp null | |
| `password` | varchar(255) | |
| `remember_token` | varchar(100) null | |
| `created_at` / `updated_at` | timestamp | |

**No `plan` column.** v1 ships the Free plan only; limits are hardcoded in `config/limits.php`. There is no `Plan` enum and no `subscribed('default')` derivation.

### 3.2 `spaces`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `user_id` | bigint FK → users.id | cascade on delete |
| `name` | varchar(120) | internal identification (form field, max 120, required, default = title) |
| `slug` | varchar(60) | **globally unique**, used in `/s/{slug}` route. Auto-suggested from title, editable; on collision the form suggests `-2`, `-3` |
| `public_id` | char(12) | unique, non-sequential, immutable, used in embed snippet |
| `title` | varchar(160) | public |
| `subtitle` | varchar(255) null | public |
| `ask` | text | the testimonial prompt |
| `theme` | enum | `minimal_light`, `minimal_dark`, `soft_color` |
| `rating_enabled` | boolean | default `true` |
| `field_config` | JSON | per-space toggle for the 3 optional fields — see §3.3 |
| `created_at` / `updated_at` | timestamp | |
| `deleted_at` | timestamp null | **soft delete** — row stays as a tombstone after purge, slug stays claimed forever |

**Indexes:** `unique(slug)`, `unique(public_id)`, `index(user_id, deleted_at)`.

**Two public identifiers (deliberate):**

| Column | Job | Audience | Mutable? |
|---|---|---|---|
| `slug` | Shareable collection URL, `/s/{slug}` | respondents | yes, user-editable |
| `public_id` | Embed key, `data-testimonial-space="{public_id}"` | external sites | **never** |

If embeds used `slug`, renaming a Space would silently break every embed already live on customers' sites.

**Slug rules**
- Globally unique, enforced by a database unique index.
- Route is `/s/{slug}` (namespaced under `/s/`, never the root, so it cannot collide with app routes).
- Reserved defensively: `s`, `api`, `admin`, `login`, `register`, `dashboard`, `billing`, `embed`, `assets`, `up`.
- On collision, the create form suggests a suffixed slug (`course-launch-2`, `course-launch-3`).

**Soft-delete policy:** On delete, `deleted_at` is set. The `slug` stays claimed permanently — both Maya and any other user cannot reclaim it. Soft-deleted Spaces do **not** count toward plan limits. After the 30-day retention, the purge job keeps the Space row as a tombstone.

### 3.3 `spaces.field_config` (JSON column)

A JSON blob on the `spaces` table. Holds per-Space toggles for the 3 optional fields. **No user-defined custom fields in MVP** (see §10 Non-Goals).

```json
{
  "company_name":  { "enabled": true,  "required": false },
  "social_url":    { "enabled": false, "required": false },
  "profile_photo": { "enabled": false, "required": false }
}
```

Three locked fields — Name, Email, Address — are always enabled and required. They are not stored in `field_config`; they are columns on `testimonials`.

### 3.4 `testimonials`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `space_id` | bigint FK → spaces.id | cascade on delete |
| `name` | varchar(120) | always required |
| `email` | varchar(180) | always required, **never public** |
| `address` | varchar(255) | always required, **never public** |
| `company_name` | varchar(160) null | optional per space |
| `social_url` | varchar(255) null | optional per space |
| `profile_photo` | varchar(255) null | optional per space; storage path |
| `testimonial` | text | always required, max **2,000** chars |
| `rating` | tinyint unsigned null | 1..5, null when the space disables rating |
| `consent_given` | boolean | default `false` |
| `consented_at` | timestamp null | set when `consent_given = 1`; not backfilled |
| `consent_text_version` | varchar(40) null | version of the wording from `config/consent.php`; set with `consented_at`; not backfilled |
| `is_favorite` | boolean | default `false`, ordering only |
| `is_wall_of_love` | boolean | default `false` |
| `is_hidden` | boolean | default `false` |
| `is_public` | boolean **STORED generated** | see below |
| `submitted_at` | timestamp | domain timestamp |
| `created_at` / `updated_at` | timestamp | |
| `deleted_at` | timestamp null | soft delete |

**Indexes**

| Index | Columns | Serves |
|---|---|---|
| `idx_public` | (`space_id`, `is_public`, `is_favorite`, `submitted_at`) | embed reads, index-ordered, no filesort |
| `idx_space_live` | (`space_id`, `deleted_at`, `submitted_at`) | limit count, collection graph, inbox |
| `idx_respondents` | (`space_id`, `deleted_at`, `email`) | `COUNT(DISTINCT email)` |

**The `is_public` generated column:**

```sql
is_public TINYINT(1) AS (
  CASE WHEN consent_given = 1
        AND is_wall_of_love = 1
        AND is_hidden = 0
        AND deleted_at IS NULL
       THEN 1 ELSE 0 END
) STORED
```

```php
$table->boolean('is_public')->storedAs(
    'case when consent_given = 1 and is_wall_of_love = 1
          and is_hidden = 0 and deleted_at is null
          then 1 else 0 end'
);
```

Eloquent scope: `scopePubliclyVisible()` → `where('is_public', true)`. The embed endpoint, live preview, Wall-of-Love empty state, and dashboard all read through this scope.

**Application rule (UI guard):** block `is_wall_of_love = 1` while `consent_given = 0`. The generated column already makes such a row unpublishable, but a toggle that can never take effect is a UI that lies.

**Why `is_wall_of_love` and `is_hidden` stay separate:** hide is an *override layered on a publish decision*, not a replacement. With one `visibility` enum, hiding overwrites "published", and unhiding has nowhere to return to. Two booleans restore publication automatically.

### 3.5 `embed_configurations`
Exactly 0 or 1 per Space. The row is optional; with no saved row, code defaults apply.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `space_id` | bigint FK → spaces.id | **unique**, cascade on delete |
| `layout` | enum | `masonry`, `carousel`, default `masonry` |
| `dark_mode` | boolean | default `false` |
| `animation_enabled` | boolean | default `true` |
| `background_color` | char(7) null | `#RRGGBB` |
| `item_limit` | smallint unsigned | default `12`, **capped at 50** |
| `show_rating` | boolean | default `true` |
| `created_at` / `updated_at` | timestamp | |

`item_limit` is capped at 50 to keep embed payload and render time bounded on host pages.

### 3.6 Cashier tables
**Not in v1.** `subscriptions` and `subscription_items` do not exist. v2 future path: install Cashier, add the `Billable` trait on `User`, run the standard Cashier migrations.

### 3.7 Limits (in code, not a table)

```php
// config/limits.php
return [
    'max_spaces'              => 3,
    'max_testimonials_per_space' => 100,
];
```

```php
// usage — raw config, no helper
$maxSpaces   = config('limits.max_spaces');              // 3
$maxPerSpace = config('limits.max_testimonials_per_space'); // 100
```

- **No `Plan` enum:** v1 has one plan (Free). An enum with one case is a marker for "we used to have more", which is a v2 concern.
- **No `plans` table:** same reason.
- **No `users.plan` column:** would be a constant; redundant.
- **No `subscribed('default')`:** there are no subscriptions in v1.
- v2 future path: replace this with a `Plan` enum backed by a `plans` table or a Cashier subscription lookup.

### 3.8 `deletion_requests`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `email` | varchar(180) | |
| `space_slug` | varchar(60) | value the respondent typed |
| `space_id` | bigint nullable | FK → spaces.id, **nullOnDelete** (set null if the Space is hard-deleted) |
| `testimonial_id` | bigint nullable | FK → testimonials.id, **nullOnDelete** (set null when the testimonial is force-deleted by Forget or the purge job) |
| `status` | enum | `open` \| `acted` |
| `created_at` | timestamp | |
| `acted_at` | timestamp null | set when matching testimonial is forgotten |

Rows are never hard-deleted in MVP; only `status` changes to `acted`. The owner dashboard matches open requests to a Space by `space_id`. (See §13.2.)

---

## 4. Key Technical Decisions

Decisions in this PRD that are deliberate — borrowed from the data model and documented so they are not rediscovered as bugs.

1. **Dual public identifiers (`slug` + `public_id`).** Slug is mutable for the collection URL; public_id is immutable for the embed snippet. Renaming a Space must not break live embeds on customer sites.
2. **`is_public` is a MySQL STORED generated column.** The visibility rule (`consent_given AND is_wall_of_love AND NOT is_hidden AND NOT deleted`) lives in the schema, so it can't drift across four call sites.
3. **Soft deletes on `spaces` and `testimonials`.** `deleted_at` is set on delete. Soft-deleted testimonial rows are hard-deleted after 30 days by the purge job. Space rows are kept as tombstones — their slug stays claimed permanently. Soft-deleted Spaces/testimonials do **not** count toward the Free limits — otherwise a 3/3 Free user can only fix it by deleting real data. **A soft-deleted Space can be restored by the owner within 30 days** (see §7, §11); after the 30-day purge the Space is a tombstone and cannot be restored.
4. **Limits are hardcoded constants in `config/limits.php`.** No `Plan` enum, no `plans` table, no `users.plan` column. v1 has one plan. v2 may replace this with a `Plan` enum backed by Cashier.
5. **No `stripe_events` table, no webhook idempotency layer.** v1 has no webhooks. v2 future path: add a `stripe_events` table with `unique(stripe_event_id)` for the day billing is added.
6. **Testimonial cap is a soft cap, not an invariant.** At 99/100, two simultaneous submissions may both pass and the Space lands at 101. `FOR UPDATE` is rejected for lock contention on the hot public endpoint. Counter caches are rejected for drift complexity. If counting ever becomes expensive, the counter cache is additive.
7. **UTC-only graph aggregation, no `users.timezone`.** Simpler query. Display uses owner locale. Day-boundary errors for non-UTC owners are accepted and documented.
8. **`is_wall_of_love = 1` is blocked while `consent_given = 0`.** The generated column already makes the row unpublishable, but the UI must not lie — a toggle that can never take effect is itself a bug.

---

## 5. Space Creation Flow

Route: `/spaces/new` (auth required).

Owner fills out a single form, in this order:

1. **Name** — internal identification, max 120, required. Defaults to the Title.
2. **Slug** — max 60, required, auto-suggested from title, editable. The reserved list is checked. On collision, the create form probes **GET /spaces/check-slug?slug=X** (auth required; lives in `routes/web.php`; JSON response `{available: bool, suggested: string|null}`). The probe looks up `Space::withTrashed()` (so soft-deleted and tombstoned slugs count as taken), checks the reserved list (`s`, `api`, `admin`, `login`, `register`, `dashboard`, `billing`, `embed`, `assets`, `up`), and trims any appended `-N` suffix so the final slug is at most 60 chars. `suggested` is the next free `-2`/`-3` suffix or `null` if the slug is free as-is. The form surfaces "slug already taken" and offers the suffixed slug. The database `unique(slug)` index is the source of truth; the probe is a UX nicety, not a security boundary.
3. **Title** — shown on public page.
4. **Subtitle** — supporting line.
5. **Ask** — the prompt (e.g. "What did you like about our product?").
6. **Rating** — collect star rating? (toggle, default on; stored as `rating_enabled`).
7. **Theme** — `minimal_light`, `minimal_dark`, `soft_color`.
8. **Optional fields** — three toggles with on/off + required/optional:
   - Company name (`company_name`)
   - Social URL (`social_url`)
   - Profile photo (`profile_photo`)

Name, Email, Address are always on and required (columns on `testimonials`).

On save: redirect to a **success page** showing the public URL (`https://{app}/s/{slug}`) with **Copy Link** + **Go to Dashboard**.

Space list (in dashboard) shows: title, testimonial count, public link, edit, delete (with confirm; soft-delete sets `deleted_at`). A **Deleted Spaces** tab lists Spaces deleted less than 30 days ago and offers Restore (see §7, §11).

---

## 6. Public Submission Page

Route: `/s/{slug}` — no login required.

Renders the space's theme, title, subtitle, and ask. Shows the three locked fields (Name, Email, Address) plus any of the 3 optional fields whose `enabled=true` in `field_config`. Required fields are enforced client- and server-side. Star rating input appears if `rating_enabled=true`.

**Consent checkbox** (yes/no, default false):
> "I give permission for this testimonial to be shared publicly."

The wording lives in `config/consent.php` under a version key; see §6.1.

Submission is allowed with consent unchecked, but the testimonial will be excluded from embeds and Wall of Love (via `is_public` STORED column).

**Server-side validation (one FormRequest):**
- `name` — required, max 120.
- `email` — required, `email:rfc`, max 180. No DNS check.
- `address` — required, max 255.
- `testimonial` — required, max 2,000 chars. Reject whitespace-only, emoji-only, and zero-width-only text. Text is trimmed. Zero-width characters (U+200B to U+200D, U+FEFF) are always stripped. Unicode normalization needs PHP `intl` and is skipped if `intl` is missing.
- `consent_given` — nullable boolean. Consent is optional.
- `social_url` — nullable `url`, max 255, when enabled.
- `company_name` — required only when `field_config.company_name.required = true`.
- `rating` — required 1..5 when `rating_enabled = true`. When `rating_enabled = false`, the field is ignored and `null` is stored.
- Honeypot field present and must be empty.

**Photo upload (when enabled):**
- Max 5 MB.
- MIME in `jpg | png | webp` (checked server-side).
- `getimagesize()` header check; hard reject over 4,000 px on either side.
- Re-encode to webp about 400 px wide via Intervention Image.
- Stored at `photos/{random}.webp` (flat random path — original filename is never stored).
- If the DB insert fails, the file is deleted.
- Error messages never reveal file paths.

**Cap behavior:** when the Space is at its testimonial cap, the form shows the lock message: "This Space is not accepting responses right now."

**Unknown or soft-deleted slug → 404.**

**Spam protection:** honeypot field + IP rate limit (5 submissions / hour / space) + email rate limit (5 submissions / hour / email / space). No CAPTCHA in MVP.

**Funny confirmation message** after success (e.g. "You're officially awesome. 🚀"). A small set of rotating messages. Optional GIF/meme is nice-to-have; do not block submission if asset is missing.

### 6.1 Consent versioning
- The wording lives in `config/consent.php` under a version key (e.g. `v1`, `v2`).
- New testimonials submitted with `consent_given = 1` write `consented_at` and `consent_text_version`. They are not backfilled.
- Published versions are never edited. To change wording, add a new version key.
- `/privacy` links to the current and previous wording.
- Owner can press **Withdraw consent** on a testimonial: this sets `consent_given = 0` **and** `is_wall_of_love = 0` (so the UI never shows a Wall-of-Love badge on a row that cannot be public; the `is_public` STORED column recomputes automatically). `consented_at` and `consent_text_version` are kept as audit.
- Do not collect real customer data until the wording is final.

### 6.2 Duplicate rule
- Same `email` + same `testimonial` text + same `space` within 5 minutes is a duplicate.
- The response is the normal thank-you page. Nothing is saved. Nothing counts toward the cap.

---

## 7. Inbox & Testimonial Management

Route: `/inbox` (auth required).

Filter by Space (dropdown, default: all). Filter chips: All / Favorites / Wall of Love / Hidden. **Trash** is its own view, reached from a tab.

Sorting (firmware rule): `is_favorite DESC, submitted_at DESC`. `is_hidden=true` items still appear in inbox but are flagged.

Each testimonial card shows: **name, email, address, company (if set), photo (if set), rating, testimonial text, submitted_at, consent badge**.

**Actions per card:**
- ⭐ **Favorite** — toggles `is_favorite`. Favorited items sort to the top within their space.
- ❤️ **Wall of Love** — toggles `is_wall_of_love`. **Blocked while `consent_given = 0`, including bulk actions.**
- ⋯ **Menu:** Edit (`testimonial` text + `rating`), Hide/Unhide (`is_hidden`), Delete (with confirm; sets `deleted_at`). **No original copy is kept and no "edited" marker.**

**Withdraw consent** — Livewire method `InboxRow::withdrawConsent(Testimonial $t)` on the inbox row component. Authorizes against `$space->user_id === $user->id`. Sets `consent_given = 0` **and** `is_wall_of_love = 0` (so the UI never shows a Wall-of-Love badge on a row that cannot be public; the `is_public` STORED column recomputes automatically). Keeps `consented_at` and `consent_text_version` unchanged as audit. No email; no row delete. The action button is shown per row in the Inbox menu.

**Trash actions:**
- **Restore** — clears `deleted_at`. The `is_public` STORED column recalculates automatically. Flags (`is_favorite`, `is_wall_of_love`, `is_hidden`) come back as they were. Restore is allowed even when the Space is at its testimonial cap — the cap only blocks new public submissions, not the return of an existing one.
- **Forget now** — one confirm dialog showing the testimonial name. Forget runs the three-step order: (a) UPDATE matching `deletion_requests` to `status='acted'`, `acted_at = now`; (b) delete the photo file from the `public` disk, ignoring only "file not found"; (c) `forceDelete()` the testimonial row.

**Embed visibility rule:** a testimonial is included in embeds and the Wall of Love **only if** `is_public = 1` (STORED generated column; computed from `consent_given=1 AND is_wall_of_love=1 AND is_hidden=0 AND deleted_at IS NULL`).

**Space deletion and Restore:**
- A soft-deleted Space appears in the **Deleted Spaces** tab on the dashboard for up to 30 days. The owner can press **Restore**.
- **Restore Space** brings the Space back. Its testimonials come back too — a testimonial deleted on its own stays in Trash when its Space is restored. Trash shows live Spaces only; to reach a testimonial in a deleted Space, restore the Space first.
- **Restore is blocked when the owner is already at their Space limit** — show the "delete a Space first" notice. After the 30-day purge the Space row is a tombstone and cannot be restored; its slug stays claimed.

Inbox, dashboard, and counts read **live Spaces only**.

Pagination: 20 per page.

---

## 8. Embed Builder

Route: `/spaces/{space}/embed` (auth required).

**Options (from `embed_configurations` row):**
- **Space** selector.
- **Layout:** `masonry`, `carousel`.
- **Dark mode** toggle.
- **Animation** toggle (fade-in for masonry; auto-advance for carousel).
- **Background color** picker (hex).
- **Show rating** toggle.
- **Max testimonials** cap (default 12, **capped at 50**).

The "Source" option and the "Show avatar/photo" toggle are removed. There is no Source option; `is_public` already requires `is_wall_of_love`, so the embed shows only what the owner approved.

Any change re-renders the **live preview** on the right side using real testimonials from the selected space (read through `scopePubliclyVisible()`).

**Output: one copyable snippet, addressing the Space by its `public_id`:**

```html
<div data-testimonial-space="{public_id}"></div>
<script src="{APP_URL}/embed.js" async></script>
```

One embed per Space — the snippet format hard-codes this. Renaming the slug does not affect the embed.

**embed.js** is dependency-free, lightweight, renders in an isolated container (Shadow DOM or iframe), and loads the public JSON endpoint `GET /api/spaces/{public_id}/testimonials`. The embed snippet always loads `{APP_URL}/embed.js`, a permanent URL that never changes (customers paste it on their own sites). In v1 the app serves it directly with a short cache. Versioned files or a CDN may be added behind that URL in v2.

**Public embed endpoint:**

The endpoint looks the Space up **including soft-deleted rows**, then branches:

| Outcome | Response |
|---|---|
| Unknown `public_id` | `404` |
| Soft-deleted Space | `200` with `{"config": null, "testimonials": []}` |
| Live Space | rows from `scopePubliclyVisible()` (i.e. `is_public = 1`) |

The lookup query intentionally omits `AND deleted_at IS NULL`; the branch is decided in code after the row is found.

Response goes through an **Eloquent API Resource** that whitelists fields: `name`, `testimonial`, `rating`, `submitted_at`, `company_name`, `social_url`, `photo_url` (full URL built from `profile_photo`). **`email`, `address`, and any internal ids are never projected.** CORS open for any origin; cacheable ~60 seconds. `social_url` is returned only when its URL scheme is http or https (case-insensitive); otherwise it is `null`.

When `embed_configurations.show_rating = 0`, the API omits `rating` from every item server-side. Don't rely on the client to hide it.

A valid `?limit=N` (positive integer) overrides the saved `item_limit` for that request and may raise or lower it. The effective limit is `min(?limit if valid else item_limit, 50)`. An invalid `?limit` (0, negative, non-numeric, decimal) is ignored. `config` contains only the six option keys (`layout`, `dark_mode`, `animation_enabled`, `background_color`, `show_rating`, `item_limit`), taken from the saved row or from the defaults in `config/embed.php` when no row exists; never `id`, `space_id` or timestamps. Unknown `public_id` returns 404 with body `{"error":"Not Found"}`. Responses carry `Cache-Control: public, max-age=60`.

Existing photos still show on the embed if `profile_photo` is later disabled in `field_config` (display-time check, not a data purge).

Cloudflare in front of the app is an **optional deploy choice**, not a requirement. Caching of `embed.js` and the embed JSON only applies when Cloudflare is actually in front.

---

## 9. Dashboard

Route: `/dashboard` (auth required).

**Counter cards:**
- Total testimonials
- Total customers (`COUNT(DISTINCT email)` across all spaces) — label is "Total customers"
- Spaces used (X of N)
- Average rating (across rated, non-null)
- **Open deletion requests** for the owner's live Spaces

**Open deletion requests list** — a simple list of `deletion_requests` rows with `status = open` that match the owner's live Spaces by `space_id`.

**Deleted Spaces tab** — Spaces deleted less than 30 days ago, with a Restore action (see §7). If the owner is at the Space limit, Restore is blocked and shows the "delete a Space first" notice. Tombstones (> 30 days deleted) are not listed.

**Time-series graph:** testimonial submissions over time.

- Granularity filter: `day`, `week`, `month`.
- Range filter: 7d, 30d, 90d, all time.
- Optional space filter.

Bucketed by **UTC day** at the database level; display in owner's locale. Zero-count days must be filled in application code from the requested range.

Counters, lists, and the graph use **live Spaces only**.

**Free-plan info note (v1):** a non-actionable informational line noting that the user is on the Free plan with the hardcoded limits. No `/billing` route, no upgrade CTA, no upgrade banner — those return with Pro in v2.

**Empty states:** no spaces → "Create your first Space". No testimonials → "Share your link to start collecting."

### 9.1 Purge policy

A scheduled command `purge:run` runs nightly with `withoutOverlapping`. The retention value comes from `config('purge.retention_days') = 30` (`config/purge.php`).

Tombstone Spaces are **never** hard-deleted. The FK cascade on `embed_configurations.space_id` does not fire (the Space row is kept), so the purge job is **explicit**, not cascade-driven.

**Per Space soft-deleted more than 30 days (and not yet purged), in this exact order:**

1. For each of its testimonials (live **and** soft-deleted):
   1. Close any matching open `deletion_requests`:
      ```sql
      UPDATE deletion_requests
      SET status = 'acted', acted_at = NOW()
      WHERE space_id = :space_id
        AND status = 'open'
        AND (testimonial_id = :testimonial_id
             OR (testimonial_id IS NULL AND email = :testimonial_email));
      ```
   2. Delete the photo file from the `public` disk. Ignore only "file not found"; any other error keeps the row for the next run.
   3. `forceDelete()` the testimonial row.
2. Delete the `embed_configurations` row for that Space.
3. **Keep the `spaces` row** (tombstone) — the slug stays claimed forever.

**Per single testimonial soft-deleted more than 30 days:** the same three-step order (close matching `deletion_requests` → delete photo → `forceDelete()`).

Audit goes to a log channel named `purge`, daily, 14-day retention (`'days' => 14` set explicitly in `config/logging.php`). Row IDs and counts only. No personal data.

---

## 10. Non-Goals / Out of Scope (MVP)

Explicitly **not** in this MVP:

- Google / social OAuth (email + password only)
- Onboarding survey, confetti animations
- Multi-tenant / team workspaces / multiple users per account
- Multiple paid plans, annual billing, coupons
- Custom domains
- Video / NPS / brand monitor / email assistant / MCP — testimonial.to's paid-tier features, not this MVP
- AI-generated testimonials, AI summarization, AI social posts
- **User-defined custom fields** — only the 3 fixed optional fields (`company_name`, `social_url`, `profile_photo`) are supported; answers are columns on `testimonials`, not EAV
- i18n / multi-language
- Email verification is not required in MVP. `email_verified_at` column is kept; `MustVerifyEmail` is **not** used. Revisit before launch.
- No owner edit history (originals are overwritten, no audit log of edits).
- **No CAPTCHA in MVP** (honeypot + IP rate limit + email rate limit + 5-minute dedupe).
- Public Wall of Love standalone page (covered by the embed).
- CSV export, testimonial import
- Approval workflows / pending-review state
- Mobile apps
- Social-media publishing integrations
- Timezone-corrected dashboard graphs (UTC bucketing only)
- **Pro plan, Stripe, Cashier, webhooks, `/billing` route, `STRIPE_PRO_PRICE_ID` env var, `stripe` log channel, `Plan` enum, `subscribed('default')` derivation.** v1 ships Free plan only with hardcoded limits. All of this is a v2 future path.

**In scope:** password-reset mail is in scope (log mailer in v1, real provider in v2). **Out of scope:** owner notification emails on new testimonials.

---

## 11. Limits

v1 ships the Free plan only. Limits are hardcoded constants in `config/limits.php`.

| Limit | Value | Source |
|---|---|---|
| Spaces per owner | 3 | `config/limits.php` `max_spaces` |
| Testimonials per Space | 100 | `config/limits.php` `max_testimonials_per_space` |

**Enforcement (mandatory, server-side):**
- Space creation blocked at limit; show "Delete a Space or contact support" notice (no upgrade CTA in v1).
- When a Space hits its testimonial cap, the public form locks with "This Space is not accepting responses right now."
- All checks happen server-side — never UI-only.
- Warning banner when usage ≥ 80% of cap.
- Limits are checked **per Space at submission time**.
- **Soft-deleted Spaces/testimonials don't count toward limits.**
- There is **no upgrade path in v1**. v1 is Free-only; v2 adds Pro.

**Boundary race accepted:** at 99/100, two simultaneous submissions may both pass and the Space lands at 101. The cap is a soft cap, not an invariant.

**Restore rules:**
- Restore a soft-deleted testimonial from Trash: allowed even when the Space is at its testimonial cap (the cap only blocks new public submissions).
- Restore a soft-deleted Space from the Deleted Spaces tab: blocked if the owner is already at the Space limit; show the "delete a Space first" notice. After the 30-day purge the Space row is a tombstone and cannot be restored; its slug stays claimed.

One plan only. No tiers, no annual billing, no coupons. Pro is a v2 future path.

### 11.1 (Reserved)

Downgrade section removed for v1 (Free plan only). v2 may reintroduce this section when Pro lands.

---

## 12. Billing & Stripe Integration

**Not in v1.** v1 has no billing integration. The `/billing` route does not exist. There is no Stripe, no Cashier, no `Billable` trait, no `subscriptions` table, no `STRIPE_PRO_PRICE_ID` env var.

v2 future path: install Laravel Cashier, add the `Billable` trait on `User`, run `cashier:install`, add a Pro plan priced at $9.99/mo, expose `/billing` with Checkout and Customer Portal, register a `POST /stripe/webhook` endpoint with CSRF exclusion and replay-safe handlers. The dedicated `stripe` log channel from v2.2 §12.1 returns with the addition of a `stripe_events` table for idempotency. **None of this is built in v1.**

### 12.1 (Reserved)

Webhook log section removed for v1. Returns with §12 in v2.

---

## 13. Tech Stack (explicit)

- **Framework:** Laravel 11
- **Frontend:** Livewire 3 + Blade. No separate JS framework, no Inertia, no API-only split.
- **Database:** MySQL (Postgres acceptable)
- **Auth:** Laravel Breeze or Fortify (email + password only — see §10). `MustVerifyEmail` is **not** used in MVP.
- **Billing:** **None in v1.** v2 adds Stripe via Laravel Cashier.
- **File storage:** Laravel's **`public` disk** in v1 (served from `storage/app/public` via `php artisan storage:link`). Path `photos/{random}.webp`. Cloudinary or S3-compatible later, **UNVERIFIED**. Profile photos, max 5 MB, jpg/png/webp, re-encoded to webp ~400 px. The `storage:link` step is documented in the README.
- **Logs:** dedicated log channel `purge` takes its destination from config. The `purge` channel must set `'days' => 14` explicitly in `config/logging.php`. (No `stripe` channel in v1; that returns in v2 with the webhook integration.)
- **Mail:** **log mailer** in v1.
- **Charts:** lightweight client-rendered chart library (e.g. Chart.js via Blade/CDN) — server pre-aggregates the time-series.
- **Deployment:** v1 is a demo built production-shaped. Production hosting, real email, a domain, and backups are v2 items.

**v2 prep kept in config:** the trusted-proxy list and the `CLOUDFLARE_HEADERS_ENABLED` switch stay in the PRD as a config option, **off by default**.

### 13.0 Auth throttles
- **Login** — rate limited per email and per IP. (Breeze built-in `ThrottlesLogins`.)
- **Forgot-password, reset-password, register** — Breeze's Livewire/Volt components, **not** POST routes. Route-level `throttle` middleware does **not** see the action body, so rate limiting is enforced **inside the component action** using `RateLimiter::tooManyAttempts` and `RateLimiter::hit`. Limit: **5 attempts per 15 minutes** (`Limit::perMinutes(15, 5)`). Two keys: one per IP, one per `email + IP`. On limit hit, add a validation error "Too many attempts. Try again later." and do not process the action. The named limiter is defined in `AppServiceProvider::boot()` (Laravel 11 has no `RouteServiceProvider`).
- The 6th-forgot/6th-reset/6th-register call within 15 minutes shows the throttle error message (verified by Pest tests calling the action directly, not via HTTP).

(Email verification throttling removed — verification is not in MVP.)

### 13.1 Trusted proxies (optional, if a proxy/CDN is in front)
- Cloudflare is **not assumed**. If a proxy or CDN is later placed in front of the app, the IP list is committed in config (no fetch at boot).
- Header trust is behind a config switch (e.g. `CLOUDFLARE_HEADERS_ENABLED`), **off by default**.
- README says: "verify ranges before deploy."

### 13.2 Privacy & deletion requests
- `/privacy` is a static page that states: what is collected, why, how long, how to request removal, and links to the consent wording in `config/consent.php`.
- `/privacy/request-deletion` is a form with: `email`, `space_slug`, optional `testimonial_id`, honeypot. Writes a `deletion_requests` row (see §3.8). Throttled at 5 requests/hour per IP. The server resolves `space_id` from `space_slug` using `Space::withTrashed()` — if no Space matches, `space_id` is `null` (and `space_slug` is kept as the respondent typed it). If `testimonial_id` is given, it is accepted only if that testimonial belongs to the resolved Space; otherwise `testimonial_id` is stored as `null`. The respondent sees the same thank-you page in every case — the form never reveals whether an ID exists.
- **Flow:** the owner sees open requests on the dashboard (§9), keyed to their **live Spaces** by `space_id`. Requests for soft-deleted Spaces are not shown until the Space is restored (the README notes this). The owner deletes the testimonial (it goes to Trash). Then the owner presses **Forget** (Trash only). Forget runs the three-step order: (a) UPDATE matching `deletion_requests` to `status='acted'`, `acted_at = now`; (b) delete the photo file from the `public` disk, ignoring only "file not found"; (c) `forceDelete()` the testimonial row. The `testimonial_id` FK on `deletion_requests` is `nullOnDelete`, so when the testimonial is force-deleted (by Forget or the purge job) the FK on the matching request is set to `null` and the request stays `acted`. The purge job runs the same `acted`-update before force-deleting past 30 days.
- No automatic enforcement in MVP.
- This is **not legal advice**. Review before launch.

---

## 14. Acceptance Criteria

### Authentication
- [ ] User can register, log in, log out, reset password.
- [ ] All authenticated routes require login.
- [ ] No social login providers.
- [ ] Email verification is not required in MVP; `MustVerifyEmail` is not used.
- [ ] Login, forgot-password, reset, and signup are rate limited per email and per IP.

### Spaces
- [ ] User can create a Space with Name (max 120, default = title), Slug (max 60, auto-suggested, reserved-list checked, collision suggests `-2`, `-3`), title, subtitle, ask, theme.
- [ ] Name, Email, Address are enabled + required by default (always).
- [ ] `company_name`, `social_url`, `profile_photo` each have enabled + required toggles in `field_config`.
- [ ] Rating toggle (on/off) works per space; stored as `rating_enabled`.
- [ ] Theme selector has `minimal_light`, `minimal_dark`, `soft_color` options.
- [ ] Slug is globally unique; collisions get `-2`, `-3` suffix.
- [ ] Public URL is shown on a success page after creation.
- [ ] `public_id` is auto-created at Space insert (immutable).
- [ ] Delete is a soft delete (`deleted_at` set); slug remains claimed forever (tombstone row kept after purge).
- [ ] **Deleted Spaces tab** lists Spaces deleted less than 30 days ago; offers Restore.
- [ ] **Restore Space** is blocked if the owner is at the Space limit ("delete a Space first" notice); tombstoned Spaces cannot be restored.

### Public submission
- [ ] `/s/{slug}` requires no login.
- [ ] Form shows only enabled fields; enforces required server-side via one FormRequest.
- [ ] Name, Email, Address always present and required.
- [ ] Rating appears only when `rating_enabled=true`.
- [ ] Consent checkbox stored as `consent_given`. When given, `consented_at` and `consent_text_version` are written. Not backfilled.
- [ ] Rate-limited + IP-spam-validated. **Email rate limit: 5 submissions/hour/email/space**, alongside the existing 5/hour per IP per Space.
- [ ] Testimonial body ≤ 2,000 chars; text is trimmed; zero-width chars (U+200B-U+200D, U+FEFF) are stripped; Unicode normalization needs PHP `intl` and is skipped if `intl` is missing; reject whitespace-only, emoji-only, zero-width-only.
- [ ] Photo ≤ 5 MB; jpg/png/webp only; `getimagesize()` header check; hard reject > 4,000 px on either side; re-encode to webp ~400 px; flat random filename; original filename never stored; file deleted if DB insert fails; error messages never reveal file paths.
- [ ] Funny confirmation message appears after submit.
- [ ] Unknown / soft-deleted slug returns 404.
- [ ] When Space is at testimonial cap, form shows the lock message.
- [ ] Duplicate (same email + same testimonial + same space within 5 minutes) returns thank-you only, does not save, does not count.
- [ ] No CAPTCHA in MVP.

### Consent
- [ ] Consent wording lives in `config/consent.php` under a version key.
- [ ] `/privacy` links to current and previous wording.
- [ ] Owner can press **Withdraw consent** — sets `consent_given = 0` **and** `is_wall_of_love = 0`; keeps `consented_at` and `consent_text_version`.
- [ ] No real customer data collected until wording is final.

### Inbox
- [ ] Owner can favorite (⭐), wall-of-love (❤️), hide, edit (text + rating), delete (soft).
- [ ] Wall-of-Love toggle is blocked while `consent_given = 0`, including bulk actions.
- [ ] Favorites sort to top within their space (`is_favorite DESC, submitted_at DESC`).
- [ ] Hidden items stay in inbox but are excluded from embeds.
- [ ] Inbox card shows name, email, address, company (if set), photo (if set), rating, testimonial text, submitted_at, consent badge.
- [ ] Trash view is present. Edit does not keep original; no "edited" marker.
- [ ] **Trash Restore** — clears `deleted_at`; `is_public` recalculates automatically; flags come back as they were; allowed even when the Space is at its testimonial cap.
- [ ] **Withdraw consent** — `InboxRow::withdrawConsent(Testimonial $t)` sets `consent_given = 0` **and** `is_wall_of_love = 0`; keeps `consented_at` and `consent_text_version` unchanged as audit; `is_public` recomputes to 0. (No POST route — the method is called by Livewire only.)
- [ ] **Forget now** in Trash only: one confirm dialog with the name; runs the three-step order (close matching `deletion_requests` to `acted`/`acted_at = now` → delete photo file from `public` disk, ignoring only "file not found" → `forceDelete()` the testimonial row). The FK `testimonial_id` on the matching request is set to null (`nullOnDelete`).
- [ ] Inbox, dashboard, and counts use live Spaces only.

### Embeds
- [ ] Builder supports `masonry` + `carousel` only.
- [ ] Builder exposes layout, dark mode, animation, background color, show-rating, max-count (capped 50). No "Source" option. No "Show avatar/photo" toggle.
- [ ] Live preview updates instantly on any change.
- [ ] Generated snippet uses `data-testimonial-space="{public_id}"`, not embed id.
- [ ] Generated snippet works on a plain external HTML page; renames of `slug` do not break it.
- [ ] Embeds read through `scopePubliclyVisible()`; non-consented and hidden testimonials never appear.
- [ ] `is_public` is a STORED generated column on `testimonials`.
- [ ] API Resource never projects `email`, `address`, or internal ids. `photo_url` is the full URL built from `profile_photo`.
- [ ] Unknown `public_id` → 404. Soft-deleted Space → 200 `{"testimonials": []}`. Live Space → rows from `scopePubliclyVisible()`. The Space lookup query intentionally omits `AND deleted_at IS NULL`; the branch is in code after the row is found.
- [ ] Embed JSON cache ~60s.
- [ ] Existing photos still show on the embed if `profile_photo` is later disabled.
- [ ] embed.js is served at the permanent URL `{APP_URL}/embed.js` with a short cache in v1. Versioned files or a CDN may sit behind that URL in v2.

### Dashboard
- [ ] Total testimonials, total customers (label "Total customers"), spaces-used (X of 3), average rating, open deletion requests count shown.
- [ ] Open deletion requests list for owner's live Spaces, matched by `space_id`.
- [ ] Deleted Spaces tab with Restore, listing Spaces deleted less than 30 days ago.
- [ ] Time-series graph with day/week/month granularity and 7d/30d/90d/all ranges works.
- [ ] Graph buckets by UTC day; zero-count days filled in app code.
- [ ] Counters, lists, graph use live Spaces only.
- [ ] Free users see a "you're on the Free plan" notice (no upgrade CTA in v1).

### Purge
- [ ] `purge:run` runs nightly with `withoutOverlapping`.
- [ ] Retention 30 days, one config key.
- [ ] Per testimonial: photo file deleted first (ignore only "file not found"); row force-deleted after.
- [ ] Per Space > 30 days: testimonials and photos purged, Space row kept as tombstone.
- [ ] `purge` log channel, daily, 14-day retention (`'days' => 14` explicit in `config/logging.php`), row IDs and counts only, no personal data.

### Billing
- [ ] **Not in v1.** Free plan only; no billing integration, no `/billing` route, no Stripe, no Cashier, no webhooks. v2 future path.

### Privacy & deletion requests
- [ ] `/privacy` static page with: what is collected, why, how long, how to request removal, link to consent wording.
- [ ] `/privacy/request-deletion` form (email, space slug, optional testimonial id, honeypot), throttled at 5/hr per IP, writes `deletion_requests` (with `space_id` resolved server-side via `Space::withTrashed()`; if no Space matches, `space_id = null`; `testimonial_id` only if it belongs to the resolved Space; `space_slug` stored as typed). Same thank-you page in every case.
- [ ] Owner sees open requests on dashboard, matched by `space_id`; deletes testimonial → Trash → presses Forget; `deletion_request` set to `acted` with `acted_at`. The `testimonial_id` FK on the request is `nullOnDelete`.
- [ ] No automatic enforcement in MVP. README: review before launch. This is not legal advice.

### Tech / throttles / proxies
- [ ] File storage goes through one storage interface: local disk in v1; Cloudinary or S3-compatible later (**UNVERIFIED**).
- [ ] Log channels (`stripe`, `purge`) read their destination from config and set `'days' => 14` explicitly in `config/logging.php`.
- [ ] Mail uses the log mailer in v1.
- [ ] Trusted-proxy list and `CLOUDFLARE_HEADERS_ENABLED` switch stay in config (off by default) as v2 prep.
- [ ] If a proxy/CDN is later placed in front, its IP list is committed in config; header trust is behind the switch (off by default); README says verify ranges before deploy.

---

## 15. Suggested Build Order

1. Project + DB migrations + auth (no Cashier, no Stripe)
2. Spaces CRUD + `field_config` JSON + themes + success page + `public_id` generation + hardcoded Free limits in `config/limits.php`
3. Public submission page + validation + photo pipeline + confirmation
4. Inbox + all actions (with `is_wall_of_love` guard) + Trash + Forget + Restore (testimonial + Space)
5. Dashboard counters + time-series graph (UTC bucketing) + open deletion requests + Deleted Spaces tab
6. Embed builder + `embed.js` (served directly at `{APP_URL}/embed.js`) + public embed API (using `public_id` + `is_public` scope + Eloquent API Resource)
7. **Seed data and demo readiness:** full fixture seeder (Maya, Dev, Priya, Ben — all on Free; Dev's Spaces at 100/12 to exercise cap), a plain HTML test page for the embed, README demo script (logins and walkthrough).
8. **Deploy and operate (v1):** scheduler cron line in place; `purge:run` working locally; `php artisan` backup script with restore rehearsal; `/local` + dashboard.

**Removed from v1 (not stubbed, not deferred — see D2):** the original 9-step plan's Step 6 "Stripe + webhooks + billing page + upgrade banner + downgrade notice" is **not built in v1**. These items become a v2 future path (Cashier, `/billing`, webhooks, Pro plan, `stripe_events` table) — see §16.

---

## 16. Production roadmap (v2)

v1 is a demo built production-shaped. v2 is what stands the SaaS up for real use. **v2 is where Stripe, Pro plan, webhooks, and real email enter the product.** Every third-party limit is **UNVERIFIED** until verified against the chosen provider.

1. **Stripe and Pro plan** — install Laravel Cashier; add the `Billable` trait on `User`; run `cashier:install`; expose `/billing` with Checkout and Customer Portal; register a `POST /stripe/webhook` endpoint with CSRF exclusion and replay-safe handlers; introduce a `stripe_events` table for idempotency; add a `stripe` log channel with `days => 14`; price Pro at $9.99/mo; add Pro limits (25 Spaces / 1,000 testimonials per Space) to `config/limits.php` behind a `Plan` enum.
2. **Domain and host with scheduler** — domain registered; host chosen and configured with the scheduler cron line; `purge:run` actually fires nightly.
3. **Real email provider** — replace the v1 log mailer; configure SPF/DKIM/DMARC on the sender domain; switch transactional flows (password reset, future owner notifications) to the real provider.
4. **Email verification** — enable `MustVerifyEmail` on `User`; add a verify-mail flow and a signed-route middleware.
5. **Production file storage and CDN** — move photo storage behind one storage interface (Cloudinary or S3-compatible, **UNVERIFIED**); place a CDN behind the embed-snippet URL `{APP_URL}/embed.js` for hot cache.
6. **Backups and restore rehearsal** — daily DB snapshot plus photo-storage snapshot; scheduled restore rehearsal with a written runbook.
7. **Error tracking** — integrate an error tracker; wire it to log channels (`stripe`, `purge`, default).
8. **Legal review** — review consent wording in `config/consent.php`, `/privacy`, and the `/privacy/request-deletion` flow.
9. **Security review** — review CSRF exclusions, rate limits, trusted-proxy switch, password-reset flow, and (v2) webhook signature verification.
10. **Load test** — exercise the embed endpoint (`GET /api/spaces/{public_id}/testimonials`) at realistic and burst traffic; confirm cache headers, CORS, and JSON payload sizing.

---

## 17. OPEN items

- **Production hosting** — v1 is a demo. v2 selects a host and a domain; OPEN until §16 runs.
- **Production email** — v1 uses the log mailer. v2 picks a real email provider and configures SPF/DKIM/DMARC; OPEN until §16 runs.
- **Stripe / Pro plan** — v1 is Free-only. v2 installs Cashier, adds the `Billable` trait, exposes `/billing`, registers the Stripe webhook, and adds a `stripe_events` table for idempotency. OPEN until §16 runs.

---

## 18. UNVERIFIED items

- **Cloudflare** (free plan) — not assumed; only relevant if a proxy/CDN is later placed in front.
- **Cloudinary or S3-compatible storage** — not assumed; v1 uses local disk.
- **Cloudflare R2** — likely requires a card (third-party reports; not read on the official page). **Not assumed in v1** because the user has no card. v2 future path only.
- **Oracle Cloud Always Free** — likely requires a card and the "always free" instance can be reclaimed if idle (third-party reports; not read on the official page). **Not assumed in v1** because the user has no card. v2 future path only.
- **Resend (or any email provider)** — not assumed in v1. A verified domain is needed to email anyone other than your own Resend account address (checked in Resend's docs). v1 uses the log mailer.
- **Stripe transaction fees** — out of scope for v1; verify before v2 launch if Pro is added.
- **Intervention Image** — Laravel package lists PHP 8.1+ and Laravel up to 11 (fits). UNVERIFIED: whether the host's GD build can write WebP (check `gd_info()`).
- **Host file-storage behavior between deploys** — verify the chosen v2 host keeps `storage/app` files across deploys.
- **PHP `intl` extension on the host** — required for the Unicode-normalization step in §6. UNVERIFIED.
- **4,000 × 4,000 decode memory** — Intervention Image memory use when decoding very large images before the hard reject. UNVERIFIED.
- **MySQL STORED expression with `deleted_at IS NULL`** — confirm the local MySQL server accepts `deleted_at IS NULL` inside a STORED expression. Test in a local Pest test.
- **Cloudflare free-plan behavior** for `embed.js` and the embed JSON — caching only applies if Cloudflare is actually in front of the app.
- **Animated GIF re-encode** — Intervention Image's webp re-encode of an animated GIF produces a static first-frame webp. The 4000 px header check passes on the first frame; the embed shows a non-animated thumbnail. Accepted.
- **Per-Space frame-ancestor policy** — v1 embed has no `frame-ancestors` CSP. Third-party sites can embed the wall anywhere. v2 may add per-Space setting.
- Third-party price/limit values for any v2 service chosen — verify before launch. Each "free tier" must NOT require a card on file.