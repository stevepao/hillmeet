# MVC / Code Quality Audit and Safe Refactor Plan

## PHASE 1: READ-ONLY AUDIT (no behavior changes)

### 1. Current Architecture

#### Routes (public/index.php)
- Single entry point; routes array by method (GET/POST); `{slug}` pattern for poll routes.
- Global CSRF check for all POSTs (CsrfMiddleware); 404/500 handling; controller instantiation per request (no container).

#### Controllers
- **PollController** (~947 LOC): 20+ actions. Owns poll resolution (secret / invite / direct), auth, validation, redirects, JSON responses, and heavy orchestration (lock: notifications, .ics, calendar).
- **AuthController**: login, Google OAuth, email PIN, signout, **setTimezone** (user preference).
- **HomeController**: index (owned + participated polls).
- **CalendarController**: settings, connect, callback, save.

#### Models
- **Poll**, **PollOption**, **User**: anemic DTOs + `fromRow()`; Poll has `isLocked()`, `isOrganizer()`.
- No domain logic; validation lives in controllers and PollService.

#### Repositories
- **PollRepository**, **VoteRepository**, **PollParticipantRepository**, **PollInviteRepository**, **UserRepository**, **CalendarEventRepository**, **OAuthConnectionRepository**, **GoogleCalendarSelectionRepository**, **FreebusyCacheRepository**, **EmailLoginPinRepository**.
- All DB access is in repositories (no raw SQL in controllers or views). PollRepository has `findBySlug`, `findBySlugAndVerifySecret`, `getOptions`, etc.

#### Services
- **PollService**: createPoll, addTimeOptions, generateTimeOptions, vote, voteBatch, lockPoll, deletePoll, deleteOption, sendInvites, resendInvite, removeInvite, getResults. Uses repos + RateLimit + AuditLog + EmailService.
- **AuthService**, **EmailService**, **GoogleCalendarService**, **IcsGenerator** (static).

#### Views / Templates
- **Layout**: `views/layouts/main.php` (header, nav, content, scripts).
- **Polls**: new, create_step1, edit, options, share, **view** (main poll page), **results_fragment** (partial).
- **Emails**: poll_invite, poll_locked, pin.
- **Auth**: login, email, verify. **Errors**: 404, 403.
- Views receive variables from controllers; no DB access. Some **logic in views**: date formatting (DateTime in view.php, results_fragment.php), `$finalTimeLabel` computation, `$voteLabels` / `$selectedLabel`, `$canEdit`, URL building (`$pollUrlWithSecret`, `$resultsExpandUrl`).

#### Client JS
- **app.js**: copy link, view toggle (list/grid), vote inline state (draft vs saved), vote-batch submit, results toggle (fetch results fragment), timezone ping (poll view), lock form (radio enable/disable, confirm dialog).
- **progressive.js**: (separate bundle).
- Config via `window.HILLMEET_POLL` (slug, secret, invite, urls, csrfToken, savedVotes, etc.) injected from view.

---

### 2. Inconsistencies and Duplication

#### 2.1 Poll resolution (high duplication)
The same “resolve poll by slug + secret / invite / direct (organizer or participant)” logic appears in **6 places** with small variations:
- **view()**: secret → invite (with markAccepted + add participant) → else direct (organizer or participant). Sets `$accessByInvite`.
- **vote()**: same 3 branches; builds `$backPath`; no markAccepted.
- **voteBatch()**: same 3 branches; JSON responses; no markAccepted.
- **resultsFragment()**: same 3 branches; HTML fragment response; no markAccepted.
- **lock()**: secret → invite → else **organizer only**; builds `$backUrl`.
- **checkAvailability()**: same 3 branches as view/voteBatch.

Variations: view() marks invite accepted and adds participant; lock() restricts to organizer; createEvent() only uses secret. Each copy instantiates PollInviteRepository, PollParticipantRepository, VoteRepository afresh.

#### 2.2 Back URL / redirect URL building
Built ad hoc in each action: `$backUrl`, `$backPath`, or inline `url('/poll/' . $slug . '?secret=' . ...)`. Slight differences (invite vs secret, optional query). No shared helper.

#### 2.3 Organizer-only checks
Repeated pattern: `$poll === null || !$poll->isOrganizer((int) current_user()->id)` in edit, options, optionsPost, share, sharePost, inviteResend, inviteRemove, deleteOption (and 404/403 handling). Sometimes 404 with message, sometimes 403, sometimes exit with no body.

#### 2.4 Business logic in templates
- **view.php**: Computes `$finalTimeLabel` (loop over options, match locked_option_id, format dates). Builds `$pollUrlWithSecret`, `$resultsExpandUrl`. Uses `$voteLabels`, `$selectedLabel` (vote → label).
- **results_fragment.php**: Date formatting per option (`DateTime` + timezone); `$hasAnyVotes` loop; score `$totals['yes']*2 + $totals['maybe']`; expects `$options`, `$results`, `$participants`, `$myVotes`, `$poll`, `$voteLabels`, `$resultsDebug`.
- **view.php** option loop: `$startLocal`, `$endLocal` per option; `$selectedLabel` from vote.

No SQL in views; logic is presentation-oriented but could be moved to controller or small view helpers for consistency.

#### 2.5 Mixed concerns in PollController
- **view()**: Resolves poll, loads options/votes/participants/results, freebusy, calendar, event created, invites, results debug, **renders results_fragment in try/catch**, then view. Auth + resolution + data loading + partial render + full render in one method.
- **lock()**: Resolves poll, validates option, calls PollService.lockPoll, then **in controller**: reload poll, find locked option, format time, get organizer, build .ics, get participants/invites, **per-recipient timezone + email send**, Google calendar create, redirect. Notification and calendar logic is in controller, not service.
- **createEvent()**: Resolves poll (secret only), finds locked option, gets participant emails, calls GoogleCalendarService, creates CalendarEventRepository row.

#### 2.6 Inconsistent error responses
- **JSON**: deletePoll/deleteOption use `error_code`; voteBatch uses `error` + optional `error_code: 'stale_options'`; checkAvailability uses `ok`, `error_code`, `error_message`, `action_hint`.
- **HTML**: 404 with `$pageMessage`; 403 with `$errorMessage` or raw 403; session flashes for validation errors (`poll_error`, `invite_error`, `lock_error`, `vote_error`).
- **Status codes**: 400 vs 403 vs 404 vs 409 used in different ways for “not found” vs “forbidden” vs “invalid input” vs “conflict (locked).”

#### 2.7 Repeated validation patterns
- Option id: `(int) ($_POST['option_id'] ?? 0); if ($optionId <= 0)` in lock, deleteOption, vote (single).
- Invite id: `(int) ($_POST['invite_id'] ?? 0); if ($inviteId <= 0)` in inviteResend, inviteRemove.
- Poll existence + organizer: repeated in many actions.

#### 2.8 Naming and file layout
- Consistent: Controllers/*, Repositories/*, Services/*, Models/*, views/*.
- Helpers: `e()`, `url()`, `config()`, `current_user()` in Hillmeet\Support; `require_auth()` in AuthController (global function). Mixed use of `\Hillmeet\Support\e` vs short names where imported.
- Views: some use `\Hillmeet\Support\e()`, `\Hillmeet\Support\Csrf::field()` fully qualified.

#### 2.9 Repository instantiation
Controllers and PollController create new repositories per action (e.g. `new PollInviteRepository()`, `new UserRepository()`). PollController constructor only injects PollRepository and PollService; PollService gets its repos in constructor. No DI container; no shared “poll context” object.

---

### 3. Key Flows → Where Code Lives

| Flow | Entry | Resolution | Business logic | Response / View |
|------|--------|-------------|----------------|------------------|
| **Deep-link invite** | GET /poll/{slug}?invite=TOKEN | PollController::view (invite branch) | InviteRepository::findByPollSlugAndTokenHash; markAccepted; PollParticipantRepository::add | view.php (same as secret/direct) |
| **Vote save (batch)** | POST /poll/{slug}/vote-batch | voteBatch() (secret / invite / direct) | PollService::voteBatch | JSON success/savedVotes or error |
| **Results render** | GET /poll/{slug}/results (fragment) or inline in view | resultsFragment() or view() | PollService::getResults; getResultsParticipants; getVotesForUser | results_fragment.php |
| **Lock time** | POST /poll/{slug}/lock | lock() (secret / invite / organizer) | PollService::lockPoll; then in controller: notifications, .ics, Google event | Redirect + session success |
| **Delete poll** | POST /poll/{slug}/delete (AJAX) | deletePoll() (findBySlug + organizer) | PollService::deletePoll | JSON success/error |
| **Delete time option** | POST /poll/{slug}/option-delete (AJAX) | deleteOption() (findBySlug + organizer) | PollService::deleteOption | JSON success/error |

---

### 4. Top 10 Highest-Risk Areas

1. **Poll resolution duplicated in 6+ actions**  
   Any fix (e.g. invite expiry, new access rule) must be applied in multiple places; easy to miss one and create security or UX bugs.

2. **Lock flow in controller**  
   Notifications, timezone per recipient, .ics, and calendar creation are in PollController::lock. Hard to unit test; any change to “after lock” behavior touches a large method.

3. **view() doing too much**  
   Resolves poll, loads many data sets, renders results fragment (with try/catch), then main view. Failure in one dependency can be hard to trace; refactors are risky.

4. **Inconsistent poll access for createEvent()**  
   Only supports secret; no invite or direct organizer. Diverges from view/lock and could confuse users who use invite link.

5. **Error response contract**  
   JSON shape and status codes differ between voteBatch, deletePoll, deleteOption, checkAvailability. Front-end and future clients must handle multiple patterns.

6. **Session flash keys**  
   Multiple keys (poll_error, invite_error, lock_error, vote_error, lock_success, invitations_sent). Easy to typo or overwrite; no single place that defines “all poll page flashes.”

7. **Results fragment loaded twice**  
   Once in view() (ob_start + require results_fragment) and again via AJAX (resultsFragment()). Logic and variables must stay in sync in two code paths.

8. **Options post normalization in controller**  
   optionsPost() parses and normalizes date options (DateTimeZone, format) in controller. If addTimeOptions or backend format changes, controller and service can drift.

9. **checkAvailability() size and branching**  
   Long method with resolution, validation, freebusy call, error map, debug logging, redirect vs JSON. Hard to test and change safely.

10. **No shared “authorized poll” abstraction**  
    No single type or helper representing “current user has access to this poll (by secret/invite/organizer/participant).” Leads to repeated conditionals and subtle differences between actions.

---

### 5. Incremental Refactor Plan (Behavior-Preserving)

- **Step 1** – Extract **poll resolution** into a single place (e.g. `PollAccess` service or controller private method) returning `?Poll` + context (access type, back URL). Replace the 6 call sites one by one; keep behavior and responses identical.
- **Step 2** – Extract **back URL** building into a small helper (slug, secret, invite token) and use it everywhere redirects to the poll page.
- **Step 3** – Move **post-lock notifications and calendar** from PollController::lock into PollService (e.g. `notifyLockedPoll` + optional calendar creation). Controller only calls lockPoll then notifyLockedPoll; same inputs/outputs (redirect, session).
- **Step 4** – Introduce a small **validation helper** (e.g. `requireOptionId()`, `requireInviteId()`) or reuse in a single “poll form input” validator to reduce duplication and standardize error responses.
- **Step 5** – Simplify **view()**: extract “view data” building into a dedicated method or small ViewModel builder; keep rendering in view.php; optionally move `$finalTimeLabel` (and similar) to controller so views stay dumb.
- **Step 6** – Unify **JSON error shape** for poll endpoints (e.g. always include `error` and optionally `error_code`), and document expected status codes. Change only response body shape where needed; do not change status codes in this step unless they’re wrong.
- **Step 7** – Add **smoke harness**: script or checklist that hits key endpoints and asserts status or key fields (no DB seeding required if using existing app state or manual steps).

---

## PHASE 2: SAFE REFACTOR PLAN (Small Steps Only)

### Principles
- One commit per refactor step; message format: `refactor(scope): description`.
- Each step touches a limited set of files (target ≤5 files, ≤200 LOC changed where possible).
- Smoke test checklist after each step; no API/route/DB schema changes unless explicitly called out.
- For risky steps, keep old and new paths behind a config flag until verified.

---

### Step 2.1 – Extract poll resolution into a shared helper (controller-only)

**Goal:** Single place for “resolve poll by slug + secret/invite/direct”; reuse in view, vote, voteBatch, resultsFragment, lock, checkAvailability.

**Approach:** Add a **private method** on PollController, e.g. `resolvePollForAccess(string $slug, string $secret, string $inviteToken, bool $requireOrganizer = false): ?array` returning `['poll' => Poll, 'back_url' => string, 'access_by_invite' => bool]` or null. Signature can be adjusted (e.g. accept request params from $_GET/$_POST). Do **not** change createEvent in this step (different contract).

**Files:** `src/Controllers/PollController.php` only.

**Behavior:** Identical: same 404/403 behavior and redirect URLs. Replace each of the 6 resolution blocks with a call to this method and use returned poll + back_url where applicable. For view(), keep invite flow side effect (markAccepted, add participant) inside the helper or in a single branch so behavior is unchanged.

**Smoke checklist:**
- [ ] Open poll by secret link → view loads.
- [ ] Open poll by invite link → view loads; invite marked accepted.
- [ ] Open poll as organizer (no secret) → view loads.
- [ ] Submit vote (batch) with secret → 200 and saved.
- [ ] Submit vote (batch) with invite → 200 and saved.
- [ ] Lock with secret → redirect and lock success.
- [ ] Lock as organizer (no secret) → redirect and lock success.
- [ ] Results fragment with secret → HTML fragment.
- [ ] Check availability with invite → 200 and busy data or error.

**Commit:** `refactor(controller): extract poll resolution to resolvePollForAccess`

**Risk:** Medium. If the extracted method misses a branch or side effect, invite or access can break. Mitigation: do one call site at a time and run smoke after each.

---

### Step 2.2 – Extract back-URL builder

**Goal:** One function that, given slug + secret + invite token, returns the poll page URL (with the right query param).

**Approach:** Add `poll_back_url(string $slug, string $secret, string $inviteToken): string` in Support (e.g. `helpers.php`) or as a static method. Use in PollController wherever `$backUrl` or `$backPath` or redirect to poll page is built.

**Files:** `src/Support/helpers.php`, `src/Controllers/PollController.php`.

**Smoke checklist:** Same as 2.1; ensure all redirects after lock/vote/invite still land on the correct URL (with or without secret/invite).

**Commit:** `refactor(controller): use shared poll_back_url helper`

---

### Step 2.3 – Move lock notifications + calendar into PollService

**Goal:** PollController::lock only: resolve poll, validate option_id, call service to lock, then call service to “notify and optionally create calendar event”; then redirect with session success.

**Approach:** Add `PollService::afterLockNotifyAndCalendar(Poll $poll, object $lockedOption, string $slug, int $currentUserId): void` (or similar). Move from controller: organizer/organizerTz, icsContent, participant/invite list, per-recipient timezone/email, Google event creation. Controller passes poll, locked option, slug, current user id; service uses existing repos and EmailService/IcsGenerator/GoogleCalendarService. No change to email content or .ics or API calls.

**Files:** `src/Services/PollService.php`, `src/Controllers/PollController.php`.

**Smoke checklist:**
- [ ] Lock a poll → redirect, success message, participants receive email with timezone callout.
- [ ] Lock with Google connected → calendar event created when applicable.
- [ ] Lock without Google → email with .ics attachment.

**Commit:** `refactor(service): move lock notifications and calendar to PollService`

**Risk:** Medium. Many dependencies (repos, EmailService, IcsGenerator, GoogleCalendarService). Consider moving in two sub-steps: (2.3a) notifications only, (2.3b) calendar creation.

---

### Step 2.4 – Shared validation helpers (optional)

**Goal:** Reduce repeated “option_id required”, “invite_id required”, “poll + organizer” checks.

**Approach:** Add small helpers, e.g. in a new `Support/PollRequest.php` or in controller: `requirePositiveInt(string $key, string $errorMessage): int` (returns value or exits with 400/JSON). Use only in PollController for option_id and invite_id. Do not change response format; keep same JSON keys and status codes.

**Files:** New small helper file or `Support/`, `src/Controllers/PollController.php`.

**Smoke checklist:** Delete option, remove invite, lock without option → same error responses as before.

**Commit:** `refactor(controller): use shared requirePositiveInt for option_id and invite_id`

---

### Step 2.5 – View data preparation (optional, small slice)

**Goal:** Move `$finalTimeLabel` (and optionally voteLabels) out of view into controller so view only receives precomputed values.

**Approach:** In PollController::view(), compute `$finalTimeLabel` and pass it to the view; remove the loop and DateTime from view.php. Optionally pass `$voteLabels` from controller (single source of truth).

**Files:** `views/polls/view.php`, `src/Controllers/PollController.php`.

**Smoke checklist:** Poll view with locked poll shows “Final time selected” banner; vote labels unchanged.

**Commit:** `refactor(view): move finalTimeLabel and voteLabels to controller`

---

### Step 2.6 – Smoke harness (script or checklist)

**Goal:** Documented, repeatable way to verify key flows after each refactor.

**Approach (minimal):**
- Add `docs/SMOKE_TEST.md` (or `tests/smoke/README.md`) with a **manual checklist**: e.g. “1. Log in. 2. Create poll. 3. Add options. 4. Open poll by secret. 5. Submit votes. 6. Lock poll. 7. Check email. 8. Delete option. 9. Delete poll.” and “Expected: 200/302, no 5xx, correct redirects.”
- Optional: add a **script** (e.g. `tests/smoke/smoke.sh` or `php tests/smoke/curl_endpoints.php`) that hits GET /, GET /auth/login, GET /poll/{slug} with query, POST vote-batch (with CSRF from a prior GET), and asserts HTTP status or presence of a string in body. Script can use env BASE_URL and optional COOKIE_JAR for auth.

**Files:** `docs/SMOKE_TEST.md`, optionally `tests/smoke/smoke.sh` or `tests/smoke/curl_endpoints.php`.

**Commit:** `chore(tests): add smoke test checklist and optional script`

---

### If a step requires API/behavior change

- **Stop** and document the tradeoff: what would change (response shape, status code, or route), why it’s desired, and what would break (e.g. existing front-end or bookmarks).
- Propose a **backward-compatible** alternative (e.g. add new header or query param to opt into new behavior, or deprecate old endpoint with a redirect) before changing behavior.

---

### Config flag for risky refactors

- If a step is deemed risky (e.g. full replacement of resolution logic in one go), introduce a config key, e.g. `app.use_central_poll_resolution = false`. Use it to choose “old path” vs “new path” in the controller. After smoke and staging verification, flip to true and remove the old path in a follow-up commit.

---

## Summary

- **Phase 1** is read-only: architecture described, duplication and risks listed, flows mapped, incremental plan suggested.
- **Phase 2** is small, stepwise refactors: extract poll resolution, back URL, lock notifications into service, optional validation and view-data moves, and add a smoke harness. Each step is scoped, has a smoke checklist, and a single-commit message. No large refactor; behavior and API preserved at every step.
