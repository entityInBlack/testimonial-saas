# Decision Log

This file tracks all major decisions taken during our PRD/data-model finalization sessions for the **Minimal Testimonial Collection SaaS** project (school Agentic Engineering course assignment).

Each decision is logged with:
- **Decision** — what was decided
- **Why** — rationale
- **Impact** — affected docs / sections

---

## Old Decisions

_This section is intentionally empty. The file is being created for the first time. Future "old decisions" (superseded or revised) will be archived here when a new decision replaces them._

---

## New Decisions

### D1. Solo developer, zero-cost constraint (no card, no money)

- **Decision:** v1 must be buildable by a solo developer who has **no debit/credit card** and no budget. Every tool, library, and runtime must be on a genuinely free tier (no paid trials that require a card).
- **Why:** Real user constraint (school assignment, solo dev). All previous "free trial then paid" assumptions are invalid.
- **Affects:** Rerise (ADDENDUM A1), PRD §13 Tech Stack, PRD §15 Build Order, data model §11 OPEN items.

### D2. Free plan only in v1 (no Pro plan, no billing route)

- **Decision:** v1 ships the **Free plan only**. The Pro plan, `/billing` route, plan upgrade/downgrade flow, Stripe, and Cashier are **removed from v1 entirely** — not deferred, not stubbed.
- **Why:** Two reasons compound — (a) cannot wire up billing without a payment processor and a card, (b) v1 must be a complete working version; advanced monetization is a v2 concern.
- **Affects:** PRD §3.1 (users table — removed `stripe_id`, `pm_type`, `pm_last_four`, `trial_ends_at`), PRD §3.6 (Cashier tables — "Not in v1"), PRD §3.7 (limits rewritten with hardcoded `config/limits.php`), PRD §4 (Key Technical Decisions 4–5 rewritten), PRD §11 (Free plan only), PRD §12 Billing ("Not in v1"), PRD §13 (removed Cashier line), PRD §14 (Acceptance Criteria Billing — "Not in v1"), PRD §15 (Stripe step deleted), data model §3.1, §3.5, §5.1, §5.2, §6, §7.2, §7.3.

### D3. Hardcoded limits via `config/limits.php` (no `Plan` enum in v1)

- **Decision:** Limits are hardcoded constants in `config/limits.php` (e.g. `MAX_SPACES_PER_USER = 3`, `MAX_TESTIMONIALS_PER_SPACE = 100`). No `Plan` enum, no per-user plan column, no `subscriptions` table in v1.
- **Why:** No billing = no need for a `Plan` enum. Hardcoding keeps the v1 codebase simple and matches the "Free plan only" decision.
- **Affects:** PRD §3.7, §11, data model §5.1 (replaced Plans table with `config/limits.php` constants).

### D4. Remove Stripe, Cashier, and webhooks from v1

- **Decision:** No Stripe SDK, no Laravel Cashier, no Stripe webhook endpoint, no webhook idempotency table, no local webhook log table in v1.
- **Why:** Stripe requires real account setup with a card (violates D1) and is a paid product feature (deferred to v2 per D2).
- **Affects:** PRD §12 (Billing — "Not in v1"), data model §3.5 (Cashier tables — "Not in v1"), §6 (Security table — removed Stripe webhook row), §7.2 (Webhook idempotency — "Not in v1"), §7.3 (Local webhook log — "Not in v1").

### D5. Soft-delete tombstone pattern for Spaces (slug stays claimed)

- **Decision:** When a Space is soft-deleted, the **row stays** (tombstone) and the slug remains claimed. Soft-deleted Spaces:
  - Excluded from owner listings via `WHERE deleted_at IS NULL`
  - Still served by the **embed route** for 30 days (with a "this Space is pending deletion" banner)
  - Re-occupying the same slug requires waiting for purge or picking a new slug
- **Why:** Embed links on external sites keep working during the retention window; prevents slug squatting reuse; the brief mandates the 30-day purge retention.
- **Affects:** PRD §6 (Space lifecycle), data model §3.2 (spaces columns), §4 (relationships), §7.1 (purge job).

### D6. `is_public` as a MySQL STORED generated column

- **Decision:** `testimonials.is_public` is a MySQL `GENERATED ... STORED` boolean computed from the visibility rule (approved + not flagged + within display window). Application code can read but not write it.
- **Why:** Single source of truth for the public visibility rule; cheap to index; no risk of app code drifting from the rule.
- **Affects:** data model §3.3 (testimonials columns), PRD §7 (Display rules).

### D7. `deletion_requests` sidecar table (nullOnDelete)

- **Decision:** Deletion requests live in a dedicated `deletion_requests` table with both `space_id` (nullable, `nullOnDelete`) and `testimonial_id` (nullable, `nullOnDelete`). Either entity can be deleted while preserving the audit trail row.
- **Why:** Audit/compliance record must survive even if the target row is purged; clean separation from business tables.
- **Affects:** data model §3.4 (deletion_requests columns), §4 (relationships), PRD §8.

### D8. Embed lookup omits `AND deleted_at IS NULL`

- **Decision:** The embed route's testimonial lookup **does not** include `AND deleted_at IS NULL`. Branches in code: (a) 404 if no testimonial found, (b) 200 with empty array if Space is soft-deleted (banner shown), (c) 200 with live data otherwise.
- **Why:** Embed links on external sites must keep working during the 30-day retention window even when the Space is soft-deleted; only the **purge** (hard delete after 30 days) should cause 404.
- **Affects:** data model §3 (embed lookup branch-in-code), PRD §6 (Space lifecycle).

### D9. Restore asymmetry — testimonial at cap OK, Space at cap blocked

- **Decision:** If a Space's testimonial count is at the cap and a testimonial is soft-deleted, the cap does **not** free up for new submissions. If a user's Space count is at the cap and a Space is soft-deleted, the freed slot **does** allow creating a new Space (after the 30-day retention via "delete a real Space").
- **Why:** Testimonials come from external submitters who can't be expected to retry on someone else's deletion. Space slots are owner-controlled and removing a Space should genuinely free a slot.
- **Affects:** PRD §11.1 (downgrade — "Not in v1", reserved), data model §5.3.

### D10. 30-day soft-delete retention for Spaces and Testimonials

- **Decision:** Both Spaces and Testimonials use a 30-day soft-delete retention. After 30 days, a scheduled job hard-deletes the row (and dependent rows).
- **Why:** Brief mandates 30 days; matches D5 and D8.
- **Affects:** PRD §6, data model §3.2, §3.3, §7.1.

### D11. Consent versioning via `consented_at` + `consent_text_version`

- **Decision:** Every Testimonial stores `consented_at` (timestamp) and `consent_text_version` (string, e.g. `"v1-2026-01"`). The version is looked up from `config/consent.php` at submit time, not stored in the DB itself.
- **Why:** Audit trail must show **which** consent text was shown to the submitter at the moment of consent. Version source is config because consent text rarely changes; storing the text itself in each row would bloat the table.
- **Affects:** data model §3.3 (testimonials columns), PRD §7.

### D12. Photo pipeline — 5MB cap, getimagesize() check, webp re-encode, flat random path

- **Decision:** Photo uploads are capped at 5MB, validated as real images via `getimagesize()` (not just MIME), re-encoded to WebP at ~400px on the longest edge, and stored under a flat random path (no user-controlled subdirs).
- **Why:** 5MB cap = reasonable upper limit; `getimagesize()` = catch fake-image polyglots; WebP at 400px = fast page loads, low storage; flat random path = no path-traversal or enumeration surface.
- **Affects:** data model §3.3 (photo columns), PRD §7.

### D13. Honeypot + 5/hr/space IP rate limit + 5/hr/email/space + 5-min dedupe

- **Decision:** Submit form protected by (a) honeypot field, (b) IP rate limit 5/hr/space, (c) email rate limit 5/hr/email/space, (d) 5-min dedupe window per (space_id, email hash).
- **Why:** Cheap abuse prevention without external services; matches the brief.
- **Affects:** PRD §9 (security), data model §6.

### D14. API Resource whitelist for embed JSON

- **Decision:** Embed JSON endpoint returns only: `name`, `testimonial`, `rating`, `submitted_at`, `company_name`, `social_url`, `photo_url`. **No email, no consent_text_version, no internal IDs.**
- **Why:** Embed JSON is public; minimal PII exposure.
- **Affects:** data model §7.4, PRD §7.

### D15. Log mailer in v1 (no real email provider)

- **Decision:** v1 uses Laravel's `log` mailer for all transactional email. No SMTP, no Mailgun, no Postmark, no Resend.
- **Why:** Real email requires DNS/domain setup that violates D1's no-card/no-budget constraint. `log` mailer lets us verify the email code works end-to-end without external dependencies.
- **Affects:** PRD §13 Tech Stack, data model §10 (Future migration paths — "Real email provider").

### D16. Local disk storage in v1 (no S3)

- **Decision:** v1 uses Laravel's `local` disk for photo and asset storage. No S3, no Spaces, no CDN.
- **Why:** Same constraint as D15 — object storage requires account + card; v1 ships on local disk in `storage/app/public`.
- **Affects:** PRD §13, data model §10.

### D17. All fixture users on the Free plan

- **Decision:** Demo seeders create only Free-plan users. There is no fixture user on Pro because Pro doesn't exist in v1.
- **Why:** Consistent with D2.
- **Affects:** Rerise ADDENDUM A5, data model §1 reference scenario.

### D18. v2 future paths documented but not implemented

- **Decision:** Stripe integration, Pro plan, `/billing` route, real email provider, and S3/CDN are listed as **v2 future paths** in PRD §16 and data model §10. They are documented, not implemented in v1.
- **Why:** Keeps v1 scope tight while giving a clear migration path.
- **Affects:** PRD §16, data model §10.

### D19. PRD and data model must match section-by-section

- **Decision:** Every change to the PRD must be reflected in the data model in the same pass, and vice versa. The two documents are kept under the same version number (currently v2.3) and a cross-doc consistency scan is performed after every change set.
- **Why:** Avoids the "doc drift" failure mode where PRD says one thing and data model says another; explicitly requested by the user.
- **Affects:** Both files — version headers, §6 OPEN items, §7 UNVERIFIED items (kept in sync).

### D20. Resolve mismatches between PRD and data model before finalizing

- **Decision:** When the PRD and data model disagree on any concrete detail (column name, enum value, route, limit, retention window, etc.), resolve by editing both files to agree, with a note in §8 Divergences if a divergence is intentional.
- **Why:** User instruction; keeps the two docs as a single source of truth.
- **Affects:** data model §8 Divergences from PRD (v2.3), PRD §17 OPEN items.

### D21. PRD and data model both at v2.3 — Free plan only, reconciled

- **Decision:** Promote both the PRD and the data model to **version 2.3** with a matching header note: "v1 ships Free plan only; Pro plan, Stripe, Cashier, webhooks removed." The data model's §8 Divergences table grows entries 8.12 and 8.13 to record the v2.3 alignment.
- **Why:** User instruction — "make sure PRD and data model match as much as possible" + "if anything doesn't match, resolve them." Single version number prevents "PRD v2.2 + data v2.3" drift.
- **Affects:** PRD header line 6; data model header line 7; data model §8 (entries 8.12, 8.13); both §17 / §11 OPEN items.

### D22. Owner role no longer "manages billing"; no upgrade banner in v1

- **Decision:** Remove two stale references in the PRD that assumed a billing flow exists: (a) §2 User Roles table — change Owner description from "manages billing" to "(v1 ships Free plan only — no billing to manage)"; (b) §9 Dashboard — replace the "Free-plan upgrade nudge: persistent banner linking to `/billing`" with a non-actionable informational line about being on the Free plan with hardcoded limits.
- **Why:** `/billing` does not exist in v1; an upgrade nudge pointing to a route that does not exist is broken on first load. Both phrases survived from the v2.2 PRD and were not caught when v2.3 was patched.
- **Affects:** PRD §2, §9, §14 acceptance criteria (the matching "no upgrade CTA in v1" line in §14 was already correct).

### D23. Data model §2 ERD drops the `users → subscriptions` row

- **Decision:** Remove the `users → subscriptions` (Cashier) row from the data model's §2 ERD table. Update the bullet directly below to say "v1 has no `subscriptions` / `subscription_items` tables (Cashier not installed) and no `Billable` trait on `User`."
- **Why:** The ERD listed a relationship to `subscriptions`, which does not exist in v1. A diagram that names a non-existent table contradicts §3.5 ("Not in v1").
- **Affects:** Data model §2 (ERD table + the bullet underneath).

### D24. Rerise summary bumped to v1.1 (audit + cleanup)

- **Decision:** Promote `rerise.md` from v1.0 (with ADDENDUM v1.0a) to a single unified v1.1. Removed the now-moot Item 5 (Webhook log), struck the "(proposed, not yet confirmed)" tag from Item 7 (settled in v2.3), renumbered the build plan from 9 steps to 8 steps (old Step 6 Stripe+webhooks deleted), dropped the "25 Pro" / "1000 Pro" caps from Step 2 / Step 3, marked Fix 1 and Fix 2 of the Step 1 prompt as superseded by Fix 15, removed the "webhook route choice" open point, and refreshed §6 against the current UNVERIFIED list (Cloudflare R2 / Oracle / Resend all marked "Not assumed in v1" with the no-card rationale).
- **Why:** Rerise is the index file other agents will read first. After v2.3 finalization, it still described a pre-finalization world (9 steps, Stripe in scope, webhooks in scope). Same drift pattern as D20 / D21.
- **Affects:** `.puku-cli/rerise.md` (entire file rewritten as v1.1; ADDENDUM v1.0a left in place for traceability).

### D25. Agent 2's job redefined: detailed implementation plan from PRD + data model

- **Decision:** Rerise v1.1 §0 Roles now says Agent 2 is the agent that **plans the detailed implementation of the project**, using the PRD and the data model file as reference — translating design decisions into a concrete build plan (migrations, models, factories, seeders, routes, Livewire components, Blade views, policies, FormRequests, jobs, commands, tests, config files) and the acceptance check for each step. Agent 2 does not write the code itself; it produces the plan that the coding agent will execute.
- **Why:** User clarification. The previous wording ("plans and writes prompts for the coding agent") was too vague and read as if Agent 2 was the prompt-writer for an external coding agent rather than the project's planner. Agent 2's deliverable is the **detailed implementation plan**, not prompts.
- **Affects:** `.puku-cli/rerise.md` §0 (Roles) and §2 (Topic 6 header). The 8-step build plan, Step 1 fixes list, and Open points for Agent 2 in §2 are unchanged — they are the artifacts Agent 2 must produce against the new role description.

### D26. Agent 1's job redefined: finalize PRD + data model

- **Decision:** Rerise v1.1 §0 Roles now says Agent 1 is the **PRD / data model** agent. Main goal: **finalize the PRD and the data model**. Reads both docs, finds mismatches / contradictions / gaps / stale items between them and against the user's stated constraints (Free plan only, no Stripe/Cashier/webhooks, no card on file, no paid trials), and proposes the fix. Reviews every change Agent 2 suggests that touches the PRD or data model. Does not write code; does not write build plans.
- **Why:** User clarification. The previous "BMad brainstorming skill, Creative Partner mode, chat only, finds PRD gaps" underspecified what Agent 1 is for. The end goal is **two clean, matching, finalized docs**, not a list of vague "gaps".
- **Affects:** `.puku-cli/rerise.md` §0 (Roles), §1 (Topic 5 header), §3 (Current status pending line).

### D27. Prompt flow + agents may ask the user questions

- **Decision:** Rerise v1.1 §0 Roles gains a "Prompt flow" bullet: both agents (Agent 1 and Agent 2) receive **master prompts from the user (or from the user's AI agent)** for the work they should do. Both agents are free to **ask the user questions back** whenever they are uncertain, blocked, or need a decision — they do not have to make assumptions on the user's behalf. Claude's job is to read the replies, correct mistakes before they spread, and keep the workflow moving.
- **Why:** User clarification. The previous setup implied the user only delivers prompts and reads output, with no back-and-forth. The user wants the agents to behave like real collaborators that can ask when blocked rather than guess.
- **Affects:** `.puku-cli/rerise.md` §0 (new "Prompt flow" bullet under Roles).

### D28. Agent 2's main goal: finalize the implementation method

- **Decision:** Rerise v1.1 §0 Roles line for Agent 2 now states the main goal explicitly — to **finalize the implementation method** of the project. The §2 Topic 6 lead-in is updated to the same wording ("finalize the implementation method — i.e. produce a concrete, agreed-upon way to build the project"). The implementation method is the output: migrations, models, factories, seeders, routes, Livewire components, Blade views, policies, FormRequests, jobs, commands, tests, config files, and the acceptance check that closes each step. The output is the method, not the implementation itself.
- **Why:** User clarification. "Plan the detailed implementation" was too vague and read as if Agent 2 was building toward a generic spec. The user wants Agent 2 to converge on **the implementation method** — a final, agreed-upon way to build — which the coding agent will then execute.
- **Affects:** `.puku-cli/rerise.md` §0 (Roles — Agent 2 line) and §2 (Topic 6 lead-in).

### D29. `build order.md` exists and is Agent 2's implementation-method draft

- **Decision:** The user has produced `build order.md` (v2.3) at the project root, which contains the 8-step plan with per-step artifacts and "Done means" acceptance checks, and states there are **no** deviations from `final PRD v2.3` / `data-model-v2.3`. The rerise §2 Topic 6 lead-in now points to `build order.md` as the **authoritative implementation method** (the §2 8-step list below the lead-in is treated as the historical record). Rerise §3 Current status updates Agent 2's pending item from "response to revised Step 1 prompt" to "review of `build order.md` Step 1 against the Step 1 prompt fixes list (3–15); any revisions to subsequent steps".
- **Why:** The user has effectively pre-produced the implementation method (Agent 2's deliverable). Pointing agents at it prevents duplicated work and makes the rerise point at the real artifact instead of a historical summary.
- **Affects:** `build order.md` (header bumped to v2.3; deviations header bumped to v2.3); `.puku-cli/rerise.md` §2 (Topic 6 lead-in) and §3 (Current status).

---

### D30. Refresh `project building continuation file.md` to v2.3 / v2.4 reality

- **Decision:** End-to-end rewrite of `project building continuation file.md` so the long-term handoff doc matches the current state of the project. The old version was written before finalization and contradicted v2.3 on Free-plan-only, Stripe/Cashier removal, `config/limits.php` vs `Plan` enum, consent versioning, local disk, log mailer, the 8-step build order, and the two-agent prompt flow. New version points at the four authoritative files (`final PRD v2.3.md`, `data-model-v2.3.md`, `build order.md` v2.4, `decision_log.md`) plus `.puku-cli/rerise.md` v1.1, and adds §9 "Open Items" listing the Agent 1 follow-up for the two flagged route/method names (`GET /spaces/check-slug`, `InboxRow::withdrawConsent` + `POST /inbox/{testimonial}/withdraw-consent`).
- **Why:** When `build order.md` v2.4 was being built, Agent 2 cited this file as "authoritative" even though it described the pre-finalization world (Pro plan, Stripe/Cashier, `final PRD v2.md` filenames, `Plan` enum, single coding agent). A new AI assistant reading it would reintroduce Stripe, drop consent versioning, use the wrong filenames, and bypass the two-agent workflow. Same drift pattern as D20 / D21 / D24, applied to the last stale doc.
- **Affects:** `project building continuation file.md` (entire file rewritten); cross-references from `.puku-cli/rerise.md` and `build order.md` v2.4 §"authoritative sources" (now accurate); `decision_log.md` (this entry).

---

### D31. PRD v2.4 + data-model v2.4 — align with build order.md v2.4 flagged items

- **Decision:** Promote the PRD to v2.4 and the data model to v2.4 by adding the two artifacts `build order.md` v2.4 had flagged for Agent 1 follow-up: (a) GET /spaces/check-slug?slug=X → PRD §5 + data-model §3.2; (b) `InboxRow::withdrawConsent(Testimonial $t)` + POST /inbox/{testimonial}/withdraw-consent → PRD §7 + data-model §3.3. No other section touched. New §8.14 divergence entry records the framing difference between the PRD route and the data-model route.
- **Why:** `build order.md` v2.4 §"Flagged items" explicitly listed G1 + G2 as TBD pending Agent 1's next pass. Without this pass, the build order has invented names that aren't in the PRD or data model — a doc-drift pattern we've been preventing since D20/D21.
- **Affects:** PRD §5, PRD §7, PRD header version note, data-model §3.2, data-model §3.3, data-model §8, data-model header version note, `decision_log.md` (this entry).

---

### D32. Final cut — collapse v2.x filenames to canonical no-version filenames

- **Decision:** Rename `final PRD v2.4.md` → `final PRD.md` and `data-model-v2.4.md` → `data-model.md`. Update cross-references in `build order.md`, `project building continuation file.md`, `.puku-cli/rerise.md`, and `data-model.md` to use the new filenames. Delete `final PRD v2.md`, `final PRD v2.1.md`, `final PRD v2.2.md`, `data-model-v2.md`, `data-model-v2.1.md`, `data-model-v2.2.md`, `rerise v1.md`, `context.md`, and the three `testimonial PRD (gemini/claude/gpt).md` original-draft files. **Final canonical file set:**
  - `assignment.md` (school brief, read-only)
  - `final PRD.md` (PRD v2.4)
  - `data-model.md` (data model v2.4)
  - `build order.md` (v2.4)
  - `decision_log.md` (D1–D32, this entry)
  - `project building continuation file.md` (handoff doc, refreshed at D30)
  - `.puku-cli/rerise.md` (v1.1)
- **Why:** Every prior session ended with version-suffixed filenames (`v2`, `v2.1`, `v2.2`, `v2.3`, `v2.4`) piling up. New agents (and the user) had to guess which one was current. Same doc-drift pattern as D20 / D21 / D24 / D30, but applied to the file naming scheme itself. The decision log is the only place historical filenames survive (Option A, agreed earlier): D1–D31 entries still mention old filenames in their "Affects" lines because the ledger is a record of what was decided, not a wiki to rewrite.
- **Affects:** Filenames at the project root; cross-references in `build order.md`, `project building continuation file.md`, `.puku-cli/rerise.md`, `data-model.md`; deleted legacy files (listed above); `decision_log.md` (this entry).

---

### D33. `build order.md` v2.4 → v2.5 — resolve G1 + G2 markers

- **Decision:** Bump `build order.md` to v2.5. Resolve the two inline "(Exact route name TBD — see Flagged items below)" markers in Step 2 and Step 4 by replacing them with the canonical names directly: `GET /spaces/check-slug?slug=X` (Step 2) and `InboxRow::withdrawConsent(Testimonial $t)` + `POST /inbox/{testimonial}/withdraw-consent` (Step 4). Replace the §"Flagged items" section with §"Resolved in v2.4 (logged in D31)" that points readers at `final PRD.md` §5 / §7 and `data-model.md` §3.2 / §3.3. Add §"What changed in v2.5" at the end and demote the prior §"What changed in v2.4" to a historical section. No step instructions, stack rules, or "Done means" acceptance checks were changed. Update the version reference in `project building continuation file.md` from "build order.md (v2.4)" to "build order.md (v2.5)".
- **Why:** After D31, the build order was the only file with stale "TBD" markers. G1 + G2 are now resolved in the canonical specs, so the markers misrepresented the state of the project going into implementation. A coding agent reading v2.4 might have paused at the TBD or invented a different name. v2.5 puts the build order in lockstep with `final PRD.md` and `data-model.md`, with zero TBDs and zero pending follow-ups.
- **Affects:** `build order.md` (header bumped, Steps 2 + 4 inline markers resolved, Flagged items section renamed, What changed in v2.4 demoted to historical); `project building continuation file.md` §11 (version reference); `decision_log.md` (this entry).

### D34. Slug-check probe — `routes/web.php`, `withTrashed`, 60-char trim, no 404-if-no-Spaces

- **Decision:** `GET /spaces/check-slug?slug=X` lives in `routes/web.php` (auth middleware). The handler looks up `Space::withTrashed()` (so soft-deleted and tombstoned slugs count as taken), checks the reserved list (`s`, `api`, `admin`, `login`, `register`, `dashboard`, `billing`, `embed`, `assets`, `up`), and trims any appended `-N` suffix so the final slug is at most 60 chars. The probe **always** returns `200` with `{available: bool, suggested: string|null}` — it does **not** return 404 when the authenticated user has no live Spaces.
- **Why:** `routes/api.php` would conflict with the locked "no API-only split" decision and would require `php artisan install:api` (Sanctum scaffolding), which is out of scope. `withTrashed()` matches the tombstone pattern (slug stays claimed forever). Trim-to-60 keeps the form contract honest when the suggested suffix would push past 60. The "no 404" rule matches the spec — the probe is a UX aid, not a security boundary.
- **Affects:** `final PRD.md` §5 (slug wording); `data-model.md` §3.2 (Reads/writes — slug probe); `data-model.md` §8 (drop §8.14 divergence entry); `build order.md` Step 2 + Step 1 config notes.

### D35. Withdraw-consent is the `InboxRow::withdrawConsent` method only — no POST route

- **Decision:** Withdraw-consent is the Livewire method `InboxRow::withdrawConsent(Testimonial $t)` on the inbox row component. **No POST route** (no `POST /inbox/{testimonial}/withdraw-consent`, no `POST /api/inbox/{testimonial}/withdraw-consent`). Livewire calls the method directly via its own endpoint. Adding a controller-backed route would duplicate the authorization logic. The method **also** sets `is_wall_of_love = 0` (so the UI never shows a Wall-of-Love badge on a row that cannot be public). `consent_given = 0` is still set; `consented_at` and `consent_text_version` are still preserved as audit.
- **Why:** v2.5 / D33 had named a POST route alongside the method, framed as "Livewire default POST endpoint". Livewire doesn't actually use that route — it has its own internal action endpoint. The "Livewire default POST endpoint" framing was wrong, and the route would be dead code or duplicate authorization. Setting `is_wall_of_love = 0` is consistent with the `is_public` rule (`consent_given AND is_wall_of_love AND NOT is_hidden AND NOT deleted`) and prevents a UI lie.
- **Affects:** `final PRD.md` §7 (Withdraw-consent line), §14 (Consent acceptance line), §13.2 (Privacy flow); `data-model.md` §3.3 (State changes — withdraw-consent); `build order.md` Step 4 (per-row actions).

### D36. Auth throttles moved inside Livewire components

- **Decision:** Forgot-password, reset-password, and register are Breeze Livewire/Volt components, not POST routes. Rate limiting is enforced **inside the component action** via `RateLimiter::tooManyAttempts` / `hit`. Two keys: per IP, and per email+IP. Limit: 5 attempts per 15 minutes (`Limit::perMinutes(15, 5)`). The named limiters are defined in `AppServiceProvider::boot()` (Laravel 11 has no `RouteServiceProvider`). On limit hit, a validation error "Too many attempts. Try again later." is added and the action does not proceed. Login keeps Breeze's built-in `ThrottlesLogins` (per email + per IP).
- **Why:** Route-level `throttle` middleware does not see what's inside a Livewire action. The previous v2.5 / D33 setup applied `throttle:5,1` on POST routes that Breeze doesn't actually use; the limit was silently inactive. The user-confirmed rule is the correct shape.
- **Affects:** `final PRD.md` §13.0 (Auth throttles); `data-model.md` §6 (Security table); `build order.md` Step 1 (Auth throttles + AuthThrottleTest replacing PasswordResetRateLimitTest).

### D37. Photo storage uses the `public` disk + `php artisan storage:link`

- **Decision:** Photo uploads and reads go through Laravel's **`public` disk** (served from `storage/app/public` via `php artisan storage:link`). `photo_url = Storage::disk('public')->url($path)`. The Step 1 install runs `storage:link`; the README documents it. Forget, purge, and the "delete file if DB insert fails" rollback all use the `public` disk. A Pest test asserts that a freshly uploaded photo's URL returns `200` from the local server.
- **Why:** The previous "local disk" wording was ambiguous. The `local` disk lives under `storage/app/private` and is not web-served; embeds on third-party sites need to fetch photos from a URL the browser can reach. The `public` disk is the standard Laravel answer for this. `storage:link` makes the path reachable via the public-symlink.
- **Affects:** `final PRD.md` §6 (photo upload), §13 (Tech stack file storage); `data-model.md` §3.3 (profile_photo column), §9.3 (Profile photo storage); `build order.md` Step 1 (storage:link), Step 3 (photo pipeline), Step 4 (Forget), Step 6 (purge); `project building continuation file.md` §3 (file storage rule).

### D38. Public embed endpoint in `routes/web.php` outside the `web` middleware group

- **Decision:** The public embed endpoint is **`GET /api/spaces/{public_id}/testimonials`** — registered in `routes/web.php` **outside** the `web` middleware group (no `StartSession`, no `AddQueuedCookiesToResponse`, no `VerifyCsrfToken`). It has its own throttle of `120/min` per IP and sets `Cache-Control: max-age=60`. A Pest test sends an `Origin` header and asserts the response carries `Access-Control-Allow-Origin: *`; a second Pest test asserts the response has **no `Set-Cookie`** header and has `Cache-Control: max-age=60`. Laravel 11's default `config/cors.php` allows `*` origins for `api/*` paths, which covers the URL.
- **Why:** The `web` middleware group starts a session, sets cookies, and runs CSRF checks — all of which break CDN caching and add noise to every public embed fetch. The "no API-only split" locked rule applies to the *frontend* (no Inertia, no separate JS framework), not to the *routing layer* — a public JSON route in `routes/web.php` is fine. The path stays under `/api/` so the default CORS rule covers the cross-origin fetch from customers' sites.
- **Affects:** `final PRD.md` §6 (rate limits table); `data-model.md` §6 (Security table — cross-origin embed JSON row); `build order.md` Step 7 (route placement + Pest tests).

### D39. Embed response shape: `{config, testimonials}` with soft-deleted → `{config: null, testimonials: []}`

- **Decision:** The embed endpoint returns:
  - `200 { "config": {…}, "testimonials": […] }` for a live Space — `config` is the `embed_configurations` row, or the **code defaults** (`masonry`, `dark_mode=false`, `animation_enabled=true`, `background_color=null`, `show_rating=true`, `item_limit=12`) when no row exists.
  - `200 { "config": null, "testimonials": [] }` for a soft-deleted Space (embed links keep working during the 30-day retention window).
  - `404` for an unknown `public_id`.
  - **Server-side filter:** when `embed_configurations.show_rating = 0`, the API omits `rating` from every item (do not rely on the client to hide it).
  - **`embed.js`:** when `config` is `null`, renders nothing. All text rendering uses `element.textContent` (never `innerHTML`).
- **Why:** Embeds need both the config (layout, dark mode, etc.) and the items; combining them avoids a second fetch. The `null config` on soft-deleted Spaces is the "this Space is pending deletion" signal that `embed.js` reads. The `textContent`-only rule neutralizes XSS from respondent content. The server-side `show_rating` filter means the embed JSON does not leak a `rating` field the owner disabled.
- **Affects:** `final PRD.md` §8 (Public embed endpoint), §14 (Embeds acceptance); `data-model.md` §4.1 (Embed read), §6 (Security table); `build order.md` Step 7.

### D40. `purge:run` is explicit, not cascade-driven

- **Decision:** Tombstone Spaces are **never** hard-deleted, so the FK cascade on `embed_configurations.space_id` does **not** fire. The purge job is **explicit**, in this exact order:

  **Per testimonial soft-deleted past retention:**
  1. UPDATE matching `deletion_requests` to `status='acted'`, `acted_at = now`.
  2. Delete the photo file from the `public` disk (ignore only "file not found"; any other error keeps the row for the next run).
  3. `forceDelete()` the row.

  **Per Space soft-deleted past retention:**
  1. For each of its testimonials (live **and** soft-deleted): run the three-step order above.
  2. Delete the `embed_configurations` row for that Space explicitly.
  3. Keep the `spaces` row (tombstone) so the slug stays claimed.

  Retention value comes from `config('purge.retention_days') = 30`. New `config/purge.php`.

- **Why:** The previous v2.5 wording said "purge their testimonials and photos, **keep the Space row** (tombstone)" — accurate, but it relied on the v2.4-era assumption that the `embed_configurations` cascade fires when the Space is hard-deleted. Since the Space row is kept, the cascade never fires; the embed row must be deleted explicitly. The three-step order for **single** testimonials is new: previously Forget/purge was a two-step (delete photo → forceDelete). Adding the `acted`-update first closes the audit trail before the `testimonial_id` FK is nulled.
- **Affects:** `final PRD.md` §9.1 (Purge policy); `data-model.md` §3.6 (deletion_requests — Forget order); `build order.md` Step 6 (purge order), Step 1 (`config/purge.php`).

### D41. Deletion request form resolves slug via `withTrashed`; dashboard filters to live Spaces

- **Decision:** The `/privacy/request-deletion` form resolves `space_slug` server-side with `Space::withTrashed()`. If a Space matches, its `space_id` is stored. If none matches, `space_id = null` and `space_slug` is kept as typed. If `testimonial_id` is given, it is accepted only if that testimonial belongs to the resolved Space; otherwise it is stored as `null`. The respondent sees the same thank-you page in every case — the form never reveals whether an ID exists. The dashboard's open-deletion-requests list filters to the owner's **live** Spaces by `space_id`; requests whose `space_id` points at a soft-deleted Space are not shown until the Space is restored (the README notes this).
- **Why:** The `withTrashed()` resolution allows legitimate deletion requests during the 30-day retention window without forcing the respondent to wait. Storing the typed slug preserves the audit trail even when the Space is purged (its `space_id` becomes `null`). The same thank-you page prevents enumeration. The dashboard filter prevents dangling references from cluttering the owner's UI; the README note turns this from a "missing feature" into "expected behavior".
- **Affects:** `final PRD.md` §13.2 (Privacy & deletion requests flow), §14 (Privacy acceptance); `data-model.md` §3.6 (deletion_requests rows, dashboard rule); `build order.md` Step 5 (dashboard list), Step 8 (form + README note).

### D42. Fixture seeders and embed-response expectations pinned exactly

- **Decision:** Fixture seeders produce:
  - **Maya Sharma** → `brightcopy-wins` (6 testimonials), `course-launch` (3 testimonials).
  - **Dev Okafor** → `shiplog-reviews` (100 LIVE testimonials + 1 soft-deleted Ana), `beta-testers` (12 LIVE testimonials).
  - **`shiplog-reviews` named rows:** Priya (consent, WoL, public) / Ben (consent, WoL, hidden → `is_public=0`) / Tom (consent, no WoL → `is_public=0`) / Sara (no consent → `is_public=0`) / Ana (consent, WoL, soft-deleted, not counted).
  - **`shiplog-reviews` generated rows:** 96 live, **first 20 have `is_wall_of_love=1`**.
  - **`beta-testers` named row:** Priya (`priya@nimbus.dev`, **same email as in `shiplog-reviews`**, consent, WoL, public) + 11 generated live rows.
  - **1 open deletion request** on `shiplog-reviews` for Sara's email, `testimonial_id = null`, `status = 'open'`.
  - **Dev's "Total customers" = 111** (100 + 12 live − 1 for Priya counted once across both Spaces; Ana not counted).
  - **`shiplog-reviews` embed** includes Priya + the 20 WoL generated rows (≤ 50 → 21 items); excludes Ben, Tom, Sara, Ana.
  - **`beta-testers` embed** includes Priya + 11 generated rows.

  Cross-user authorization Pest tests: user B cannot read, edit, delete, forget, withdraw-consent, or embed-build user A's data.

- **Why:** The previous v2.5 fixture description named only Priya + Ben and said "100 testimonials, exercises cap". A coding agent reading that could produce any 100-row fixture and pass; the embed-response expectations were unstated. Pinning the named rows (Priya / Ben / Tom / Sara / Ana), the soft-deleted Ana at 101 rows, the exact WoL count (first 20 generated), and the cross-Space Priya email makes the embed and dashboard counters fully testable.
- **Affects:** `final PRD.md` §15 (Suggested Build Order — Step 7 "Seed data and demo readiness"); `data-model.md` §1 (Reference scenario); `build order.md` Step 8 (Fixture seeders + Done means).

---

### D43. Doc-hygiene pass — align canonical docs at v2.5 / v2.6

- **Decision:** Apply a 19-sub-edit doc-hygiene pass across the four canonical files. No new product decisions; no §3 (handoff) "do not re-litigate" rules re-opened. Affected file versions stay at PRD v2.5 / data-model v2.5 / build-order v2.6 / rerise v1.1.
- **Edits, grouped by reason:**
  - **D35 fallout (Withdraw-consent also sets `is_wall_of_love = 0`):** PRD §6.1 line 303 and data-model §7.4 line 415 updated to mirror the existing PRD §7 / §14 / data-model §3.3 wording. (4 places now read consistently.)
  - **D39 fallout (embed response `{config, testimonials}` + server-side `show_rating` filter):** PRD §8 lookup table (line 381) updated; PRD §8 prose gains the server-side `show_rating=0` sentence. Data model and build order were already correct.
  - **D37 fallout (embed snippet URL form):** PRD §8 snippet (line 367) changed from `https://{app}/embed.js` to `{APP_URL}/embed.js`. The other three docs were already correct.
  - **D2 / D22 fallout (no Stripe / no `/billing` wording):** PRD §15 line 674 reworded from "Skipped for v1" to "Removed from v1 (not stubbed, not deferred — see D2)", preserving the historical reference to the original 9-step plan's Step 6.
  - **Stale cross-references in `data-model.md`:** §5.4 line 365 `PRD §10` → `PRD §11`; §7.1 line 403 `PRD §13` → `PRD §9`; §4.3 line 322 `(PRD §10)` → `(PRD §11)`; line 5 `PRD v2.4` → `PRD v2.5`; §8 column-2 header `PRD v2.3 says` → `PRD v2.5 says` (table body left alone — historical record).
  - **Workflow file version bumps (canonical-name alignment per D32):** `.puku-cli/rerise.md` line 130 (forward-looking), line 175, line 176 (with honest Areas 1+2 / 3+4 status); `project building continuation file.md` §5 line 118 (D31/D34/D35 follow-up closed), §6 line 141, §10 line 173, §10 line 180.
  - **Workflow file UNVERIFIED mirror:** `.puku-cli/rerise.md` §6 gains the "Animated GIF re-encode" and "Per-Space frame-ancestor policy" lines already present in PRD §18 and data-model §12 (D19 / D21 mirror rule).
  - **Intentionally NOT edited** (historical references per D32): `.puku-cli/rerise.md` line 110 ("reconciled in PRD v2.3…", inside a "Gaps found in my own first read" historical block) and line 184 ("bumped to PRD v2.3…", inside the §5 "What changed in v1.1" historical block).
- **Why:** Cross-doc consistency scan after D42 surfaced 5 real drift items (D35 fallout in two files, D39 fallout in the §8 table, two stale §-cross-refs in data-model §5.4 and §7.1) plus 12 workflow-file version bumps. A second agent (Areas 3 + 4 of the Agent 1 workflow) re-verified each line against the actual file contents and proposed 2 corrections to the original diffs (Edit 5: preserve "the original 9-step plan's Step 6" framing; Edit 11d: scope the "already complete" parenthetical honestly to Areas 1+2 vs 3+4). The previous agent's "Edit 3.4" finding was rejected because it pointed at the wrong file; this pass folds the correct file (rerise §6) into the list.
- **Affects:** `final PRD.md` §6.1 / §8 / §15; `data-model.md` line 5 / §4.3 / §5.4 / §7.1 / §7.4 / §8 column header; `.puku-cli/rerise.md` §3 (lines 130, 175, 176) and §6 UNVERIFIED list; `project building continuation file.md` §5 / §6 / §10. No new tables, columns, routes, components, or versions introduced. File version headers unchanged.

---

_Last updated: 2026-10-08 (doc-hygiene pass; 19 sub-edits across final PRD v2.5, data-model v2.5, .puku-cli/rerise.md v1.1, project building continuation file.md — D35/D39 fallout + stale cross-refs + workflow version bumps; no new decisions; D43 logged)._