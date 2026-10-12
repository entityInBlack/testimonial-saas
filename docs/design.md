# design.md - UI polish plan (Testimonial SaaS)

Written Monday 12 October 2026 (evening, +0600) by Claude with Jahin Kabir.
This is a PLAN ONLY. Nothing in it has been built. It is executed in the next session.

Save it as `C:\11111\design.md` (original). In Wave 0 a copy goes to `C:\11111\testimonial-saas\docs\design.md` and is committed on `polish-final` (originals in `C:\11111` win, as for the other planning docs).

**Labels:** VERIFIED = seen in a code dump or screenshot on 12 Oct. JAHIN = Jahin's statement, not pasted. UNVERIFIED = reasoning or docs only. Do not treat UNVERIFIED as fact.

---

## 1. State and dates

| Item | State |
|---|---|
| `main` | `a3d90e8` = `origin/main`, clean (VERIFIED by pasted `git log` before tagging) |
| Tag `pre-polish` | on `a3d90e8`, pushed (VERIFIED: `git ls-remote --tags origin` showed `a3d90e878b5e5e2b78df4f5a07f1410957929057 refs/tags/pre-polish`) |
| Claude Pro feature tests | deferred by Jahin ("we will test the pro features later") |
| **Deadline** | **Thu 15 Oct 2026** (JAHIN: extended by 2 more days; the handoff still says 13 Oct) |
| **Polish cut-off** | **end of Wed 14 Oct 2026**: polish merged into `main` by then. Thu 15 is only reseed, recording, reflection re-read, submit. (Proposed by Claude; Jahin replied "ok give the dump commands") |
| Why the UI is weak | No UI plan was ever written: the PRD, data model and build order describe behaviour and data, not looks. Pages were styled one by one. |

---

## 2. Decisions to confirm at the start of the next session

Claude's recommendation is given for each. Jahin confirms or changes them before any master prompt.

| # | Decision | Recommendation | Why |
|---|---|---|---|
| D1 | Font | One stack everywhere: the system stack already used by landing and privacy (`system-ui, -apple-system, "Segoe UI", Roboto, sans-serif`). Remove the `fonts.bunny.net` Figtree links from `layouts/app.blade.php` and `layouts/guest.blade.php` and set Tailwind `sans` to the same stack. | Today two pages use the system font and the rest use Figtree from a remote host. No new remote resource is allowed (landing comment lines 10-12), so unify on the font that needs none. |
| D2 | Brand mark | Text wordmark: `config('app.name')` ("Testimonial SaaS") in every header, no invented logo. | The Laravel logo shows on every guest and app page today. A text wordmark is honest and needs no artwork. |
| D3 | Buttons | Indigo primary, sentence case (no `uppercase tracking-widest`). One primary, one secondary, one danger style. | Two primary styles exist today (gray-800 Breeze vs indigo). Indigo is already the accent on landing, dashboard tabs, chart and links. |
| D4 | Spaces row click target (#12) | The Space title links to that Space's Inbox (`/inbox?spaceId=<id>`); the public URL `/s/<slug>` becomes a real link that opens in a new tab. Embed / Edit / Delete stay as they are. | Owners open a Space to read responses; the public link is for checking what customers see. |
| D5 | Inbox "WoL" label (#21) | Show "Wall of Love" in full. If a test asserts the exact text "WoL", keep the text and STOP and report (do not change the test). | "WoL" is unclear to a first-time viewer (the client). |
| D6 | #14 Inbox search | Stay deferred (after the deadline). | Feature change: hard rule 15 means editing the PRD, data model and build order plus new tests. |
| D7 | #24 snippet copy button | Include as the LAST item of Task A, optional; drop it if time runs short. | Useful on mobile (the snippet is cut off at 320 px), but not essential. |

---

## 3. Design rules (the brief)

### 3.1 Subject, audience, job

- **Subject:** a tool for collecting customer testimonials and showing them as a "wall of love".
- **Audiences:** (a) the Space owner (logged in: dashboard, Spaces, Inbox, embed builder); (b) the customer leaving a testimonial on `/s/{slug}` (public, often on a phone); (c) Jahin's project manager and client watching the demo.
- **Primary job of the polish:** make the app look like ONE product. Today it looks like four (landing shell, privacy shell, Breeze guest shell, Breeze app shell).
- **Reference page:** the desktop dashboard (white cards on light gray, rounded corners, soft shadow, indigo accent) is the best page today. Other pages move towards it, not the reverse.
- **The one memorable element:** testimonial cards (embed preview, embed, Inbox): the quote text is the hero of each card, stars in amber. Everything else stays quiet. No decoration added elsewhere.

### 3.2 Tokens

Tailwind 3.4 default palette only (no custom colours, no new plugins).

| Role | Tailwind | Hex | Used for |
|---|---|---|---|
| Ink | `gray-900` | `#111827` | headings, body text |
| Muted | `gray-600` | `#4B5563` | secondary text, hints |
| Surface | `white` | `#FFFFFF` | cards, headers |
| Canvas | `gray-100` | `#F3F4F6` | page background (app and guest) |
| Accent | `indigo-600` / hover `indigo-700` | `#4F46E5` / `#4338CA` | primary buttons, links, active tab |
| Stars | `amber-400` | `#FBBF24` | rating stars and selected rating only |
| Danger | `red-600` | `#DC2626` | Delete, destructive actions |

Status colours that already exist (emerald "Current"/"Live", amber "Hidden") stay.

### 3.3 Typography

- One family (D1). Sentence case everywhere: no `uppercase` + `tracking-*` on buttons or labels (today: buttons, dashboard counter labels, landing "Free plan" heading). CSS case changes do not change the HTML text, so tests asserting text are not affected; still check.
- Scale: page title `text-xl font-semibold` (app header) / `text-2xl font-semibold` (guest page title); section title `text-lg font-semibold`; body `text-sm` or `text-base`; hints `text-xs text-gray-600`.
- Landing hero: smaller on narrow screens (today 4 lines at 320 px); keep `sm:text-4xl` on larger screens.
- Line length: prose blocks max about 75 characters (`max-w-prose` or `max-w-2xl`).

### 3.4 Layout

**Breakpoints:** Tailwind defaults (`sm` 640, `md` 768, `lg` 1024). Every page must work at **320 px** and at a maximized desktop window.

**Gutters:** minimum 16 px side padding at every width (`px-4`, then `sm:px-6 lg:px-8`). Today the dashboard and Spaces wrappers have NO padding below 640 px (`max-w-7xl mx-auto sm:px-6 lg:px-8`, `dashboard-index.blade.php:10`; `max-w-5xl ...`, `space-index.blade.php:25`), giving about 4 px gutters.

**App pages (logged in):** the content's left edge equals the page title's left edge at every width (fixes #22). Use the same container as the header: `max-w-7xl mx-auto px-4 sm:px-6 lg:px-8`. A page may narrow its inner column, but left-aligned under the title, never re-centred.

**Guest pages (public):** one shared public header and footer (see 3.5). Exactly ONE card surface per page: the layout does not draw a card around content that already has cards (fixes #11). Widths: auth forms (login, register, forgot/reset password, verify email, confirm password) `max-w-md`; public forms (`/s/{slug}`, request deletion) `max-w-2xl`; privacy `max-w-3xl` (as today).

**Page header (app pages):** title (+ optional subtitle) and actions. At `sm` and up: one row, title left, actions right. Below `sm`: title on top, actions below, nothing overlapping (fixes #20, #25).

**Lists of items with actions (Spaces rows, Inbox cards):** below `sm`, stack: identity (name, URL / email, time) on top, actions in a wrapping row below. Never join meta with " · " where it can wrap and leave a dangling dot; put each meta item on its own line on narrow screens.

**Touch targets:** buttons and row actions at least 44 px tall on narrow screens (inputs already have `min-h-[44px]`). Checkboxes are DRAWN at normal size (about 16-20 px) and get the 44 px tap area from their label/wrapper, not from the box itself (fixes the oversized consent box, `public-submission.blade.php:178`).

### 3.5 Shared pieces (built in Wave 0, used by everyone)

1. **Brand wordmark** (D2): used in the public header and the app nav. Links to `/` (guest) or the dashboard (app), with an accessible name.
2. **Public header:** wordmark left; right side: guest = "Log in" + "Create account" (primary); logged in = "Dashboard". Privacy and Request deletion are shown from `sm` up and hidden below `sm` with a responsive class (they stay in the HTML and in the footer). Used by landing, privacy and every guest-layout page.
3. **Public footer:** wordmark, Privacy, Request deletion (as landing today).
4. **Button components:** `primary-button`, `secondary-button`, `danger-button` follow D3. Pages that hand-write button classes (`/s/{slug}` submit, deletion submit, "New Space", dashboard empty state) switch to the shared style (Wave 0 for guest pages, Wave 1 for app pages).
5. **Page header component** (new, e.g. `components/page-header.blade.php`): title, optional subtitle, actions slot, stacking rule from 3.4. Wave 1 pages adopt it.
6. **App nav:** wordmark instead of the Laravel logo; Log Out separated from Profile by a divider in the desktop dropdown AND the mobile menu (#4).

### 3.6 States and copy

- **Loading:** a control that starts a server round trip shows a short text, not only a disabled look. Reference pattern already in the repo: `deletion-request-form.blade.php:72-73` ("Send request" / "Working..."). Apply to the dashboard selects (#10).
- **Empty states:** say what to do next (the dashboard and Spaces already do; keep).
- **Dates shown to people:** human-readable (e.g. "11 Oct 2026"). Applies to the embed (#16). The API keeps its ISO format (hard rule 10 untouched).
- **Copy:** plain words, sentence case, active voice. Do NOT change any text a test asserts; if a change needs it, STOP and report.

### 3.7 Wireframes (ASCII)

Public header, 320 px:

```
+--------------------------------------+
| Testimonial SaaS   Log in [Create ac]|   <- Privacy / Request deletion hidden < sm
+--------------------------------------+
```

Guest form page (e.g. `/s/beta-testers`), 320 px, one card level:

```
[public header]
  Beta Testers                      <- page title on the canvas, no card
  What our beta testers are saying
  +----------------------------------+
  | Name      [____________________] |  <- the ONLY card
  | ...                              |
  | Rating    (1)(2)(3)(4)(5)        |  <- one row at 320
  | [x] I give permission ...        |  <- normal-size box, 44 px tap area
  | [   Submit testimonial   ]       |  <- full width < sm
  +----------------------------------+
  Back to home
[public footer]
```

App page header, 320 px vs desktop:

```
320:     | Spaces                    |      desktop: | Spaces         Deleted Spaces [New Space] |
         | Deleted Spaces [New Space]|
```

Spaces row, 320 px:

```
| Shiplog Reviews  (-> Inbox)          |
| /s/shiplog-reviews (opens public)    |
| public_id en7mozr1qsiu               |
| [Embed] [Edit] [Delete]              |
```

---

## 4. Polish items

Numbering continues the handoff list. #1 and #3 were done in the earlier parallel task. #17 (Jahin's own "thing or two") is still unnamed; ask once.

**Evidence key:** `d1` = `wave0-dump.txt`, `d2` = `wave0-dump-2.txt` (both uploaded 12 Oct); line numbers below are the REPO file's line numbers. `ss` = screenshot, 12 Oct.

### 4.1 Wave 0 - shell and guest side

| ID | Problem | Evidence | Acceptance |
|---|---|---|---|
| #13 (widened) | Laravel logo on every guest and app page; app name nowhere when logged in | `guest.blade.php:20-22`, `navigation.blade.php:26-28` (d1); ss of login, register, `/s`, deletion, dashboard, Spaces, Inbox | Wordmark (D2) in guest header and app nav; no `x-application-logo` visible; link has an accessible name |
| #2 | Login has no link to Register (Register already links to Login) | `login.blade.php:59-69`, `register.blade.php:119-121` (d2); ss | Login shows a "Create account" link to `route('register')`; a test asserts it |
| #4 | Log Out not separated from Profile | `navigation.blade.php:61-70` and `108-118` (d1) | Divider before Log Out in the dropdown and the mobile menu |
| #11 | Nested white-in-white cards on guest pages; card only `sm:max-w-md` | `guest.blade.php:25` (d1); `public-submission.blade.php:2-3, 27, 72`, `deletion-request-form.blade.php:2-3, 22, 28` (d2); ss at 320 and desktop (fields about 190 px wide at 320) | One card level per page; widths per 3.4; fields use the available width at 320 px (16 px gutters + card padding only) |
| #15 | Landing header has no "Log in"; header crowded at 320 px (brand wraps, "Testimonial" touches "Privacy", "Create account" wraps) | `landing.blade.php:28-36` (d1); ss 320 logged out | Public header (3.5) with Log in; fits on one line at 320 px; all existing `data-testid`s kept |
| #27 | Two primary button styles (gray-800 Breeze vs hand-written indigo) | `primary-button.blade.php:1` (d1); `public-submission.blade.php:193-197`, `deletion-request-form.blade.php:67-74` (d2); ss | Buttons follow D3 on all guest pages and in the shared components |
| #28 | Consent checkbox drawn 44 px wide | `public-submission.blade.php:178` (d2); ss | Box drawn at normal size; tap area still at least 44 px; consent text not squeezed |
| #29 | Rating buttons wrap 3+2 at 320 px; plain gray squares | `public-submission.blade.php:158-168` (d2); ss | Five in one row at 320 px; selected state still visible (amber); `rating-{n}` test ids kept. Stars instead of numbers optional |
| #30 | Guest pages have no header or way back (request-deletion has no link out at all) | ss of deletion page; `deletion-request-form.blade.php` has no back link (d2) | Shared public header and footer on every guest-layout page |
| #31 | Landing mobile details: hero 4 lines at 320 px; "Read the privacy policy" indented relative to the button; "Free plan" heading in caps | `landing.blade.php:44, 54, 65` (d1); ss | Smaller hero on narrow screens; link aligned with the button's left edge; sentence case |
| #32 | No page heading on login and register | `login.blade.php`, `register.blade.php` (d2); ss | A heading ("Log in", "Create account") on both; other auth pages get one if they lack it |
| #33 | Two font families (D1) | `app.blade.php:11-12`, `guest.blade.php:11-12`, `landing.blade.php:13-16`, `tailwind.config.js:16` (d1); `privacy.blade.php:10-13` (d2) | One stack everywhere; no remote font link left; no new remote resource |
| #34 (optional) | Privacy "How to request removal" link text is the full absolute URL (touches the edge at 320 px) | `privacy.blade.php:72` (d2); ss | Link text "request deletion form" ONLY if no test asserts the URL text (`privacy-removal-link`); otherwise leave and report |
| #35 (report only) | `livewire/welcome/navigation.blade.php` looks like an unused Breeze leftover (has `dark:` classes, `#FF2D20`) | d1 | Agent greps for usages and REPORTS; does not delete |
| - | Write `docs/design.md` | this file | Copy of `C:\11111\design.md`, hash-matched |

### 4.2 Wave 1, Task A - Embed

| ID | Problem | Evidence | Acceptance |
|---|---|---|---|
| #5 | Builder preview is one long single column titled "Preview (masonry)"; carousel and dark toggles have no visible effect (`embed-preview-carousel` / `embed-preview-dark` have no CSS, per handoff) | ss desktop and 320 | Preview visibly changes for masonry vs carousel and for dark mode, close to what `embed.js` renders |
| #6 | `embed.js` should validate `background_color` against `^#[0-9A-Fa-f]{6}$` before using it | handoff | Invalid values are ignored (default background); `textContent` only (hard rule 11) |
| #7 | Item-limit hint says values above 50 are clamped, but the input refuses them | ss ("Values above 50 are clamped to 50; values below 1 become 1.") | Hint matches the real behaviour; any test asserting the old hint updated with the reason stated |
| #8 | Embed renders 3 columns at 320 px (cards about 95 px wide, tiny text) | ss of `embed-test.html` at 320 px | 1 column at narrow widths, more as space allows; cause found in `embed.js` (not seen yet) |
| #16 | Embed cards show raw ISO timestamps (`2026-10-11T17:00:00+00:00`) | ss of `embed-test.html` | Human-readable date in the card; API output unchanged |
| #23 | Builder page: snippet buried under the full preview (about 2 screens on desktop, 3 at 320 px); left card stretches leaving empty space | ss desktop and 320 | Snippet reachable without scrolling past the preview (preview height capped with its own scroll, or snippet placed above/beside it); no stretched empty card |
| #22 (builder) | Content starts right of the title (title x about 331, cards x about 395) | ss desktop | Content left edge equals title left edge |
| #25 (builder) | Header "Embed builder - Shiplog Reviews - Back to Spaces" collides at 320 px | ss 320 | Uses the page header component; no overlap at 320 px |
| #24 (optional, last) | No copy button on the snippet; snippet scrolls sideways at 320 px | ss | Copy button that copies the exact snippet; works without breaking the page if the clipboard API is unavailable |

**Task A files (only these):** `public/embed.js`, `public/demo-embed.html`, `app/Livewire/Spaces/EmbedBuilder.php`, `resources/views/livewire/spaces/embed-builder.blade.php`, and the tests that cover them. Embed API code, routes and the API Resource are OUT of scope (hard rules 8-10).

**Testing note (Task A):** Pest cannot run browser JavaScript, so #6, #8 and #16 get NO fake tests (no "file contains string" tests). They are checked by hand in Wave 2 on `file:///C:/11111/embed-test.html` at 320 px and desktop. #7 and #23 can have Livewire tests.

### 4.3 Wave 1, Task B - App pages

| ID | Problem | Evidence | Acceptance |
|---|---|---|---|
| #9 | `DashboardGraphTest.php` comment blames `wire:snapshot` (it was `data-chartjs-src`) | handoff | Comment corrected; no assertion changed |
| #10 | Dashboard selects show only a disabled state while loading | `dashboard-index.blade.php:164, 176, 189` (d2) | A short "Loading..." / "Working..." text appears while a select's request runs (pattern 3.6) |
| #18 | Chart filter labels detach from their selects when the row wraps ("Granularity [Day] Range" / "[30d] Space" / "[All]") | `dashboard-index.blade.php:160-198` (labels `ml-2`, `flex-wrap`) (d2); ss 320 | Each label + select wraps as one unit; at 320 px every label sits directly with its own select |
| #19 | Dashboard at 320 px: about 4 px gutters; five counter cards one per row | `dashboard-index.blade.php:10, 62` (d2); ss 320 | 16 px gutters; counters 2 per row below `sm` (5th spans or sits alone cleanly); desktop unchanged (5 in a row) |
| #12 + D4 | Spaces rows not clickable; public URL plain text | `space-index.blade.php:47-52` (d2); ss | Title links to the Space's Inbox; `/s/<slug>` is a link opening in a new tab; tests assert both hrefs |
| #20 | Spaces header crowded at 320 px; rows don't wrap ("public_id:" breaks, actions cramped) | `space-index.blade.php:3-21, 47-72` (d2); ss 320 | Page header component; rows stack per 3.4; actions at least 44 px tall below `sm` |
| #21 | Inbox at 320 px: Space select truncated ("Shiplog Reviews (/s/shiplog-re"); card meta line leaves dangling " · "; six action buttons wrap over 2-3 rows; "WoL" unclear | ss 320 and 294; ss desktop | Select shows the Space name in full at 320 px (slug moved out of the option label or shortened, only if no test asserts it); meta stacks below `sm`; actions grouped with Delete visually apart from the rest; D5 |
| #26 (investigate first) | Hidden card shows both "Hidden" and "Live" badges | ss desktop and 320 | Agent reads `InboxRow` and REPORTS what "Live" means. Change the label only if its meaning is clear from the code and no test asserts the text; otherwise leave |
| #22 | Content narrower than and right of the page title on Spaces and Inbox (`max-w-5xl` vs header `max-w-7xl`) | `space-index.blade.php:25` (d2); ss desktop | Left edges match (3.4) on dashboard, Spaces, Inbox, Space form / created / deleted pages, profile |
| #25 | Page header pattern on all Task B pages | - | Page header component used; no overlap at 320 px |
| #27 (app) | "New Space" and the dashboard empty-state link hand-write indigo uppercase buttons | `space-index.blade.php:14-18`, `dashboard-index.blade.php:52-56` (d2) | Follow D3 |
| sentence case | Dashboard counter labels uppercase | `dashboard-index.blade.php:65` etc. (d2) | Sentence case; `data-testid`s and text unchanged |

**Task B files (only these):** `resources/views/livewire/dashboard/*`, `resources/views/livewire/spaces/*` EXCEPT `embed-builder.blade.php`, `resources/views/components/spaces/deleted-list.blade.php`, `resources/views/livewire/inbox/*`, `resources/views/profile.blade.php`, `resources/views/livewire/profile/*`, their Livewire classes under `app/Livewire/Dashboard`, `app/Livewire/Spaces` (except `EmbedBuilder.php`), `app/Livewire/Inbox`, and `tests/.../DashboardGraphTest.php` plus the tests covering these pages. The Free plan note text stays EXACT (`dashboard-index.blade.php:13-21`, hard rule 2).

---

## 5. Waves, ownership and setup

### 5.1 File ownership (no file in two places)

| Owner | Files |
|---|---|
| **Wave 0** | `resources/views/layouts/*`, `resources/views/livewire/layout/navigation.blade.php`, `resources/views/livewire/welcome/navigation.blade.php` (report only), all `resources/views/components/*.blade.php` except `components/spaces/`, new shared components, `resources/css/app.css`, `tailwind.config.js`, `resources/views/landing.blade.php`, `resources/views/privacy.blade.php`, `resources/views/livewire/pages/auth/*`, `resources/views/livewire/public-submission.blade.php`, `resources/views/livewire/privacy/deletion-request-form.blade.php`, `resources/views/errors/*`, `docs/design.md`, and the tests covering these |
| **Task A** | section 4.2 list |
| **Task B** | section 4.3 list |

Wave 1 tasks NEVER edit Wave 0 files (no `app.css`, no shared components). If a Wave 1 task needs a shared change, it STOPS and reports.

### 5.2 Wave 0 (one puku-cli session, main checkout)

1. `C:\11111\testimonial-saas`, branch `polish-final` created from `main` (`a3d90e8`, tag `pre-polish` exists).
2. puku-cli started in the repo root (CLAUDE.md loads there).
3. Tests MAY run here (this checkout has `.env.testing`): `--filter` while working, full suite at the end, exit 0.
4. Jahin reviews (dump + `git diff`), checks the guest pages and the app nav visually at 320 px and desktop, commits by explicit paths, then `git push -u origin polish-final` (so Emdash can branch from `origin/polish-final`).

### 5.3 Wave 1 (two Emdash tasks in parallel, puku-cli in each)

Setup per task (from `emdash-notebook.md`, VERIFIED unless marked):
- Create Task (Ctrl+N); names `polish-embed` and `polish-app-pages`; "Create new worktree", "Create new branch", **From branch `origin/polish-final`**; **Push branch to remote OFF**; Auto-approve OFF; leave the initial prompt empty.
- Terminal drawer (Ctrl+J), choose **PowerShell**, check the prompt ends with the worktree folder, type commands **line by line**, then `puku-cli`, trust the folder.
- Each agent: edits files only. **No tests, no migrations, no `serve`, no `npm`** (the worktree has no `.env`, `.env.testing`, `vendor`, `node_modules`; tests there could wipe the dev DB). The agent may WRITE tests; they run in Wave 2.
- DO NOT READ list includes the main checkout `C:\11111\testimonial-saas` (plus the usual list).
- `/export <name>.txt` lands in the worktree (UNTESTED): move it out before committing.
- Never click Publish (task card) or Share with team (project settings).
- Jahin reviews with `git -C "<worktree path>" diff` and commits inside the worktree by explicit paths. No push needed: worktree branches are visible to the main checkout.
- JAHIN: two puku sessions can run at the same time; puku runs inside Emdash. Whether puku loads `CLAUDE.md` from a worktree is UNVERIFIED, so every prompt carries its own rules.

### 5.4 Wave 2 (serial, main checkout)

1. `git switch polish-final`, merge Task A's branch, then Task B's branch (no conflicts expected: disjoint files).
2. `php artisan test; echo $LASTEXITCODE` once on the merged branch (last line `0`). Fix failures in the main checkout (one puku session, scoped prompt).
3. Restart `php artisan serve` and `npm run dev` from the main checkout; Jahin does the visual check (5.5).
4. Check the Favorites question (below, 7.2) before the recording.
5. Merge `polish-final` into `main`, push. README and reflection updates (handoff section 9): test count line; Emdash and the tools table if the tasks really ran there; reflection re-count (200-300 words).
6. Emdash cleanup: delete both tasks; Delete leaves the local branch (VERIFIED in the test), so remove it with `git branch -d`; check `git worktree list`, `git branch -a`, `git status --short`.

### 5.5 Visual check matrix (Jahin, Wave 2)

Each page at 320 px (DevTools Responsive) and at a maximized desktop window, Ctrl+0:

| Page | 320 | Desktop |
|---|---|---|
| `/` logged out and logged in | [ ] | [ ] |
| `/login`, `/register`, `/forgot-password` | [ ] | [ ] |
| `/s/beta-testers` (form, then the thank-you view) | [ ] | [ ] |
| `/privacy`, `/privacy/request-deletion` | [ ] | [ ] |
| `/dashboard` (Active and Deleted Spaces tabs; hamburger menu open at 320) | [ ] | [ ] |
| `/spaces` | [ ] | [ ] |
| `/inbox` and `/inbox?spaceId=3` | [ ] | [ ] |
| `/spaces/3/embed` (masonry, carousel, dark) | [ ] | [ ] |
| `file:///C:/11111/embed-test.html` (with DevTools Disable cache + Ctrl+Shift+R) | [ ] | [ ] |
| A 404 page | [ ] | [ ] |

Pass = no horizontal page scroll, nothing overlapping, wordmark visible, one button style, content aligned with the title.

---

## 6. Rules every master prompt must carry

(Standing preferences and the handoff's reporting rules apply in full; listed here because they bite in this polish.)

- Requirements, exact test assertions and scope boundaries; implementation left to the agent.
- **Keep every existing `data-testid`** on an element with the same meaning. New elements get page-prefixed test ids.
- **Do not change text a test asserts.** If a polish item needs it, STOP and report.
- Never loosen assertions; each new test has a "would fail if" note; no tests that cannot fail.
- No new remote resources (fonts, CDNs, images). Chart.js stays as it is.
- Hard rules: 2 (Free plan note exact, no upgrade CTA), 8-11 (embed API, clamp, whitelist, `textContent` only), 15 (behaviour deviation = all three docs in the same change). Pure visual changes are not deviations; #6, #12 and D4 links are UI hardening / navigation, so the agent checks the PRD, data model and build order for any contrary wording and STOPS if it finds one.
- No `git add`/commit/stash/restore/reset/push by the agent; no `migrate`, `db:seed`, tinker writes or `serve`; never touch `.env*`, `README.md`, `.env.example`.
- Report items in full; STOP means end the turn and report.

---

## 7. Deferred and open

### 7.1 Deferred (after the deadline)

#14 Inbox search; deletion-form wording ("Space slug", "Testimonial id" are developer words); the stale "What to look for" box in `C:\11111\embed-test.html` (outside the repo); everything in the handoff's "Later" list.

### 7.2 Open questions / UNVERIFIED

- **Favorites:** the Inbox shows Favorites (0) for Shiplog Reviews (ss), but the handoff recorded Priya Raman as "favourite" in the embed API check after the reseed. Unknown whether she was un-favourited during testing or the handoff label was wrong. Check in Wave 2, before the recording.
- **Landing redirect:** the handoff says the landing page redirects logged-in users; the 12 Oct screenshot shows the landing page rendered for a logged-in user with a "Dashboard" link (matches `landing.blade.php:31-32`). The handoff note looks wrong; logging out before the recording is still harmless.
- **#8 cause** and how the preview classes are wired: `embed.js` and `embed-builder.blade.php` not seen by Claude yet.
- **"Live" badge meaning (#26):** not seen in code.
- **Inbox views, profile views, Space form views, error pages, forgot/reset/verify pages:** not seen (code or screenshots).
- Emdash "From branch" listing a pushed `origin/polish-final`: expected (it listed `origin/main`), not tried.

---

## 8. Schedule

| When | What |
|---|---|
| Next session (Tue 13 Oct) | Step 0 checks; confirm D1-D7 and #17; save this file; create `polish-final`; Wave 0 prompt (ask first), run, review, commit, push |
| Tue 13 / Wed 14 Oct | Wave 1 Emdash setup; Task A and Task B prompts (ask first), run in parallel, review, commit |
| Wed 14 Oct | Wave 2 merge, tests, visual matrix, fixes, merge to `main`, README / reflection updates. **Cut-off: end of day** |
| Thu 15 Oct | Reseed + embed-test id; recording; reflection re-read; submit |

## 9. First steps of the next session

1. Step 0 checks (handoff section 10, step 0).
2. Jahin confirms or changes D1-D7 and names #17 (or drops it).
3. Save this file to `C:\11111\design.md`.
4. `git switch -c polish-final` in `C:\11111\testimonial-saas` (after `git log --oneline -1` still shows `a3d90e8`).
5. Claude gathers any missing Wave 0 files (auth pages other than login/register, `application-logo`, `dropdown-link`, `danger-button`, `responsive-nav-link`, error pages), then asks permission and writes the Wave 0 master prompt.
