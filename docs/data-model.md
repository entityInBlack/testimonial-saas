# Data Model: Testimonial Collection SaaS

**Source:** 37 screenshots of the instructor's data model document (Module 13-06), a companion to `PRD.md`.
**Status in the document:** agreed, not yet implemented, no code written.
Where it differs from PRD v2.5, the data model is authoritative. Items marked **[Guess]** are not on screen.

**Version:** 2.5 — reconciled with `final PRD.md` v2.5 and `build order.md` v2.6. v1 ships Free plan only; Pro plan, Stripe, Cashier, webhooks removed.
**v2.5 alignment:** aligned slug-check route to `routes/web.php` (no `/api/` prefix), added `withTrashed` lookup + 60-char slug trim + reserved-list check + dropped the "404 if user has no live Spaces" rule; withdraw-consent is now `InboxRow::withdrawConsent(Testimonial $t)` only (no POST route, no `/api/inbox/{testimonial}/withdraw-consent`); also sets `is_wall_of_love = 0` on withdraw; embed response is `{config, testimonials}` with soft-deleted Space returning `{config: null, testimonials: []}`; deletion request form uses `withTrashed` resolution and the Forget/purge order runs the `acted`-update BEFORE forceDelete; `deletion_requests` rows for soft-deleted Spaces are not shown on the dashboard until the Space is restored; `App\Support\Limits` helper dropped (use raw `config('limits.*')`); §8.14 divergence entry removed.

---

## 1. Reference scenario (the fixture, and the shape the seeders should produce)

| User | Plan | Space | Slug | Testimonials |
|---|---|---|---|---|
| Maya Sharma (`maya@brightcopy.co`) | Free | Brightcopy Client Wins | `brightcopy-wins` | 6 |
| Maya Sharma | Free | Course Launch Feedback | `course-launch` | 3 |
| Dev Okafor (`dev@shiplog.io`) | Free | Shiplog Product Reviews | `shiplog-reviews` | 100 |
| Dev Okafor | Free | Shiplog Beta Testers | `beta-testers` | 12 |

- **Priya Raman** (`priya@nimbus.dev`) submitted to both of Dev's Spaces. This is why "unique respondents" counts distinct emails across Spaces, and why `email` is a column rather than an EAV row.
- **Ben Fischer** is on Dev's Wall of Love and hidden. This is why `is_wall_of_love` and `is_hidden` are separate flags.

## 2. Entity overview (ERD)

| From | Label | To | Cardinality |
|---|---|---|---|
| `users` | owns | `spaces` | 1 to 0..many |
| `spaces` | collects | `testimonials` | 1 to 0..many |
| `spaces` | configures | `embed_configurations` | 1 to 0..1 |
| `spaces` | receives | `deletion_requests` | 1 to 0..many |

- Ownership is a **single hop**: `testimonials.space_id` → `spaces.user_id`. Every policy check is `$space->user_id === $user->id`. There is no account or team layer.
- **New tables (4):** `spaces`, `testimonials`, `embed_configurations`, `deletion_requests`. `users` comes from the Laravel auth starter kit. v1 has **no `subscriptions` / `subscription_items` tables** (Cashier not installed) and no `Billable` trait on `User` (see §3.5).
- **No EAV.** Custom respondent fields are explicitly **out of scope** for MVP. Per-Space field toggles live in a JSON column on `spaces` (see §3.2). Answers to optional fields are nullable columns on `testimonials` (see §3.3).

---

## 3. Tables

### 3.1 `users`
Standard Laravel auth only. **No Cashier, no `Billable` trait, no Stripe columns.** v1 has no billing integration.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `name` | varchar(120) | |
| `email` | varchar(180) | unique |
| `email_verified_at` | timestamp null | PRD §4. `MustVerifyEmail` is **not** used in MVP. |
| `password` | varchar(255) | |
| `remember_token` | varchar(100) null | |
| `created_at` / `updated_at` | timestamp | |

**No `plan` column**, no `pm_type`, no `pm_last_four`, no `trial_ends_at`, no `stripe_id`. v1 has one plan (Free); v2 may add them when the project ships Pro. **No `timezone` column** (§7.1).

### 3.2 `spaces`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `user_id` | bigint FK → users.id | cascade on delete |
| `name` | varchar(120) | internal identification (form field, max 120, required, default = title) |
| `slug` | varchar(60) | **globally unique**; auto-suggested from title; on collision the form suggests `-2`, `-3` |
| `public_id` | char(12) | unique, non-sequential, immutable |
| `title` | varchar(160) | public |
| `subtitle` | varchar(255) null | public |
| `ask` | text | the testimonial request |
| `theme` | enum | `minimal_light` \| `minimal_dark` \| `soft_color` |
| `rating_enabled` | boolean | default `true` |
| `field_config` | JSON | per-Space toggles for the 3 fixed optional fields — see below |
| `created_at` / `updated_at` | timestamp | |
| `deleted_at` | timestamp null | soft delete; row stays as a tombstone after the 30-day purge so the slug stays claimed |

**Indexes:** `unique(slug)`, `unique(public_id)`, `index(user_id, deleted_at)`.

**`field_config` JSON column:**

```json
{
  "company_name":  { "enabled": true,  "required": false },
  "social_url":    { "enabled": false, "required": false },
  "profile_photo": { "enabled": false, "required": false }
}
```

Three keys, three states each (`enabled` × `required`). Name, Email, Address are **not** stored here — they are always-enabled-required columns on `testimonials` and not user-configurable. User-defined custom fields are **not** supported in MVP (see §10).

**Two public identifiers, deliberately**

| Column | Job | Audience | Mutable? |
|---|---|---|---|
| `slug` | Shareable collection URL, `/s/brightcopy-wins` | respondents | yes, user-editable |
| `public_id` | Embed key, `<div data-testimonial-space="9xKf2mQr7bZa">` | external sites | **never** |

They cannot be one column. If embeds used `slug`, renaming `brightcopy-wins` to `client-wins` would silently break every embed already live on Maya's clients' sites.

**Slug rules**
- Globally unique, enforced by a **database** unique index (application-level checks race).
- Route is `/s/{slug}`, not the root. At the root, slugs would collide with every app route (`/dashboard`, `/billing`) and need an ever-growing reserved list.
- Reserved anyway, defensively: `s`, `api`, `admin`, `login`, `register`, `dashboard`, `billing`, `embed`, `assets`, `up`.
- On collision, the create form suggests a suffixed slug (`course-launch-2`, `course-launch-3`).

#### Reads/writes
- Slug-collision probe — **GET /spaces/check-slug?slug=X** (auth required; lives in `routes/web.php`) returns `{available: bool, suggested: string|null}`. The probe looks up `Space::withTrashed()` (so soft-deleted and tombstoned slugs count as taken), checks the reserved list (`s`, `api`, `admin`, `login`, `register`, `dashboard`, `billing`, `embed`, `assets`, `up`), and trims any appended `-N` suffix so the final slug is at most 60 chars. `suggested` is the next free `-2`/`-3` suffix or `null` if the slug is free as-is. The probe always returns `200` with the JSON above (it does **not** 404 when the user has no live Spaces — that's a different concern). The database `unique(slug)` index is the source of truth; the probe is a UX aid, not a security boundary.

### 3.3 `testimonials`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `space_id` | bigint FK → spaces.id | cascade on delete |
| `name` | varchar(120) | always required (PRD §5.2) |
| `email` | varchar(180) | always required, **never public** (PRD §13) |
| `address` | varchar(255) | always required, **never public** |
| `company_name` | varchar(160) null | optional per space (`field_config.company_name.enabled`) |
| `social_url` | varchar(255) null | optional per space |
| `profile_photo` | varchar(255) null | optional per space; storage path |
| `testimonial` | text | always required, max 2,000 chars |
| `rating` | tinyint unsigned null | 1..5, null when the Space disables rating |
| `consent_given` | boolean | default `false` |
| `consented_at` | timestamp null | set when `consent_given = 1`; not backfilled |
| `consent_text_version` | varchar(40) null | version key from `config/consent.php`; set with `consented_at`; not backfilled |
| `is_favorite` | boolean | default `false`, ordering only |
| `is_wall_of_love` | boolean | default `false` |
| `is_hidden` | boolean | default `false` |
| `is_public` | boolean **STORED generated** | see below |
| `submitted_at` | timestamp | domain timestamp, distinct from `created_at` |
| `created_at` / `updated_at` | timestamp | |
| `deleted_at` | timestamp null | soft delete (PRD §8); row is hard-deleted by the purge job after 30 days |

**Indexes**

| Index | Columns | Serves |
|---|---|---|
| `idx_public` | (`space_id`, `is_public`, `is_favorite`, `submitted_at`) | embed reads, index-ordered, no filesort |
| `idx_space_live` | (`space_id`, `deleted_at`, `submitted_at`) | limit count, collection graph, inbox |
| `idx_respondents` | (`space_id`, `deleted_at`, `email`) | `COUNT(DISTINCT email)` |

**The `is_public` generated column**

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

- PRD §8's four-condition rule runs on the embed endpoint, live preview, Wall-of-Love empty state and dashboard. Hand-written at four places, one would eventually be wrong (Ben appearing on a customer's site after being hidden). So the rule lives in the schema.
- `scopePubliclyVisible()` stays as the app-facing API and resolves to `where('is_public', true)`.
- **Verify before building:** confirm your MySQL server accepts `deleted_at IS NULL` inside a STORED expression. **Gaps not closed.**
- **Cost accepted:** recomputed on every insert/update (cheap on a read-heavy table). Changing the §8 rule later needs a deliberate, reviewed migration.

**Why `is_wall_of_love` and `is_hidden` stay separate:** hide is an **override layered on a publish decision, not a replacement**. With one `visibility` enum, hiding Ben overwrites `published`, and unhiding has nowhere to return to (he lands silently in `inbox`, demoted). Two booleans restore him automatically.

| # | Respondent | consent_given | is_wall_of_love | is_hidden | deleted_at | is_public |
|---|---|---|---|---|---|---|
| 41 | Priya Raman | 1 | 1 | 0 | none | **1** |
| 42 | Tom Alvarez | 1 | 0 | 0 | none | 0 |
| 43 | Sara Nkemi | 0 | 0 | 0 | none | 0 |
| 44 | Ben Fischer | 1 | 1 | **1** | none | 0 (suppressed, publish intact) |
| 45 | Ana Ruiz | 1 | 1 | 0 | **set** | 0 |

**Application rule:** block `is_wall_of_love = 1` while `consent_given = 0`. The generated column already makes Sara unpublishable, but a toggle that can never take effect is a UI that lies.

**Why `name`, `email`, and `address` are columns, not config rows**
1. They are always enabled and required. A field that cannot vary is part of what a testimonial *is*, not configuration.
2. It makes the email (and address) guarantee **structural, not procedural**. With no EAV, no embed query path can ever reach an email value via a generic "dump every value" code path; the columns themselves are simply not projected.
3. It keeps "unique respondents" an indexed aggregate:

```sql
SELECT COUNT(DISTINCT email) FROM testimonials
WHERE space_id IN (3, 4) AND deleted_at IS NULL;
```

#### State changes
- Withdraw consent — Livewire method **`InboxRow::withdrawConsent(Testimonial $t)`** on the inbox row Livewire component. **No POST route** — Livewire calls the method directly via its own endpoint, and adding a controller-backed route would duplicate the authorization logic. Authorizes against `$space->user_id === $user->id`. Sets `consent_given = 0` **and** `is_wall_of_love = 0` (so the UI never shows a Wall-of-Love badge on a row that cannot be public). Does not touch `consented_at` or `consent_text_version` (audit fields preserved). Does not delete the row. The `is_public` STORED column recomputes automatically.

### 3.4 `embed_configurations`
Exactly **0 or 1 per Space**. The row is optional: with no saved row, code defaults apply, so the Embed page renders before it is ever saved.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `space_id` | bigint FK → spaces.id | **unique**, cascade on delete |
| `layout` | enum | `masonry` \| `carousel`, default `masonry` |
| `dark_mode` | boolean | default `false` |
| `animation_enabled` | boolean | default `true` |
| `background_color` | char(7) null | `#RRGGBB` |
| `item_limit` | smallint unsigned | default `12`, **capped at 50** |
| `show_rating` | boolean | default `true` |
| `created_at` / `updated_at` | timestamp | |

**No `display_options` JSON column.** The "Source" option and the "Show avatar/photo" toggle are **not** represented here. There is no Source option; `is_public` already requires `is_wall_of_love`, so the embed shows only what the owner approved. Photo display follows `profile_photo IS NOT NULL`.

**Where visibility toggles live**

| What | Storage | Visibility toggle |
|---|---|---|
| Respondent name | `testimonials.name` | always shown |
| Testimonial text | `testimonials.testimonial` | always shown |
| Address | `testimonials.address` | **never shown**, not addressable |
| Email | `testimonials.email` | **never shown**, not addressable |
| Rating | `testimonials.rating` | `embed_configurations.show_rating` |
| Company | `testimonials.company_name` | owner-side (`field_config.company_name.enabled`); embed always shows if populated |
| Social | `testimonials.social_url` | owner-side; embed always shows if populated |
| Photo | `testimonials.profile_photo` | owner-side; embed always shows if populated (existing photos still show if `profile_photo` is later disabled in `field_config`) |

- `item_limit` is capped because an embed of 1,000 testimonials would hang someone's homepage.
- **One embed per Space** is fixed by the snippet format: `<div data-testimonial-space="SPACE_PUBLIC_ID">` addresses a Space, not an embed.

### 3.5 Cashier tables
**Not in v1.** `subscriptions` and `subscription_items` do not exist. v2 future path: install Cashier, add the `Billable` trait on `User`, run the standard Cashier migrations.

### 3.6 `deletion_requests`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `email` | varchar(180) | |
| `space_slug` | varchar(60) | value the respondent typed |
| `space_id` | bigint nullable | FK → spaces.id, **nullOnDelete** (set null if the Space is hard-deleted) |
| `testimonial_id` | bigint nullable | FK → testimonials.id, **nullOnDelete** (set null when the testimonial is force-deleted by Forget or the purge job) |
| `status` | enum | `open` \| `acted` |
| `created_at` | timestamp | |
| `acted_at` | timestamp null | set when the matching testimonial is forgotten |
| `updated_at` | timestamp | from `timestamps()` in the migration |

**Indexes:** `idx_deletion_space_status` on (`space_id`, `status`); `idx_deletion_email_status` on (`email`, `status`).

Rows are never hard-deleted in MVP; only `status` changes to `acted`. The owner dashboard matches open requests to a Space by `space_id` — but **only** for **live** Spaces (the dashboard hides requests whose `space_id` points at a soft-deleted Space until the owner restores the Space; the README notes this).

**Forget order (Inbox Trash) — runs BEFORE the testimonial is `forceDelete()`d:**
```sql
UPDATE deletion_requests
SET status = 'acted', acted_at = NOW()
WHERE space_id = :space_id
  AND status = 'open'
  AND (testimonial_id = :testimonial_id
       OR (testimonial_id IS NULL AND email = :testimonial_email));
```
Then delete the photo file from the `public` disk (ignore only "file not found"), then `forceDelete()` the testimonial row.

The purge job runs the **same** `acted`-update before force-deleting any testimonial past 30 days.

---

## 4. Key queries

### 4.1 Embed read (hottest path)

```sql
-- 1. Space lookup by public_id, INCLUDING soft-deleted rows.
--    The branch is decided in code after the row is found:
--      not found         -> 404 {"error":"Not Found"}
--      soft-deleted      -> 200 {"config": null, "testimonials": []}
--      live              -> 200 {"config": {...}, "testimonials": [...]}
--                           (config = only the six option keys
--                            (layout, dark_mode, animation_enabled, background_color, show_rating, item_limit),
--                            taken from the saved row or from the defaults in
--                            config/embed.php when no row exists; never id, space_id
--                            or timestamps)
SELECT id, deleted_at FROM spaces WHERE public_id = ?;
```

```sql
-- 2. Testimonials, index-ordered off idx_public, no filesort.
--    Explicit column list. `email` and `address` must never enter the projection.
--    The Eloquent API Resource maps profile_photo -> photo_url.
--    social_url is returned only when its URL scheme is http or https (case-insensitive); otherwise it is null.
--    When embed_configurations.show_rating = 0, "rating" is omitted from every
--    item server-side (do not rely on the client to hide it).
SELECT id, name, testimonial, rating, submitted_at,
       company_name, social_url, profile_photo
FROM testimonials
WHERE space_id = ? AND is_public = 1
ORDER BY is_favorite DESC, submitted_at DESC
LIMIT ?;
```

A valid `?limit=N` (positive integer) overrides the saved `item_limit` for that request and may raise or lower it. The effective limit is `min(?limit if valid else item_limit, 50)`. An invalid `?limit` (0, negative, non-numeric, decimal) is ignored.

The generated column protects **row** selection, not **column** selection. The embed payload must go through an **Eloquent API Resource** that whitelists fields, never `Testimonial::find()->toArray()`.

### 4.2 Dashboard stats

```sql
-- Total Spaces (live only)
SELECT COUNT(*) FROM spaces WHERE user_id = ? AND deleted_at IS NULL;

-- Total testimonials
SELECT COUNT(*) FROM testimonials
WHERE space_id IN (SELECT id FROM spaces WHERE user_id = ? AND deleted_at IS NULL)
  AND deleted_at IS NULL;

-- Unique respondents (Priya counts once across Dev's two Spaces)
SELECT COUNT(DISTINCT email) FROM testimonials
WHERE space_id IN (...) AND deleted_at IS NULL;

-- Collection graph (7 / 30 / 90 days), grouped by UTC day
SELECT DATE(submitted_at) AS d, COUNT(*) AS c
FROM testimonials
WHERE space_id IN (...) AND deleted_at IS NULL AND submitted_at >= ?
GROUP BY d;
```

Zero-count days don't appear in `GROUP BY` output, so they must be filled in application code from the requested range.

**Label note:** the "Total customers" counter uses this exact wording in both PRD §9 and this document.

### 4.3 Limit check (every public submission)

```php
// Duplicates (PRD §6.2: same email + same testimonial text + same space
// within 5 minutes) are not counted and do not save.
$count = Testimonial::where('space_id', $space->id)->count(); // SoftDeletes -> deleted_at IS NULL
if ($count >= (int) config('limits.max_testimonials_per_space')) {
    // Show lock message "This Space is not accepting responses right now."
    // Never silently discard. (PRD §11)
}
```

> **v2.5 alignment:** `$plan->maxTestimonialsPerSpace()` was a v2.4 helper. v1 has no Plan object; the cap is read directly from `config/limits.php` (data-model §5.1).

---

## 5. Plans and limits

v1 has one plan: Free. Limits are hardcoded constants in `config/limits.php`.

### 5.1 Limits (no `Plan` enum, no `plans` table, no `users.plan` column)

```php
// config/limits.php
return [
    'max_spaces'                 => 3,
    'max_testimonials_per_space' => 100,
];
```

```php
// usage
$maxSpaces  = config('limits.max_spaces');  // 3
$maxPerSpace = config('limits.max_testimonials_per_space');  // 100
```

- **No `Plan` enum:** an enum with one case is a marker for "we used to have more", which is a v2 concern.
- **No `plans` table:** same reason.
- **No `users.plan` column:** would be a constant; redundant.
- **No `subscribed('default')`:** there are no subscriptions in v1.
- v2 future path: replace this with a `Plan` enum backed by a `plans` table or a Cashier subscription lookup.

### 5.2 Downgrade behaviour
Not in v1 (no Pro to downgrade from). v2 may reintroduce this section when Pro lands.

### 5.3 Soft-deleted rows never count, but can be restored
Counting only `deleted_at IS NULL` avoids a hidden number: Dev deletes 45 of 100, his inbox shows 55, yet he'd be blocked at "100". Deleting real testimonials to make room is paying a genuine price, not gaming. The same applies to Spaces: otherwise Maya, at 3/3 on Free after mistyping a slug, could only fix it by deleting a real Space (no upgrade path in v1).

**Restore rules (PRD §7, §11):**
- A testimonial in Trash can be **restored** by clearing `deleted_at`. The `is_public` STORED column recalculates automatically; flags (`is_favorite`, `is_wall_of_love`, `is_hidden`) come back as they were. Restore is allowed even when the Space is at its testimonial cap — the cap only blocks new public submissions, not the return of an existing one.
- A soft-deleted Space can be **restored** from the Deleted Spaces tab within 30 days. Restore is blocked if the owner is already at the Space limit. After the 30-day purge the Space row is a tombstone and cannot be restored; its slug stays claimed.

### 5.4 The boundary race is accepted
At 99/100, two simultaneous submissions both pass and the Space lands at 101. The cap is a **soft cap, not an invariant**; PRD §11 says "never silently discard submissions", so accepting a 101st is the right side to err on.

- Rejected: `SELECT … FOR UPDATE` on the Space row (lock contention on the hot public endpoint, held across a multi-table write of testimonial + photo).
- Rejected: a `spaces.testimonials_count` counter cache (drift across create / soft-delete / restore / force-delete, needs a reconciliation command, and `COUNT(*)` on an index over at most 1,000 rows is sub-millisecond).
- If counting ever becomes expensive, the counter cache is additive.

---

## 6. Security and authorization (PRD §13)

| Requirement | How it is met |
|---|---|
| Users access only their own Spaces | `$space->user_id === $user->id`; single-hop policies |
| Public IDs non-sequential, hard to guess | `public_id char(12)`, random, unique, immutable |
| Email never exposed via embeds | **Structural**: `email` is a column on `testimonials`; no EAV path can leak it; API Resource whitelist |
| Address never exposed via embeds | **Structural**: same as email |
| Escape/sanitize testimonial content | Application layer: sanitize on write, escape on render |
| Restrict photo type and size | `field_config.profile_photo`; JPG/PNG/WebP + 5 MB cap validated on upload; `getimagesize()` header check; > 4,000 px on either side hard-rejected; re-encoded to webp ~400 px |
| Rate limits on auth + public submission | Auth throttles are enforced **inside the Livewire component action** via `RateLimiter::tooManyAttempts` / `hit` (PRD §13.0). Public submission throttles are route middleware: IP and per-email/Space limits (PRD §6). |
| Stripe webhook signatures verified | **Not in v1.** v2 adds `POST /stripe/webhook` (CSRF-excluded) and Cashier signature verification. |
| Cross-origin embed JSON | The public embed endpoint is registered in `routes/web.php` **outside** the `web` middleware group (no session, no cookie, no CSRF). Laravel 11's default `config/cors.php` allows `Access-Control-Allow-Origin: *` for `api/*` paths, which covers `/api/spaces/{public_id}/testimonials`. A Pest test sends an `Origin` header and asserts the response header. The route sets `Cache-Control: public, max-age=60`. |
| All dates in UTC | All timestamps UTC (§7.1) |

The remaining exposure is column selection on `testimonials`, covered by the API Resource whitelist.

---

## 7. Accepted limitations
"Known, decided, and recorded so they are not rediscovered as bugs."

### 7.1 Graph buckets by UTC day
No `users.timezone`. Dev in Lagos (UTC+1): Priya submits at 23:30 UTC on 16 July (00:30 on the 17th for Dev), so the graph bars it on the 16th and "collected today" reads 0.

| User's offset | Submissions on the wrong day |
|---|---|
| UTC+1 (Lagos) | 23:00 to 00:00 UTC daily |
| UTC-7 (Los Angeles) | every submission 17:00 to 24:00 local, roughly a third of the day |

Accepted: it reads PRD §9 literally and keeps the query trivial. Retrofit is not free: adding `users.timezone` later rebases every historical bucket on the day it ships. The upgrade path is a daily rollup table (`space_id`, `local_date`, `count`).

### 7.2 Webhook idempotency
**Not in v1.** v1 has no webhooks and no `stripe_events` table. v2 future path: add a `stripe_events` table with `unique(stripe_event_id)` and INSERT-first (duplicate key = already seen) for idempotency at the database level.

### 7.3 Local webhook log: `stripe` channel
**Not in v1.** v1 has no `stripe` log channel. The `purge` log channel is the only dedicated daily channel in v1, with `days => 14` explicit in `config/logging.php`. v2 reintroduces this section when webhooks land.

### 7.4 Consent records the time and the wording, not just the bit
Consent is a **publicity permission** (§6): name, face, employer and words on a website and on social media. The columns `consent_given`, `consented_at`, and `consent_text_version` together answer "what did Priya agree to, and when?". The wording lives in `config/consent.php` under a version key.

- Rows created before these columns existed **cannot be backfilled**, because what the respondent saw was never captured. No real data exists yet, so this is acceptable.
- **Withdraw consent** sets `consent_given = 0` and `is_wall_of_love = 0` and keeps `consented_at` and `consent_text_version` as audit history. The `is_public` STORED column recomputes to 0 automatically.
- Published versions are never edited. To change wording, add a new version key.

### 7.5 Soft-deleted Spaces hold their slug forever; Restore is a 30-day window
A soft-deleted Space keeps its row, so its slug stays claimed. Maya cannot recreate `course-launch` after deleting it, and neither can anyone else.

- The owner can **Restore** the Space from the Deleted Spaces tab within 30 days. Restore is blocked when the owner is already at the Space limit ("delete a Space first" notice).
- After the 30-day retention, the purge job hard-deletes the Space's testimonials and photos but **keeps the Space row as a tombstone** so the slug stays claimed. Tombstones cannot be restored.
- Testimonials deleted on their own stay in Trash when their Space is restored. Trash shows live Spaces only; to reach a testimonial in a deleted Space, restore the Space first.
- **Do not "fix" with `unique(slug, deleted_at)`.** MySQL treats NULLs as distinct in unique indexes, so two live Spaces (both `deleted_at IS NULL`) could share a slug. That is worse than the leak.
- Rejected: slug mangling on delete (`course-launch__deleted_7`) corrupts the slug on restore; a purge job needs a retention policy. Both are additive if slug exhaustion ever bites.

### 7.6 Testimonial limit may overshoot by one
See §5.4. Deliberate.

---

## 8. Divergences from the PRD (v2.5)

After the v2.5 reconciliation, the data model and the PRD are aligned on the high-impact decisions. The remaining differences are clarifications, not disagreements.

| # | PRD v2.5 says | This model does | Why |
|---|---|---|---|
| 8.1 | `testimonials.address` (column) | `testimonials.address` (column) | **Aligned.** Address is a required locked column, treated identically to Name/Email. |
| 8.2 | `spaces.field_config` JSON with 3 fixed optional fields | `spaces.field_config` JSON with 3 fixed optional fields | **Aligned.** Custom fields are out of MVP scope. |
| 8.3 | `testimonials.company_name`, `social_url`, `profile_photo` (columns) | Same — columns, not EAV | **Aligned.** EAV (`space_fields` / `testimonial_values`) was considered and rejected. |
| 8.4 | `embed_configurations.show_rating` for the rating visibility toggle | Same; `field_config` controls whether optional fields appear on the form, not on the embed | **Aligned.** |
| 8.5 | No `is_public` column specified at the schema level | `is_public` STORED generated column | PRD v2.5 §3.4 adopts this exactly as written here. |
| 8.6 | §5: Space deletion unspecified | Soft delete + 30-day purge, tombstone Space row; Restore within 30 days | Without it, Maya at 3/3 on Free can only fix a typo by deleting real data. PRD v2.5 §3.2 / §11 / §9.1 adopt this. |
| 8.7 | `spaces.rating_enabled` (column) | `spaces.rating_enabled` (column) | **Aligned.** Rating is a `testimonials` column, so the toggle belongs with the data. |
| 8.8 | Consent is `consent_given` only | `consent_given` + `consented_at` + `consent_text_version` | The data model insists on the audit trail; PRD v2.5 §3.4 / §6.1 adopt it. |
| 8.9 | Embed lookup is branch-on-state (404 / 200 empty / live) | Embed lookup query intentionally omits `AND deleted_at IS NULL`; branch decided in code after the row is found | The data model wants the branch in app code so the soft-deleted state is observable, not silently hidden. PRD v2.5 §8 adopts it. |
| 8.10 | `deletion_requests` carries `space_slug` (respondent-typed) and resolves `space_id` server-side | Both columns present, `space_id` nullable FK to spaces.id with `nullOnDelete`, `space_slug` retained as-typed | The owner dashboard matches open requests to a Space by `space_id`; the typed slug is kept for audit. PRD v2.5 §3.8 / §9 / §13.2 adopt it. |
| 8.11 | Restore for testimonials and Spaces | Allowed when conditions hold; soft-deleted testimonials restore even at the cap; soft-deleted Spaces restore only when under the Space limit | The data model insists the testimonial cap doesn't gate restore of existing data. PRD v2.5 §7 / §11 adopt it. |
| 8.12 | Billing: Pro $9.99/mo via Stripe (Checkout, webhooks, /billing) | **Not in v1.** Limits are hardcoded in `config/limits.php`. Pro and Stripe are v2 future paths. | v1 ships Free-only; the user has no card. PRD v2.5 §11 / §12 adopt this. |
| 8.13 | `users` has `stripe_id`, `pm_type`, `pm_last_four`, `trial_ends_at` (Cashier) | **Not in v1.** Standard Laravel auth only. | v1 has no Cashier. v2 reintroduces these when Pro lands. PRD v2.5 §3.1 adopts this. |
**Resolved in v2.5** — these items moved from divergence to decision in this revision (D31, D34–D42):
- `is_public` STORED generated column → settled (PRD §3.4).
- Address as a locked column → settled (PRD §3.4).
- Field configuration as JSON on `spaces` with the 3 fixed optional fields → settled (PRD §3.3).
- No EAV, no user-defined custom fields in MVP → settled (PRD §10).
- Soft delete on Spaces + 30-day purge + tombstone Space row → settled (PRD §3.2 / §9.1).
- Restore for testimonials and Spaces → settled (PRD §7 / §11).
- `deleted_at IS NULL` inside the STORED expression → still to confirm on the local MySQL version (**Gaps not closed**).
- Consent audit trail via `consented_at` + `consent_text_version` → settled (PRD §6.1).
- Embed endpoint behaviors (404 / empty list / live scope) and `photo_url` whitelist → settled (PRD §8).
- Dedupe rule (email + text + space + 5 min) → settled (PRD §6.2).
- **Free plan only in v1** → settled (PRD §11 / §16). Pro is a v2 future path.
- **No Stripe, no Cashier, no webhooks, no `stripe_events` table, no `stripe` log channel, no `/billing` route, no `Plan` enum** → settled (PRD §3.1, §3.6, §3.7, §12, §12.1, §13).
- Hardcoded Free limits (3 / 100) in `config/limits.php` → settled (PRD §11).
- All fixture users on Free; Dev's Spaces at 100 / 12 to exercise cap code → settled (PRD §15).
- Dashboard counter label "Total customers" → settled (PRD §9; this document §4.2).
- Embed snippet loads the permanent URL `{APP_URL}/embed.js`, served directly with a short cache in v1.
- Source option on the embed → removed; `is_public` already requires `is_wall_of_love`.
- Show avatar/photo toggle on the embed → removed; photo is shown iff `profile_photo IS NOT NULL`.
- Notification emails to owners → removed from MVP (no email in v1).
- Deviation: `deletion_requests` gains `space_id` (nullable FK with `nullOnDelete`) next to `space_slug`; the owner dashboard matches requests by `space_id`.

The dashboard label "Total customers" is used in both documents.

---

## 9. Open items (resolve before or during implementation)

- **9.1 Deleting an optional field.** ~~Dev deletes his custom "Job Title" field. What happens to Priya's answer, *VP Engineering*?~~ Out of scope: optional fields are fixed (`company_name`, `social_url`, `profile_photo`) and cannot be deleted per-Space. The owner toggles `enabled`/`required` in `field_config`; the column on `testimonials` is always present and the answer is simply blank when disabled. **CLOSED.**
- **9.2 Purge policy for soft-deleted rows.** See PRD §9.1 — `purge:run`, nightly, `withoutOverlapping`, 30-day retention, photo-then-row, tombstone Spaces, `purge` log channel daily 14 days row-IDs-only (`'days' => 14` explicit in `config/logging.php`). Config keys in `config/purge.php`: `retention_days` (env `PURGE_RETENTION_DAYS`, default 30), `batch_size` (env `PURGE_BATCH_SIZE`, default 50; chunk size for the purge queries), `soft_warning_days` (env `PURGE_SOFT_WARNING_DAYS`, default 5; defined but not read by any code in v1). **CLOSED** (see PRD §9.1).
- **9.3 Profile photo storage.** Disk: Laravel's **`public` disk** in MVP (served from `storage/app/public` via `php artisan storage:link`). Path: `photos/{random}.webp` (flat random). Pipeline: `getimagesize()` header check → > 4,000 px on either side hard-rejected → Intervention Image re-encode to webp ~400 px → DB write → if the DB insert fails, the file is deleted. **Forget** deletes the photo file immediately. The `public` disk is reachable by `embed.js` on third-party sites. **CLOSED.**
- **9.4 Testimonial length validation.** Body max **2,000** chars (PRD §6). Address max **255** chars (column width). Text is trimmed; zero-width characters (U+200B-U+200D, U+FEFF) are stripped; Unicode normalization needs PHP `intl` and is skipped if `intl` is missing.

---

## 10. Future migration paths (deliberately deferred, all additive)

| Want | Path | Cost |
|---|---|---|
| User-defined custom fields | Add `space_fields` + `testimonial_values` EAV; pre-populate with the 3 fixed optional rows; copy existing `testimonials.company_name/social_url/profile_photo` into `testimonial_values` and drop those columns; move per-Space toggles out of `field_config` | **Moderate to high**: breaks the simple "select columns from testimonials" embed query; adds a join on every public read; requires a one-time data migration. Not built for MVP because the field set is closed. |
| Team accounts | Create `accounts`, backfill one per user, add `spaces.account_id`, move Cashier columns off `users` | Moderate: touches live subscription data, so cheaper before revenue scales |
| Per-user slug namespaces | Add `users.handle`, route `/{handle}/{slug}`, relax `unique(slug)` to `unique(user_id, slug)` | Moderate: breaks existing public URLs |
| Timezone-correct graphs | Add `users.timezone` + a daily rollup table (`space_id`, `local_date`, `count`) | Rebases historical buckets on ship day |
| Webhook idempotency | `stripe_events` table, `unique(stripe_event_id)`, INSERT-first | Low, additive. **Not in v1** — no webhooks. v2 path. |
| Strict limit enforcement | `spaces.testimonials_count` + atomic conditional UPDATE | Low, additive |
| Multiple embeds per Space | `embed_field_visibility` pivot **and a new snippet format** (the current `data-testimonial-space` attribute addresses a Space, so the snippet must change first) | Moderate: the blocker is the URL scheme, not the schema |
| Multiple paid plans | `plans` table + admin UI (build both together, or not at all) | Moderate. **Not in v1** — Free only. |
| Stripe integration | Install Laravel Cashier; add `Billable` trait on `User`; run `cashier:install`; expose `/billing`; add Pro plan; webhook + replay-safe handlers | Build a hand-rolled subscription system. **Not in v1**. v2 path. |
| Consent audit trail | `consented_at` + `consent_text_version` | **Done in MVP** (PRD §3.4 / §6.1). Listed here for traceability. |
| Owner edit history | Add `original_testimonial` + `original_rating` to `testimonials` (snapshot of pre-edit values, write-once) | Low, additive. **Not building this for MVP** — current behaviour is edit-overwrites with no history. |
| Email verification | Enable `MustVerifyEmail` on `User`; add a verify-mail flow and a signed-route middleware; revisit before launch | Low, additive. **Not in MVP** — revisit before launch (PRD §10). |
| Real email provider | Replace the v1 log mailer with a free provider + SPF/DKIM/DMARC on a verified domain | Keep the log mailer (current v1 default). **v2 path** — exclude any provider that requires a card. |

---

## Appendix: decision log

| # | Decision | Alternatives rejected |
|---|---|---|
| 1 | Space belongs directly to `User`; v1 has no Cashier/Billable | Account/workspace layer; hybrid `user_id` + nullable `account_id`; Cashier `Billable` on `User` (deferred to v2) |
| 2 | Slug globally unique, route `/s/{slug}`; `public_id` immutable embed key | Per-user slugs + `users.handle`; `public_id` in the public URL |
| 3 | **Field configuration: JSON column on `spaces` covering exactly the 3 fixed optional fields** (`company_name`, `social_url`, `profile_photo`); answers are nullable columns on `testimonials`. **Custom fields are explicitly out of scope for MVP.** | Normalized `space_fields` + `testimonial_values` EAV (rejected: over-engineered for a closed field set; embed queries must stay a single-table read); JSON `field_configuration` with arbitrary keys (rejected: would imply custom fields) |
| 4 | **No EAV. No user-defined custom fields in MVP.** Field set is closed: Name, Email, Address are always required (columns on `testimonials`); the 3 optional fields are fixed and toggleable via `field_config` JSON on `spaces`. | EAV with `space_fields` + `testimonial_values` (rejected: unnecessary for a closed field set, adds a join on the hot embed read); JSON `field_configuration` with user-defined keys (rejected: same reason — implies custom fields) |
| 5 | `name`, `email`, `address`, `testimonial`, `rating` as columns; variation in 3 nullable optional columns on `testimonials` | Pure EAV (rejected, see #4); all fields including company/social/photo as fixed columns (this is what we did — kept on the table) |
| 6 | `is_public` STORED generated column + `idx_public` | Eloquent scope only; single `visibility` enum |
| 7 | UTC-only day bucketing, no `users.timezone` | `users.timezone` + PHP bucketing; `users.timezone` + `CONVERT_TZ` |
| 8 | **Free plan only in v1.** Limits are hardcoded in `config/limits.php` (`max_spaces` = 3, `max_testimonials_per_space` = 100). No `Plan` enum, no `plans` table, no `users.plan` column, no `subscribed('default')` derivation. | `plans` table + `users.plan_id`; denormalized `users.plan`; `Plan` enum with multiple cases. All deferred to v2. |
| 9 | Live `COUNT(*)`, no lock, boundary race accepted | `FOR UPDATE` transaction; `spaces.testimonials_count` counter cache |
| 10 | `embed_configurations.show_rating`; no per-field embed-visibility pivot (optional fields are always shown on the embed if populated); `display_options` dropped; no Source option; no Show avatar/photo toggle (photo is shown iff `profile_photo IS NOT NULL`; `is_public` already requires `is_wall_of_love`) | `embed_field_visibility` pivot; `display_options` JSON; Source option; Show avatar/photo toggle |
| 11 | **No webhooks in v1.** v2 future path: hand-written replay-safe handlers + `stripe_events` table for idempotency. | `stripe_events` table + unique index (deferred to v2); Redis SETNX dedup |
| 12 | **No `stripe` log channel in v1.** The `purge` log channel is the only dedicated daily channel in v1, with `days => 14` explicit in `config/logging.php`. v2 reintroduces the `stripe` channel when webhooks land. | Stripe Dashboard only (deferred); `stripe_events` audit table |
| 13 | `consent_given boolean` + `consented_at` + `consent_text_version` (set on consent, not backfilled; kept on Withdraw consent) | `consent_given` only (rejected: cannot answer "what did they agree to, and when?"); `consented_at` only (rejected: missing wording version) |
| 14 | Space soft delete; 30-day purge hard-deletes testimonials and photos; Space row stays as a tombstone so the slug stays claimed; deleted rows do not count toward limits; **Restore within 30 days**, blocked at the Space limit | Hard delete + FK cascade; soft delete + slug mangling + purge job; soft delete only, no purge (rejected: row accumulation); purge without Restore (rejected: silent data loss) |
| 15 | `address` is a locked required column on `testimonials` (always-on, never public); same treatment as Name and Email | `address` as a `field_config` toggleable field (rejected: PRD requires it always-on-required); dropping address entirely (rejected: in the manager brief) |
| 16 | `deletion_requests` table (email, space_slug, space_id nullable with nullOnDelete, testimonial_id nullable with nullOnDelete, status open\|acted, created_at, updated_at, acted_at; indexes on (space_id, status) and (email, status)); owner-driven Forget flow; no automatic enforcement in MVP | Auto-enforce after N days (rejected: not legal advice, MVP is owner-driven); treating requests as soft-delete signals only (rejected: needs audit) |
| 17 | **No owner edit history** in MVP — edits overwrite original text and rating; no "edited" marker, no snapshot columns | Snapshot columns `original_testimonial` + `original_rating` (rejected for MVP, deferred to §10); full audit log table (rejected: over-engineered for MVP) |
| 18 | Embed endpoint looks the Space up **including** soft-deleted rows; branch is decided in code after the row is found (404 / 200 empty / live scope) | Filter `deleted_at IS NULL` in the lookup (rejected: cannot distinguish 404 from 200 empty) |
| 19 | **`purge` log channel** sets `'days' => 14` **explicitly** in `config/logging.php`. The `stripe` channel returns with v2 webhooks. | Rely on Laravel default (rejected: implicit default is not the contract) |

---

## Differences from the earlier text summaries (the document wins)

1. **Seed counts:** the document shows 6, 3, 100 and 12 testimonials, not "3 per space". All fixture users are on Free.
2. **v1 ships the Free plan only.** No `Pro` enum, no Stripe, no `/billing`, no `STRIPE_PRO_PRICE_ID`, no `subscriptions` table, no `Billable` trait, no webhooks, no `stripe` log channel. Limits live in `config/limits.php` as constants.
3. **Webhook idempotency: not in v1.** v2 introduces a `stripe_events` table for idempotency. The `stripe` log channel returns with v2.
4. **"Approval"** in the visibility rule is `is_wall_of_love`. There is no separate approval flag.
5. **New tables (4):** `spaces`, `testimonials`, `embed_configurations`, `deletion_requests`. The other two (`space_fields`, `testimonial_values`) were considered for v1 and rejected at v2.
6. **Plan limits:** v1 = Free only (3 / 100). Pro (25 / 1,000, $9.99/mo) is v2.
7. **`address`** is a locked required column on `testimonials`.
8. **Consent columns:** `consented_at` and `consent_text_version` are written with `consent_given = 1` and not backfilled.
9. **Dashboard counter label** is "Total customers" in both PRD and data model.
10. **`deletion_requests`** also carries `space_id` (nullable FK to spaces.id, `nullOnDelete`); `space_slug` is kept as typed.
11. **Embed URL** is `{APP_URL}/embed.js` — permanent, served directly by the app with a short cache in v1; v2 may add versioned files or a CDN behind that URL.
12. **Restore** is supported for both testimonials and Spaces (within 30 days for Spaces; testimonials can be restored from Trash even when the Space is at its cap).
13. **All fixture users on Free.** Dev Okafor (Free) has 2 Spaces with 100 + 12 testimonials; the 100-testimonial Space exercises the cap and restore-at-cap code.
14. **No Stripe columns on `users`:** no `stripe_id`, `pm_type`, `pm_last_four`, `trial_ends_at`. v2 adds them when Pro ships.
15. **Hardcoded limits, not `Plan` enum.**

---

## 11. OPEN items

- **Production hosting** — v1 needs neither a card nor a domain; v2 will have a domain and a host. OPEN until v2 selects one. Mirrors PRD §16 / §17.
- **Production email** — v1 uses the log mailer. v2 picks a real email provider and configures SPF/DKIM/DMARC; OPEN until §16 runs. **v2 constraint — exclude any email service that needs a card on file.**
- **Stripe / Pro plan** — v1 is Free-only. v2 installs Cashier, adds the `Billable` trait, exposes `/billing`, registers the Stripe webhook, and adds a `stripe_events` table for idempotency. OPEN until §16 runs.

---

## 12. UNVERIFIED items

- **Cloudflare R2** — likely requires a card (third-party reports; not read on the official page). **Not assumed in v1** because the user has no card. v2 future path only.
- **Resend domain** — production email sender identity. A verified domain is needed to email anyone other than your own Resend account address (checked in Resend's docs). Production email stays OPEN.
- **Oracle Cloud Always Free** — likely requires a card and the "always free" instance can be reclaimed if idle (third-party reports; not read on the official page). **Not assumed in v1** because the user has no card. v2 future path only.
- **GitHub Actions** — GitHub Free personal accounts include 2,000 minutes per month (checked in GitHub docs); public repos are free. Mark as checked.
- **Cloudflare free plan** behavior for `embed.js` and embed JSON — caching only applies if Cloudflare is actually in front of the app. Cloudflare is **not assumed**. **UNVERIFIED**.
- **PHP `intl` extension** — required by some Laravel validation paths and date helpers, and by the Unicode-normalization step on the testimonial body (PRD §6). **UNVERIFIED** on the chosen host.
- **4,000 × 4,000 decode memory** — Intervention Image memory use when decoding very large images before the hard reject. **UNVERIFIED**.
- **Intervention Image** — Laravel package lists PHP 8.1+ and Laravel up to 11 (fits). UNVERIFIED: whether the host's GD build can write WebP (check `gd_info()`).
- **MySQL STORED expression with `deleted_at IS NULL`** — confirm the local MySQL server accepts `deleted_at IS NULL` inside a STORED expression. Test in a local Pest test. This is the only remaining "Gaps not closed" item.
- **Animated GIF re-encode** — Intervention Image's webp re-encode of an animated GIF produces a static first-frame webp. Accepted.
- **Per-Space frame-ancestor policy** — v1 embed has no `frame-ancestors` CSP. Third-party sites can embed the wall anywhere. v2 may add per-Space setting.
- Third-party price/limit values for any service chosen for v2 — verify before launch. Each "free tier" must NOT require a card on file.