# embed Specification

## Purpose

Lets the owner configure a Space's embed (layout, dark mode, animation, background color, show rating, max testimonials) and exposes a public, dependency-free embed snippet addressed by the Space's immutable `public_id` that renders approved testimonials on third-party sites.

## Requirements

### Requirement: Embed builder
The system SHALL expose `/spaces/{space}/embed` (auth required, owner only) with these options stored on `embed_configurations`: `layout` (`masonry` | `carousel`), `dark_mode` (boolean), `animation_enabled` (boolean), `background_color` (hex `#RRGGBB`, nullable), `show_rating` (boolean, default `true`), `item_limit` (smallint unsigned, default 12, capped at 50). There is NO "Source" option. There is NO "Show avatar/photo" toggle (photos render iff `profile_photo IS NOT NULL`).

#### Scenario: Live preview
- **WHEN** the owner changes any option
- **THEN** the live preview on the right updates instantly using real rows read through `scopePubliclyVisible()`

#### Scenario: item_limit clamp
- **WHEN** the owner types an `item_limit` greater than 50
- **THEN** the system clamps it to 50

### Requirement: Embed snippet
The system SHALL output a single copyable snippet that addresses the Space by its immutable `public_id`:

```html
<div data-testimonial-space="{public_id}"></div>
<script src="{APP_URL}/embed.js" async></script>
```

#### Scenario: Slug rename does not break embed
- **WHEN** the owner renames the Space's slug
- **THEN** existing embeds continue to render (the snippet uses `public_id`, not slug)

#### Scenario: One embed per Space
- **WHEN** the snippet is pasted on an external site
- **THEN** the embed renders only that one Space's approved testimonials

### Requirement: embed.js rendering rules
The system SHALL serve a dependency-free `embed.js` at the permanent URL `{APP_URL}/embed.js`. The script SHALL render content inside an isolated container (Shadow DOM or iframe), fetch `GET /api/spaces/{public_id}/testimonials`, and render ALL text using `element.textContent` (NEVER `innerHTML`).

#### Scenario: XSS neutralization
- **WHEN** a testimonial contains `<script>alert(1)</script>`
- **THEN** `embed.js` renders the script as text, not as HTML

#### Scenario: Photo rendering
- **WHEN** a testimonial has `profile_photo IS NOT NULL`
- **THEN** `embed.js` renders the photo (no "show photo" toggle; display is `profile_photo IS NOT NULL`)

### Requirement: Public embed API endpoint
The system SHALL expose `GET /api/spaces/{public_id}/testimonials` registered in `routes/web.php` **outside** the `web` middleware group (no session, no cookie, no CSRF), throttled at 120/min per IP. The Space lookup SHALL include soft-deleted rows; the response branch is decided in code after the row is found.

#### Scenario: Unknown public_id
- **WHEN** the `public_id` does not match any Space
- **THEN** the system returns 404

#### Scenario: Soft-deleted Space
- **WHEN** the Space is soft-deleted
- **THEN** the system returns 200 with body `{"config": null, "testimonials": []}`

#### Scenario: Live Space
- **WHEN** the Space is live
- **THEN** the system returns 200 with body `{"config": {…embed_config row or code defaults…}, "testimonials": […rows from scopePubliclyVisible()…]}`

### Requirement: Embed API server-side clamps and ordering
The system SHALL clamp `item_limit` (or the `?limit=` query param) to `min((int) item_limit, 50)`. The system SHALL sort by `is_favorite DESC, submitted_at DESC` (same rule as the Inbox).

#### Scenario: ?limit=200 clamp
- **WHEN** the request includes `?limit=200`
- **THEN** the response contains at most 50 testimonials and the JSON array length matches the clamped limit

#### Scenario: Same submitted_at ordering
- **WHEN** two testimonials have the same `submitted_at` but different `is_favorite` values
- **THEN** the favorite appears first in the JSON

### Requirement: Embed API field whitelist
The system SHALL pass embed testimonials through an Eloquent API Resource that whitelists only: `name`, `testimonial`, `rating` (only when `show_rating=true`), `submitted_at`, `company_name`, `social_url`, `photo_url` (full URL built from `profile_photo`). The resource MUST NEVER project `email`, `address`, or any internal id.

#### Scenario: show_rating=false
- **WHEN** `embed_configurations.show_rating=0`
- **THEN** every item in the response omits the `rating` field server-side

#### Scenario: No email/address leakage
- **WHEN** the embed response is rendered
- **THEN** the response body contains no `email`, no `address`, and no internal `id` fields

### Requirement: Embed API caching and CORS
The system SHALL set `Cache-Control: max-age=60` and SHALL set `Access-Control-Allow-Origin: *` (Laravel 11's default `config/cors.php` already covers `api/*` paths). The response SHALL have no `Set-Cookie` header (route is outside the `web` middleware group).

#### Scenario: Origin header returns wildcard
- **WHEN** the request includes an `Origin` header
- **THEN** the response carries `Access-Control-Allow-Origin: *`

#### Scenario: Cache headers and no Set-Cookie
- **WHEN** the embed JSON is fetched
- **THEN** the response has `Cache-Control: max-age=60` and no `Set-Cookie` header
