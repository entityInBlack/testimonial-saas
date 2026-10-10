# Spec Delta: seeders

## Purpose

Provides deterministic demo fixtures so a fresh `php artisan migrate --seed && php artisan serve` produces a working v1 demo where the Free-plan caps, the `is_public` STORED column, the embed endpoint, the dashboard counters, and the deletion-request flow are all exercised.

## ADDED Requirements

### Requirement: Demo users
The system SHALL seed four users on the Free plan: Maya Sharma (`maya@brightcopy.co`), Dev Okafor (`dev@shiplog.io`), Priya Raman (`priya@nimbus.dev`), and Ben Fischer. Priya and Ben are respondents (testimonial rows); Maya and Dev are owners (Space rows).

#### Scenario: Fresh migrate --seed
- **WHEN** the owner runs `php artisan migrate --seed` on an empty database
- **THEN** the system creates Maya, Dev, Priya, and Ben users (Maya and Dev own Spaces; Priya and Ben are testimonial rows)

### Requirement: Maya's Spaces
The system SHALL seed Maya with two live Spaces:
- `brightcopy-wins` with 6 live testimonials.
- `course-launch` with 3 live testimonials.

#### Scenario: Maya's spaces render
- **WHEN** Maya logs in and visits `/dashboard`
- **THEN** the system shows 2 live Spaces (both `brightcopy-wins` and `course-launch`)

### Requirement: Dev's Spaces
The system SHALL seed Dev with two live Spaces (`shiplog-reviews` at the testimonial cap and `beta-testers` with Priya sharing an email across both) so the dashboard, embed, and `is_public` STORED column can be exercised end-to-end.

#### Scenario: Dev's shiplog-reviews at cap
- **WHEN** Dev logs in and visits `/dashboard`
- **THEN** `shiplog-reviews` shows 100 live testimonials (Ana's soft-deleted row keeps the live count at 100, not 101)

#### Scenario: Total customers = 111
- **WHEN** the seed has run
- **THEN** Dev's "Total customers" counter = 111 (100 live in `shiplog-reviews` + 12 live in `beta-testers` − 1 for Priya counted once across both Spaces; Ana's soft-deleted row is not counted)

#### Scenario: shiplog-reviews embed JSON
- **WHEN** a third party fetches `GET /api/spaces/{shiplog-reviews.public_id}/testimonials`
- **THEN** the JSON includes Priya (WoL) + 20 generated WoL rows = 21 items, ordered `is_favorite DESC, submitted_at DESC`, EXCLUDING Ben (`is_hidden`), Tom (no WoL), Sara (no consent), and Ana (soft-deleted)

#### Scenario: beta-testers embed JSON
- **WHEN** a third party fetches `GET /api/spaces/{beta-testers.public_id}/testimonials`
- **THEN** the JSON includes Priya + 11 generated rows

### Requirement: Open deletion request fixture
The system SHALL seed ONE open `deletion_requests` row on `shiplog-reviews` for Sara (`email=sara@example.test`, `testimonial_id=null` on purpose to exercise the email-match path, `status='open'`).

#### Scenario: Dashboard shows open request
- **WHEN** Dev logs in and visits `/dashboard`
- **THEN** the open deletion requests list shows Sara's row

#### Scenario: Forget-now closes the request
- **WHEN** Dev presses Forget-now on Sara's testimonial
- **THEN** the request is set to `status='acted'` with `acted_at` set

### Requirement: Demo page and README
The system SHALL include a plain HTML demo page at `public/demo-embed.html` that pastes the embed snippet for any Space, and a README that documents local MySQL setup, log mailer, scheduler cron, `purge:run`, backup + restore rehearsal, all third-party limits marked UNVERIFIED, the `CLOUDFLARE_HEADERS_ENABLED` warning, and a "Revisit before launch" list.

#### Scenario: Demo page renders embed
- **WHEN** the owner pastes the embed snippet for `shiplog-reviews` into `public/demo-embed.html` and serves it
- **THEN** the page renders Priya + 20 WoL rows = 21 items

#### Scenario: Public landing
- **WHEN** a visitor opens `/` (root)
- **THEN** the system renders a landing page with hero, Free plan info, features, and a CTA to register
