# testimonials Specification

## Purpose

Lets an anonymous visitor submit a testimonial to a Space via a public form, lets the owner manage submissions in an Inbox (favorite, Wall-of-Love, hide, edit, withdraw consent, soft-delete), and lets the owner Restore from Trash or hard-Forget a testimonial with a three-step order that respects deletion requests.

## Requirements

### Requirement: Public submission form
The system SHALL accept a testimonial submission on `/s/{slug}` via a single FormRequest that validates locked and optional fields, trims/strips the body, rejects junk-only bodies, and writes the consent audit trail when consent is given.

#### Scenario: Successful submission
- **WHEN** an anonymous visitor submits valid fields with consent unchecked
- **THEN** the system saves the testimonial with `consent_given=0`, sets `is_public=0` (via the STORED generated column), and shows a rotating funny confirmation message

#### Scenario: Submission with consent
- **WHEN** consent is checked
- **THEN** the system also writes `consented_at=now()` and `consent_text_version=config('consent.current')`

#### Scenario: Honeypot triggered
- **WHEN** the honeypot field is non-empty
- **THEN** the system silently rejects the submission (no row created, no cap counted)

#### Scenario: Duplicate within 5 minutes
- **WHEN** the same email and same testimonial text are submitted to the same Space within 5 minutes
- **THEN** the system returns the thank-you page, does not save the row, and does not count toward the cap

#### Scenario: Cap reached
- **WHEN** the Space is at `config('limits.max_testimonials_per_space')` (default 100) live testimonials
- **THEN** the form shows "This Space is not accepting responses right now." and no row is saved

### Requirement: Photo upload pipeline
The system SHALL accept a profile photo when `field_config.profile_photo.enabled=true`, validate MIME/size/dimensions, re-encode to webp ~400 px on the `public` disk with a random flat filename, and roll back the file if the DB insert fails. Error messages SHALL NOT reveal file paths.

#### Scenario: Valid photo
- **WHEN** a valid photo is uploaded
- **THEN** the system stores a re-encoded webp at `photos/{random}.webp` and `photo_url=Storage::disk('public')->url($path)` returns 200 from the local server

#### Scenario: Oversized image
- **WHEN** an image's `getimagesize()` reports > 4000 px on either side
- **THEN** the system rejects the upload with a generic error

#### Scenario: DB insert fails
- **WHEN** the photo was saved to disk but the testimonial row insert fails
- **THEN** the system deletes the photo file from the `public` disk

### Requirement: Public submission rate limits
The system SHALL rate-limit public submissions to 5 submissions per hour per IP per Space, AND 5 submissions per hour per email per Space, in addition to the 5-minute dedupe rule.

#### Scenario: IP rate limit
- **WHEN** the same IP submits 6 times within an hour to the same Space
- **THEN** the 6th submission is rejected

#### Scenario: Email rate limit
- **WHEN** the same email submits 6 times within an hour to the same Space
- **THEN** the 6th submission is rejected

### Requirement: is_public STORED generated column
The system SHALL expose `is_public` on `testimonials` as a MySQL STORED generated column defined as `CASE WHEN consent_given=1 AND is_wall_of_love=1 AND is_hidden=0 AND deleted_at IS NULL THEN 1 ELSE 0 END`. The Eloquent scope `scopePubliclyVisible()` SHALL resolve to `where('is_public', true)`.

#### Scenario: Five-row truth table
- **WHEN** five rows are inserted: Priya (consent=1, WoL=1, hidden=0, alive) → 1; Tom (consent=1, WoL=0) → 0; Sara (consent=0) → 0; Ben (consent=1, WoL=1, hidden=1) → 0; Ana (soft-deleted) → 0
- **THEN** each row's `is_public` value read from the database matches the truth table (verified by `DB::table('testimonials')->value('is_public')`, not by the in-memory Eloquent copy)

#### Scenario: Recompute on update
- **WHEN** an owner withdraws consent on Priya's row
- **THEN** `is_public` recomputes to 0 automatically (no accessor, no observer)

### Requirement: Inbox actions
The system SHALL let the authenticated owner perform Favorite, Wall-of-Love, Hide/Unhide, Edit, Withdraw consent, and soft-Delete on a testimonial in `/inbox`, with the Wall-of-Love toggle blocked while `consent_given=0` (including bulk) and Withdraw consent setting `consent_given=0` AND `is_wall_of_love=0` while keeping the consent audit fields.

#### Scenario: Wall-of-Love blocked without consent
- **WHEN** the owner toggles Wall-of-Love on a row where `consent_given=0`
- **THEN** the system blocks the action (including bulk actions)

#### Scenario: Withdraw consent
- **WHEN** the owner invokes the withdraw-consent action on a consented row
- **THEN** `consent_given=0`, `is_wall_of_love=0`, `consented_at` and `consent_text_version` are unchanged, and `is_public` recomputes to 0

#### Scenario: Inbox sort
- **WHEN** the owner views the inbox
- **THEN** the system sorts by `is_favorite DESC, submitted_at DESC` (firmware rule shared with the embed)

### Requirement: Trash Restore
The system SHALL let the owner Restore a soft-deleted testimonial from Trash. Restore SHALL clear `deleted_at`. The `is_public` STORED column SHALL recompute automatically. Flags (`is_favorite`, `is_wall_of_love`, `is_hidden`) SHALL come back as they were.

#### Scenario: Restore at cap
- **WHEN** the owner restores a soft-deleted testimonial while the Space is at the testimonial cap
- **THEN** the system allows the restore (the cap only blocks new public submissions, not the return of existing ones)

### Requirement: Forget now (three-step order)
The system SHALL expose a **Forget now** action in Trash only, running a fixed three-step order: (1) close matching `deletion_requests` to `acted`/`acted_at=now`; (2) delete the photo file from the `public` disk, ignoring only "file not found"; (3) `forceDelete()` the testimonial row.

#### Scenario: Forget with matching open deletion request
- **WHEN** the owner presses Forget on a testimonial with a matching open `deletion_requests` row
- **THEN** the request is set to `status='acted'`, `acted_at=now`, the photo is deleted, the testimonial is hard-deleted, and the `testimonial_id` FK on the request is set to `null` (via `nullOnDelete`)

#### Scenario: Forget with photo missing
- **WHEN** the owner presses Forget and the photo file is already absent
- **THEN** the system still completes the acted-update and `forceDelete()`

### Requirement: Cross-user authorization
The system SHALL deny user B from reading, editing, deleting, forgetting, withdrawing consent, or embed-building user A's data. Every Inbox action and embed-builder page MUST authorize `$space->user_id === $user->id` (single-hop).

#### Scenario: User B attempts to act on user A's testimonial
- **WHEN** user B submits a Livewire action for user A's testimonial
- **THEN** the system rejects the action
