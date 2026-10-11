# dashboard Specification

## Purpose

Lets the authenticated owner see counters, an open deletion-requests list, a Deleted Spaces tab, a time-series graph of submissions, and a non-actionable Free-plan info note, computed against live Spaces only.

## Requirements

### Requirement: Dashboard counters
The system SHALL display these counters on `/dashboard` (auth required), all computed against the owner's **live** Spaces only (soft-deleted Spaces excluded; soft-deleted testimonials inside a live Space excluded): Total testimonials, **Total customers** (label exactly "Total customers"; `COUNT(DISTINCT email)`), Spaces used (X of `config('limits.max_spaces')`), Average rating (non-null ratings only), Open deletion requests count.

#### Scenario: Total customers formula
- **WHEN** the owner has 2 live Spaces (A: 3 testimonials, B: 2 testimonials, where 1 email appears in both A and B)
- **THEN** Total customers = 4 (the shared email counts once)

#### Scenario: Dev's Total customers = 111
- **WHEN** Dev's `shiplog-reviews` has 100 live testimonials and `beta-testers` has 12 live, and Priya's email appears in both
- **THEN** Total customers = 111 (Ana's soft-deleted row is not counted; Priya is counted once)

#### Scenario: Soft-deleted Spaces excluded
- **WHEN** a soft-deleted Space has testimonials
- **THEN** those testimonials are not counted in any counter

### Requirement: Open deletion-requests list
The system SHALL show a list of `deletion_requests` rows with `status='open'` whose `space_id` matches one of the owner's **live** Spaces.

#### Scenario: Live Space match
- **WHEN** an open `deletion_requests` row references one of the owner's live Spaces
- **THEN** the row appears in the list

#### Scenario: Soft-deleted Space match
- **WHEN** an open `deletion_requests` row references one of the owner's soft-deleted Spaces
- **THEN** the row does not appear in the list until the Space is restored (README notes this)

### Requirement: Deleted Spaces tab on dashboard
The system SHALL display a Deleted Spaces tab on `/dashboard` listing Spaces deleted less than 30 days ago with a Restore action (same widget as the Space management page). Tombstoned Spaces (> 30 days) SHALL NOT be listed.

#### Scenario: Restore available within 30 days
- **WHEN** the owner opens the Deleted Spaces tab
- **THEN** soft-deleted Spaces < 30 days appear with Restore

#### Scenario: Restore blocked at cap
- **WHEN** the owner presses Restore but is already at the Space limit
- **THEN** the system shows a "delete a Space first" notice

#### Scenario: Tombstoned Space not listed
- **WHEN** a Space was soft-deleted more than 30 days ago and the purge has run
- **THEN** the Space does not appear in the tab

### Requirement: Time-series graph
The system SHALL display a Chart.js (CDN) time-series graph of testimonial submissions on `/dashboard` with granularity `day | week | month` and range `7d | 30d | 90d | all time`, with an optional space filter. The system SHALL bucket by UTC day at the database level and SHALL fill in zero-count days in application code from the requested range. Display SHALL use the owner's locale.

#### Scenario: Day granularity 7d range
- **WHEN** the owner selects day granularity and 7d range
- **THEN** the X axis shows the last 7 days with one bar per day (including zero-count days filled in by the application)

#### Scenario: Space filter
- **WHEN** the owner filters by a specific Space
- **THEN** the graph shows only that Space's submissions

#### Scenario: UTC bucketing
- **WHEN** testimonials span multiple UTC days
- **THEN** each bar reports the count of submissions whose `DATE(submitted_at)` equals that UTC day

### Requirement: Free-plan info note
The system SHALL display a non-actionable Free-plan info note in the dashboard header (above the counter cards, below the page title) with the exact copy:

> You're on the Free plan (3 Spaces, 100 testimonials per Space).

The note SHALL NOT link anywhere, SHALL NOT have a button or CTA, and SHALL render only when the authenticated user owns at least one live Space.

#### Scenario: Note renders
- **WHEN** the authenticated user owns at least one live Space
- **THEN** the note shows the exact copy above

#### Scenario: Note has no CTA
- **WHEN** the note is rendered
- **THEN** there is no link, no button, and no upgrade CTA in the note (no `/billing` route exists in v1)

### Requirement: Empty states
The system SHALL show an empty-state message on the dashboard when the owner has no live Spaces or no live testimonials.

#### Scenario: No live Spaces
- **WHEN** the authenticated owner has zero live Spaces
- **THEN** the system shows "Create your first Space" with a link to the create form

#### Scenario: No live testimonials
- **WHEN** the authenticated owner has live Spaces but no live testimonials
- **THEN** the system shows "Share your link to start collecting"
