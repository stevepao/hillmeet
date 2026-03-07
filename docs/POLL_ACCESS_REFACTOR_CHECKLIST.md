# PollAccessService Refactor – Checklist and TODOs

## Refactored call sites (checklist)

| File | Method / change |
|------|------------------|
| `src/Adapter/DbHillmeetAdapter.php` | Constructor: added `PollAccessService` dependency. `getPoll()`: uses `resolveForOrganizerByEmail`, then `PollDetailsService` + `ctx->shareUrl`. |
| `src/Adapter/DbHillmeetAdapter.php` | `findAvailability()`: uses `resolveForOrganizerByEmail`, catches `PollNotFound`/`PollForbidden`, uses `ctx->poll`, `ctx->timezone`, `ctx->shareUrl`. |
| `src/Adapter/DbHillmeetAdapter.php` | `listNonresponders()`: uses `resolveForOrganizerByEmail`, then `NonresponderService` with `ctx->pollId` / `ctx->poll->organizer_id`. |
| `src/Adapter/DbHillmeetAdapter.php` | `closePoll()`: uses `resolveForOrganizerByEmail`, then existing close logic with `ctx->poll`, `ctx->poll->organizer_id`. |
| `src/Mcp/Handler/HillmeetGetPollRequestHandler.php` | Added `PollForbidden` catch → MCP code -32002. |
| `src/Mcp/Handler/HillmeetListNonrespondersRequestHandler.php` | Added `PollForbidden` catch → MCP code -32002. |
| `src/Mcp/Handler/HillmeetClosePollRequestHandler.php` | Added `PollForbidden` catch → MCP code -32002. |
| `src/Support/McpEndpoint.php` | Instantiates `PollAccessService` and passes it into `DbHillmeetAdapter`. |
| `src/Controllers/PollController.php` | Constructor: builds `PollAccessService`. `view()`: uses `resolveForOrganizerByUserId` then `resolveForInvitee` on failure; marks invite accepted when `ctx->invite` set; uses `ctx->poll`, `ctx->pollId`. |
| `tests/Adapter/*.php` (5 files) | Constructor: added `PollAccessService` argument. |
| `tests/Integration/McpToolsIntegrationTest.php` | Adapter construction: added `PollAccessService` argument. |

## New files

- `src/Support/PollRef.php` – PollRef value object, `PollRef::parse()`
- `src/Support/AccessMode.php` – `AccessMode` enum (organizer, invitee, secret-link, public)
- `src/Support/Actor.php` – `OrganizerActor`, `InviteeActor` value objects
- `src/Support/PollContext.php` – PollContext readonly DTO
- `src/Exception/PollNotFound.php` – extends `HillmeetNotFound` (404 / -32020)
- `src/Exception/PollForbidden.php` – new (403 / -32002)
- `src/Exception/PollValidationError.php` – extends `HillmeetValidationError` (400 / -32010)
- `src/Services/PollAccessService.php` – `resolveForOrganizerByEmail`, `resolveForOrganizerByUserId`, `resolveForInvitee`
- `tests/Services/PollAccessServiceTest.php` – organizer/invitee/secret-link/not-found/normalization tests

## Modified (non-call-site)

- `src/Repositories/PollInviteRepository.php` – added `findByPollIdAndEmail()` for invitee-by-email resolution.

## Remaining places (TODOs – poll resolution / access still outside PollAccessService)

| File | Notes |
|------|--------|
| `src/Controllers/PollController.php` | `resolvePollForAccess()` still used by: `vote()`, `voteBatch()`, `resultsFragment()`, `lock()`, `checkAvailability()`, `createEvent()`, `notifyLock()`, `autoAcceptAvailability()`, `edit()`, `options()`, `share()`, `sharePost()`, `inviteResend()`, `inviteRemove()`, `deletePoll()`, `deleteOption()`. Many use `findBySlug()` + organizer/participant checks directly. |
| `src/Controllers/AuthController.php` | `verifyPage()`: uses `findBySlugAndVerifySecret()` and `findBySlug()` for pending poll after auth. |
| `src/Services/PollDetailsService.php` | `getPollDetailsForOwner()`: uses `findBySlug()` + organizer_id check (called by adapter after PollAccessService; could accept poll id from context later). |

No DB schema changes; only new repository method added.

## MCP tests

- `./vendor/bin/phpunit tests/Mcp/ tests/Adapter/ tests/Services/PollAccessServiceTest.php` was run successfully.
- All run tests passed; some tests are skipped when config/DB are not present.

## Error mapping

- **PollNotFound** → HTTP 404, MCP -32020.
- **PollForbidden** → HTTP 403, MCP -32002 (new in GetPoll, ListNonresponders, ClosePoll handlers).
- **PollValidationError** / **HillmeetValidationError** → HTTP 400, MCP -32010.
- Invitee unauthorized is surfaced as **PollNotFound** to avoid enumeration.
