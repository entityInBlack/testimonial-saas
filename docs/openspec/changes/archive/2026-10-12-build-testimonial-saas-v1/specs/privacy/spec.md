# Spec Delta: privacy

## Purpose

Lets a respondent read what is collected and request deletion of their testimonial, and links the public site to the versioned consent wording.

## ADDED Requirements

### Requirement: Privacy page
The system SHALL expose `/privacy` as a static page that states: what is collected, why it is collected, how long it is kept, how to request removal, and links to the current AND previous consent wording from `config('consent.php')`.

#### Scenario: Visit /privacy
- **WHEN** a visitor opens `/privacy`
- **THEN** the system renders the static page with sections for "What we collect", "Why", "How long", "How to request removal", and a "Consent wording" section listing the current version and any previous versions

### Requirement: Deletion request form
The system SHALL expose `/privacy/request-deletion` with a form that accepts `email` (required, max 180), `space_slug` (required, max 60), optional `testimonial_id`, and a honeypot field. The form SHALL be throttled at 5 requests/hour per IP. On submit, the system SHALL write a `deletion_requests` row.

#### Scenario: Space resolved server-side
- **WHEN** the typed `space_slug` matches a Space (looked up via `Space::withTrashed()`)
- **THEN** the row's `space_id` is set to that Space's id; `testimonial_id` is set only if it belongs to the resolved Space

#### Scenario: Space not found
- **WHEN** the typed `space_slug` does not match any Space
- **THEN** the row is written with `space_id=null` and `space_slug` retained as typed (the form never reveals whether an ID exists)

#### Scenario: Honeypot triggered
- **WHEN** the honeypot field is non-empty
- **THEN** the system silently rejects the submission (no row created)

#### Scenario: Same thank-you for every outcome
- **WHEN** the form is submitted (valid, invalid slug, honeypot triggered, rate limited)
- **THEN** the system shows the same thank-you page; the form never reveals whether an ID exists

### Requirement: Forget updates matching deletion_requests
The system SHALL set the `deletion_requests` row to `status='acted'` with `acted_at=now` when the owner presses Forget on the matching testimonial, BEFORE the testimonial is `forceDelete()`d (see the `testimonials` capability for the full three-step order).

#### Scenario: Forget closes matching request
- **WHEN** the owner presses Forget on Sara's testimonial
- **THEN** the seeded open `deletion_requests` row (with `testimonial_id=null` and matching email) is set to `status='acted'`, `acted_at=now`
