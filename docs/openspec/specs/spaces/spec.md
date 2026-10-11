# spaces Specification

## Purpose

Lets an authenticated owner create, edit, and soft-delete Spaces, each with a globally-unique shareable slug for the public collection URL and an immutable `public_id` for the embed snippet.

## Requirements

### Requirement: Owner can create a Space
The system SHALL let an authenticated owner create a Space with `name` (max 120, default = `title`), `slug` (max 60, auto-suggested from title, kebab-case, editable), `title`, `subtitle`, `ask`, `theme` (`minimal_light` | `minimal_dark` | `soft_color`), `rating_enabled` (boolean), and `field_config` JSON for the three fixed optional fields (`company_name`, `social_url`, `profile_photo`).

#### Scenario: Successful create
- **WHEN** owner submits the create form with valid fields
- **THEN** the system creates the Space, generates a 12-character `public_id`, sets `field_config` defaults, and redirects to a success page showing the public URL `/s/{slug}` with **Copy Link** and **Go to Dashboard** actions

#### Scenario: Slug collision
- **WHEN** the typed slug matches an existing slug (live, soft-deleted, or tombstoned) or matches the reserved list (`s`, `api`, `admin`, `login`, `register`, `dashboard`, `billing`, `embed`, `assets`, `up`)
- **THEN** the system surfaces a "slug already taken" notice and offers a suffixed suggestion (`-2`, `-3`)

### Requirement: Slug collision probe
The system SHALL expose `GET /spaces/check-slug?slug=X` (auth required, registered in `routes/web.php`) that returns `{available: bool, suggested: string|null}`.

#### Scenario: Free slug
- **WHEN** the typed slug is unused and not in the reserved list
- **THEN** the response is `{available: true, suggested: null}`

#### Scenario: Taken slug with suggestion
- **WHEN** the typed slug is taken
- **THEN** the response is `{available: false, suggested: "<slug>-2"}` (or the next free `-N` suffix, trimmed to 60 chars)

#### Scenario: Soft-deleted or tombstoned slug
- **WHEN** the typed slug is held by a soft-deleted or tombstoned Space
- **THEN** the response reports `available: false` (the probe uses `Space::withTrashed()`)

### Requirement: Free plan space cap
The system SHALL enforce a maximum of `config('limits.max_spaces')` (default `3`) live Spaces per owner on creation.

#### Scenario: At cap
- **WHEN** an authenticated owner already has 3 live Spaces and attempts to create another
- **THEN** the system blocks the create and shows a "Delete a Space or contact support" notice (no upgrade CTA in v1)

#### Scenario: Warning at 80%
- **WHEN** an authenticated owner has 3 or more live Spaces (≥ 80% of cap = 3)
- **THEN** the system shows a warning banner

#### Scenario: Soft-deleted Spaces excluded
- **WHEN** counting live Spaces toward the cap
- **THEN** soft-deleted Spaces are not counted

### Requirement: Soft delete and 30-day Restore
The system SHALL soft-delete a Space by setting `deleted_at`. The Space SHALL appear in the **Deleted Spaces** tab for 30 days. The owner MAY press **Restore** to clear `deleted_at`.

#### Scenario: Restore within 30 days
- **WHEN** the owner presses Restore on a Space deleted less than 30 days ago
- **THEN** the system clears `deleted_at` and the Space becomes live again

#### Scenario: Restore blocked at cap
- **WHEN** the owner presses Restore but is already at the Free Space limit
- **THEN** the system shows a "delete a Space first" notice and does not restore

#### Scenario: Tombstoned Space not in tab
- **WHEN** a Space has been soft-deleted more than 30 days and the purge job has run
- **THEN** the Space is not listed in the Deleted Spaces tab and cannot be restored

#### Scenario: Slug stays claimed forever
- **WHEN** a Space is soft-deleted (and after the 30-day purge)
- **THEN** its slug remains claimed by the tombstoned Space row, and no other owner can create a Space with the same slug

### Requirement: Public submission route by slug
The system SHALL route public submissions at `/s/{slug}` (no login required). Unknown or soft-deleted slugs SHALL return 404.

#### Scenario: Live slug
- **WHEN** an anonymous visitor opens `/s/{live-slug}`
- **THEN** the system renders the Space's theme, title, subtitle, and ask, plus the three locked fields and any enabled optional fields

#### Scenario: Unknown or soft-deleted slug
- **WHEN** an anonymous visitor opens `/s/{unknown-or-soft-deleted-slug}`
- **THEN** the system returns 404
