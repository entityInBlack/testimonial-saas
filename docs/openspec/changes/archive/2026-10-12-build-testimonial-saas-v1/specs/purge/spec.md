# Spec Delta: purge

## Purpose

Hard-deletes soft-deleted testimonials and their photos after the retention window, converts soft-deleted Spaces into tombstones (keeps the Space row so the slug stays claimed forever), and emits audit logs to a dedicated `purge` log channel.

## ADDED Requirements

### Requirement: Retention window
The system SHALL use `config('purge.retention_days')` (default 30) as the single source of truth for the purge retention window. The `purge:run` command SHALL read this key and nothing else.

#### Scenario: Default retention
- **WHEN** `config('purge.retention_days')` is the default (30)
- **THEN** soft-deleted testimonials and Spaces older than 30 days are eligible for purge

### Requirement: Scheduled purge command
The system SHALL expose `php artisan purge:run` that runs nightly with `withoutOverlapping` (via the Laravel scheduler).

#### Scenario: Cron line
- **WHEN** the host's cron runs `* * * * * cd /path/to/app && php artisan schedule:run`
- **THEN** `purge:run` fires nightly with `withoutOverlapping`

### Requirement: Per-testimonial purge order
The system SHALL run this three-step order for every soft-deleted testimonial older than retention: (1) `UPDATE deletion_requests SET status='acted', acted_at=NOW() WHERE space_id=:space_id AND status='open' AND (testimonial_id=:testimonial_id OR (testimonial_id IS NULL AND email=:testimonial_email))`; (2) delete the photo file from the `public` disk (ignore only "file not found"; any other error keeps the row for the next run); (3) `forceDelete()` the testimonial row.

#### Scenario: Testimonial past retention
- **WHEN** `purge:run` runs on a soft-deleted testimonial past retention
- **THEN** the matching open `deletion_requests` rows are set to `acted`, the photo is deleted, and the testimonial is hard-deleted

#### Scenario: Testimonial with photo missing
- **WHEN** the photo file is already absent
- **THEN** the purge still completes the acted-update and `forceDelete()`

### Requirement: Per-Space purge order
The system SHALL run this order for every Space soft-deleted past retention: (1) for each of its testimonials (live AND soft-deleted), run the per-testimonial three-step order; (2) `delete()` the `embed_configurations` row for that Space (the FK cascade does NOT fire because the Space row is kept); (3) KEEP the `spaces` row as a tombstone so the slug stays claimed forever.

#### Scenario: Space past retention becomes tombstone
- **WHEN** `purge:run` runs on a Space soft-deleted past retention
- **THEN** all its testimonials and their photos are gone, the `embed_configurations` row is gone, the Space row remains with `deleted_at` set, and the slug is claimed forever

#### Scenario: Tombstoned Space not restorable
- **WHEN** a Space has been tombstoned
- **THEN** the Space does not appear in the Deleted Spaces tab and the owner cannot restore it

### Requirement: Purge audit log
The system SHALL write audit entries to a dedicated `purge` log channel. The `purge` channel SHALL be configured in `config/logging.php` with `'days' => 14` **explicitly**. Entries SHALL include row IDs and counts only — no personal data (no `name`, `email`, `address`, or `testimonial` text).

#### Scenario: 14-day retention
- **WHEN** `purge:run` runs
- **THEN** the `purge` log channel retains entries for 14 days (set explicitly in `config/logging.php`)

#### Scenario: No personal data in logs
- **WHEN** `purge:run` writes an entry
- **THEN** the entry contains only row IDs and counts (e.g. `space_id=3, purged_testimonials=100, purged_photos=98`)
