# PollAccessService Refactor – Self-Audit Checklist

## 1) Every place pollRef → poll resolution still exists outside PollAccessService

| File | Function | Resolution used |
|------|----------|-----------------|
| **AuthController.php** | `verifyPage()` | `findBySlugAndVerifySecret($pending['slug'], $pendingSecret)`, `findBySlug($pending['slug'])` |
| **PollController.php** | `resolvePollForAccess()` | `findBySlugAndVerifySecret($slug, $secret)`, `findById((int)$invite->poll_id)`, `findBySlug($slug)` |
| **PollController.php** | `edit()` | `findBySlug($slug)` |
| **PollController.php** | `options()` | `findBySlug($slug)` |
| **PollController.php** | `share()` | `findBySlug($slug)` |
| **PollController.php** | `inviteResend()` | `findBySlug($slug)` |
| **PollController.php** | `inviteRemove()` | `findBySlug($slug)` |
| **PollController.php** | `deletePoll()` | `findBySlug($slug)` |
| **PollController.php** | `deleteOption()` | `findBySlug($slug)` |
| **PollController.php** | `vote()` | via `resolvePollForAccess()`; fallback `findBySlug($slug)` when resolved === null |
| **PollController.php** | `voteBatch()` | via `resolvePollForAccess()`; fallback `findBySlug($slug)` |
| **PollController.php** | `lock()` | via `resolvePollForAccess()`; later `findById($poll->id)` to refresh |
| **PollController.php** | `notifyLock()` | `findBySlugAndVerifySecret($slug, $secret)` |
| **PollDetailsService.php** | `getPollDetailsForOwner()` | `findBySlug($pollId)` |
| **PollRepository.php** | `generateSlug()` | `findBySlug($slug)` (existence check only, not user-supplied ref) |

**Excluded (not "pollRef" resolution):** PollRepository method definitions; PollAccessService (canonical); PollService/AvailabilityService/NonresponderService/DbHillmeetAdapter when they only use `findById($pollId)` with an already-resolved id.

---

## 2) Every place organizer ownership checks still exist outside PollAccessService

| File | Function | Check |
|------|----------|--------|
| **AuthController.php** | `verifyPage()` | `!$poll->isOrganizer((int) $_SESSION['user']->id)` |
| **PollController.php** | `resolvePollForAccess()` | `!$candidate->isOrganizer($userId)`, `$candidate->isOrganizer($userId) \|\| $isParticipant` |
| **PollController.php** | `edit()`, `options()`, `share()`, `inviteResend()`, `inviteRemove()`, `deletePoll()`, `deleteOption()` | `$poll === null \|\| !$poll->isOrganizer((int) current_user()->id)` |
| **PollController.php** | `lock()`, `createEvent()`, `notifyLock()`, `checkAvailability()`, `autoAcceptAvailability()` | `!$resolved['poll']->isOrganizer(...)` or `!$poll->isOrganizer($userId)` |
| **PollDetailsService.php** | `getPollDetailsForOwner()` | `$poll->organizer_id !== $userId` |
| **PollService.php** | `lockPoll()`, `sendInvites()`, `resendInvite()`, etc. | `!$poll->isOrganizer($organizerId)` (caller already resolved) |
| **AvailabilityService.php** | `computeBestSlots()` | `$poll->organizer_id !== $userId` (adapter uses PollAccessService first) |
| **NonresponderService.php** | `findNonrespondersForPoll()` | `$poll->organizer_id !== $userId` (adapter uses PollAccessService first) |

**Excluded:** PollAccessService (canonical); DbHillmeetAdapter (uses ctx only); Models/Poll (property/helper).

---

## 3) All MCP handlers + DbHillmeetAdapter methods now use PollAccessService

| Component | Method / handler | Uses PollAccessService? |
|-----------|------------------|--------------------------|
| DbHillmeetAdapter | `getPoll()` | **Yes** |
| DbHillmeetAdapter | `findAvailability()` | **Yes** |
| DbHillmeetAdapter | `listNonresponders()` | **Yes** |
| DbHillmeetAdapter | `closePoll()` | **Yes** |
| DbHillmeetAdapter | `listPolls()` | N/A (no poll ref) |
| DbHillmeetAdapter | `createPoll()` | N/A (no poll ref) |
| HillmeetGetPollRequestHandler | `handle()` | **Yes** (via adapter); catches PollForbidden → -32002 |
| HillmeetFindAvailabilityRequestHandler | `handle()` | **Yes** (via adapter) |
| HillmeetListNonrespondersRequestHandler | `handle()` | **Yes** (via adapter); catches PollForbidden → -32002 |
| HillmeetClosePollRequestHandler | `handle()` | **Yes** (via adapter); catches PollForbidden → -32002 |
| HillmeetListPollsRequestHandler | `handle()` | N/A |
| HillmeetCreatePollRequestHandler | `handle()` | N/A |

**Yes** – All poll-ref MCP paths go through PollAccessService.

---

## 4) Invitee rules implemented (invite email vs secret link vs token)

**Yes.** In `PollAccessService::resolveForInvitee()`:

1. **Secret link** – If `$secret` non-empty: resolve poll by ref, then `findBySlugAndVerifySecret($poll->slug, $secret)`. Valid → accessMode = SECRET_LINK (no invite; any email). Invalid → PollNotFound.
2. **Invite token** – Else if `$inviteToken` non-empty: `findByPollSlugAndTokenHash($poll->slug, hash('sha256', $inviteToken))`. Found → accessMode = INVITEE, `ctx->invite` set. Not found → PollNotFound.
3. **Invite by email** – Else: `findByPollIdAndEmail($poll->id, $inviteeEmail)`. Found → accessMode = INVITEE, `ctx->invite` set. Not found → PollNotFound.

---

## 5) Forbidden vs not-found to avoid poll enumeration

**Yes.**

- **Organizer:** Poll missing or owner missing → PollNotFound (404 / -32020). Poll exists but not owner → PollForbidden (403 / -32002).
- **Invitee:** All failures (poll missing, bad secret, bad token, no invite) → PollNotFound only. No PollForbidden, so “exists but forbidden” is not distinguishable from “not found”.

---

## 6) Unit/integration tests – organizer and invitee resolution

**Yes.** `tests/Services/PollAccessServiceTest.php`:

| Test | Scenario |
|------|----------|
| testOrganizerResolvesOwnPollBySlug | Organizer by email + slug → ctx isOrganizer, accessMode ORGANIZER, timezone, canLock, canClose, shareUrl |
| testOrganizerAccessOtherPollThrowsForbidden | Other email + slug → PollForbidden |
| testInviteeWithValidInviteByEmailResolves | Invitee by email only (no secret/token) → accessMode INVITEE, invite set, timezone, canViewResults |
| testInviteeWithValidSecretLinkResolves | Secret link → accessMode SECRET_LINK, invite null |
| testInviteeWithValidInviteTokenResolves | Invite token only (?invite=...) → accessMode INVITEE, invite set, timezone, canVote, canViewResults |
| testInviteeWithoutInviteOrSecretThrowsNotFound | No invite, no secret → PollNotFound |
| testEmailNormalization | Mixed-case owner email → resolves |
| testResolveForOrganizerByUserId | Organizer by userId + slug → isOrganizer, accessMode ORGANIZER, timezone |
| testOwnerNotFoundThrows | Nonexistent owner email → PollNotFound "Owner not found" |

**Yes.** `tests/Services/PollAccessServiceTest.php` – 9 tests covering organizer (own/other/owner missing/userId), invitee (by email, by secret link, **by invite token**, no access), and normalization.

---

## Summary

| # | Item | Result |
|---|------|--------|
| 1 | pollRef→poll resolution outside PollAccessService | **Yes** – Listed (AuthController, PollController 13 usages, PollDetailsService, PollRepository). |
| 2 | Organizer ownership checks outside PollAccessService | **Yes** – Listed (AuthController, PollController, PollDetailsService, PollService, AvailabilityService, NonresponderService). |
| 3 | MCP + DbHillmeetAdapter use PollAccessService | **Yes** – All poll-ref methods/handlers use it. |
| 4 | Invitee rules (email / secret / token) | **Yes** – Described. |
| 5 | Forbidden vs not-found / enumeration | **Yes** – Invitee path PollNotFound only; organizer uses PollForbidden. |
| 6 | Tests for organizer and invitee | **Yes** – 9 tests; invite-by-token scenario covered. |
