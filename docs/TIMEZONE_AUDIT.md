# Timezone audit: Core application and MCP server

**Audit date:** 2026-02-24 (full pass)  
**Scope:** All timezone handling in core logic (controllers, services, repos, views) and MCP server (adapters, handlers, tool schemas).  
**Policy:** Store slot options in UTC; default input in user/poll timezone; display in user TZ when known → poll TZ → UTC; conversions explicit everywhere.

---

## Executive summary

| Area | Status | Notes |
|------|--------|------|
| **Storage** | OK | Poll options stored as UTC; no issues. |
| **Core input (web)** | OK | Options parsed as poll TZ, converted to UTC. |
| **Core display** | Fixed | Viewer TZ when known (view, results, final time label). |
| **Core outbound (Calendar, ICS)** | Fixed | Google Calendar and ICS parse/send UTC explicitly. |
| **MCP input (create_poll, close_poll)** | OK | Options and final_slot parsed as UTC / ISO8601 with zone. |
| **MCP output (get_poll, list_polls, find_availability, close_poll)** | OK | Options/formats use poll TZ or explicit UTC. |
| **Server-local datetimes** | Documented | sent_at, created_at, RateLimit rely on PHP/MySQL TZ alignment (README). |

No remaining **definite** timezone bugs in core or MCP. A few **documentation** and **edge-case** notes are below.

---

## Part A: Core application

### A.1 Storage and input (verified OK)

- **PollRepository:** `poll_options.start_utc`, `end_utc` stored as `Y-m-d H:i:s`; no timezone in column — app treats them as UTC everywhere. OK.
- **PollController::optionsPost:** Form `start`/`end` parsed with `new \DateTime($o['start'], $tz)` where `$tz = $poll->timezone`, then `setTimezone($utc)->format('Y-m-d H:i:s')`. Correct: input = poll TZ, storage = UTC.
- **PollService::createPoll:** Poll timezone from request; options added later via optionsPost or MCP. OK.
- **PollService::generateTimeOptions:** Builds in poll timezone, converts with `setTimezone(new \DateTimeZone('UTC'))` before storing. OK.

### A.2 Display (fixed)

- **PollController::view / resultsFragment:** `$displayTimezone` = `current_user()->timezone` → `$poll->timezone` → `'UTC'`. Passed to view and fragment.
- **views/polls/view.php:** Uses `$displayTz` for option times and lock option labels. Final time label built in controller with same `$displayTimezone`. OK.
- **views/polls/results_fragment.php:** Uses `$displayTz` for “Your saved votes” and results table. OK.
- **views/polls/options.php:** Shows times in **poll timezone** (organizer editing event times). By design; no change needed.

### A.3 Outbound (fixed)

- **GoogleCalendarService::createEvent:** `startUtc`/`endUtc` parsed with `new \DateTimeImmutable(..., new \DateTimeZone('UTC'))`, sent as ISO8601 with `timeZone: UTC`. Fixed (was strtotime/server TZ). OK.
- **GoogleCalendarService freebusy:** `timeMin`/`timeMax` and option overlap use explicit UTC. OK.
- **IcsGenerator::formatUtcIcs:** Parses with `new \DateTimeImmutable($mysqlUtc, new \DateTimeZone('UTC'))`, outputs `Ymd\THis\Z`. Fixed (was strtotime). OK.
- **PollService lock emails:** `formatLockedTime` uses recipient/organizer timezone; timezone callout in body. OK.

### A.4 Server-local and ambiguous (documented)

- **sent_at / created_at:** MySQL `DATETIME` with `NOW()`; displayed with `strtotime()` / `new DateTime($inv->sent_at)` (no zone). **Documented** in README: PHP default and MySQL session TZ should match.
- **DbHillmeetAdapter::getPoll** formats `created_at` as UTC for MCP; if DB is server-local, deploy with consistent TZ or document. README covers this.
- **RateLimit:** Uses `date('Y-m-d H:i:s', $windowStart)` and `NOW()`. PHP and MySQL must agree; low priority, documented by convention.
- **Misc:** `bin/mcp-create-key.php` and `public/deploy-check.php` use `date()` for labels/timestamps (server TZ). Acceptable for non–user-facing use.

### A.5 Core paths not involving user-facing times

- **AvailabilityService:** Option times and `prefer_times` parsed with `DateTimeZone('UTC')`. OK.
- **PollDetailsService:** Options loaded as UTC `DateTimeImmutable`. OK.
- **AuthController setTimezone:** Validates IANA timezone; no datetime parsing. OK.
- **UserRepository::setTimezone:** Stores string; validation via `new \DateTimeZone($timezone)`. OK.

---

## Part B: MCP server

### B.1 MCP input (tool arguments)

- **hillmeet_create_poll**
  - **options[].start:** Documented as “ISO8601 UTC”. Adapter uses `parseUtcDatetime($value)` → `new \DateTimeImmutable($value, new \DateTimeZone('UTC'))`. If client sends `Z` or offset, PHP parses correctly; if naive string, interpreted as UTC. OK.
  - **timezone:** Optional IANA; adapter `resolvePollTimezone` uses payload → organizer user TZ → UTC. OK.
- **hillmeet_close_poll**
  - **final_slot.start / end:** Documented as “ISO8601 UTC”. Adapter `findOptionByFinalSlot` uses `new \DateTimeImmutable($startStr, new \DateTimeZone('UTC'))` then formats to `Y-m-d H:i:s` for comparison. Naive string = UTC; ISO8601 with offset (e.g. from get_poll in poll TZ) parses to correct instant. OK.
- **hillmeet_find_availability**
  - **prefer_times[].start/end:** Documented “ISO8601 UTC”. AvailabilityService `normalizePreferTimes` uses `new \DateTimeImmutable($w['start'], new \DateTimeZone('UTC'))`. OK.

### B.2 MCP output (tool results)

- **hillmeet_create_poll:** Returns `timezone` (poll’s); `share_url` (with secret when new). No option times in response. OK.
- **hillmeet_get_poll:** Options `start`/`end` from `formatInPollTimezone($opt['start_utc'], $data->timezone)` — ISO8601 in **poll timezone**. Documented as “options (start/end in poll timezone)”. OK.
- **hillmeet_list_polls:** Each poll has `created_at` (DB string), `timezone`. No option times. `created_at` assumed UTC when displayed elsewhere; see A.4. OK.
- **hillmeet_find_availability:** `best_slots[].start/end` from `formatInPollTimezone(..., $poll->timezone)`. OK.
- **hillmeet_close_poll:** `final_slot` from adapter: same `formatInPollTimezone` (poll TZ). OK.

### B.3 MCP adapter (DbHillmeetAdapter)

- **createPoll:** Option start parsed via `parseUtcDatetime` (UTC); end = start + duration; stored in DB as UTC. OK.
- **closePoll / findOptionByFinalSlot:** final_slot start/end parsed with `DateTimeImmutable(..., UTC)`, compared to `opt->start_utc`/`end_utc`. OK.
- **getPoll:** Options formatted with `formatInPollTimezone($opt['start_utc'], $data->timezone)`; `created_at` with `DateTimeImmutable($data->created_at, UTC)->format('c')`. OK.
- **listPolls:** Returns `created_at` raw from DB; share_url logic unrelated to timezone. OK.
- **findAvailability:** Best slots formatted with `formatInPollTimezone(..., $poll->timezone)`. OK.
- **formatInPollTimezone:** Accepts UTC `DateTimeImmutable` and poll timezone string; falls back to UTC on invalid TZ. OK.
- **resolvePollTimezone:** Payload → user timezone → UTC. OK.

### B.4 MCP handlers and endpoint

- Handlers do not parse datetimes; they pass arguments to the adapter. No timezone bugs in handlers.
- **McpEndpoint.php:** `date('c')` for `hillmeet_ping` “time” — server local. Acceptable for “current server time” in a health check.

### B.5 MCP documentation (tool schemas)

- **create_poll:** “options … start (ISO8601 UTC)” — clear.
- **close_poll:** “final_slot … start and end, ISO8601 UTC” — clear. Note: clients may send ISO8601 with offset (e.g. from get_poll); adapter accepts both.
- **find_availability:** “prefer_times … ISO8601 UTC” — clear.
- **get_poll:** “options (start/end in poll timezone)” — accurate.

No schema changes required; one optional clarification: **final_slot** “ISO8601 UTC or with explicit offset (e.g. from get_poll); naive strings interpreted as UTC.”

---

## Part C: Remaining recommendations

1. **PHP/MySQL timezone:** Keep README “Timezone” note. Ensure `date_default_timezone_set` (bootstrap) and MySQL session/time_zone align (e.g. both UTC in production).
2. **RateLimit (optional):** If standardizing on UTC everywhere, use `gmdate` and store UTC in DB for rate_limit windows.
3. **share.php sent_at:** Still `(new DateTime($inv->sent_at))->format(...)` with no zone. Same as view.php: relies on PHP default = MySQL. Documented; optional future change: treat as UTC if you migrate to UTC-stored timestamps.
4. **MCP doc nuance:** In docs (e.g. MCP.md), you could state that `final_slot` accepts ISO8601 with or without offset; naive strings are treated as UTC.

---

## Part D: Checklist for future changes

- [ ] Any new code parsing a “UTC” string (`Y-m-d H:i:s` or similar) uses `new \DateTimeImmutable($str, new \DateTimeZone('UTC'))` (or equivalent), not `strtotime()` or `DateTime` without a zone.
- [ ] Any new display of poll/option times uses: viewer TZ if known → poll TZ → UTC.
- [ ] Any new outbound integration (calendar, email, ICS, third-party API) sends or formats UTC explicitly (or documents the chosen zone).
- [ ] New MCP tools that accept or return datetimes: document whether times are UTC or in a specific timezone (e.g. poll timezone) and parse/format accordingly in the adapter.

---

## Summary table: files touched by timezone logic

| File | Role | Status |
|------|------|--------|
| `src/Controllers/PollController.php` | Web option input (poll TZ→UTC); display TZ; final time label | OK / Fixed |
| `src/Controllers/AuthController.php` | setTimezone; calendar event create (passes UTC to service) | OK |
| `src/Services/GoogleCalendarService.php` | createEvent, freebusy (UTC explicit) | Fixed |
| `src/Services/IcsGenerator.php` | formatUtcIcs (UTC explicit) | Fixed |
| `src/Services/PollService.php` | generateTimeOptions (poll TZ→UTC); lock emails (recipient TZ) | OK |
| `src/Services/AvailabilityService.php` | Option and prefer_times in UTC | OK |
| `src/Services/PollDetailsService.php` | Options as UTC DateTimeImmutable | OK |
| `src/Adapter/DbHillmeetAdapter.php` | MCP: create/get/list/close/find; formatInPollTimezone; parseUtcDatetime; findOptionByFinalSlot | OK |
| `src/Repositories/PollRepository.php` | Storage only (UTC columns) | OK |
| `views/polls/view.php` | Display options/lock in $displayTz; sent_at (server TZ) | Fixed / Documented |
| `views/polls/results_fragment.php` | Display in $displayTz | Fixed |
| `views/polls/share.php` | sent_at display (server TZ) | Documented |
| `views/polls/options.php` | Poll TZ for organizer edit | OK by design |
| `src/Support/McpEndpoint.php` | Ping time; tool schemas (UTC / poll TZ documented) | OK |
| `src/Mcp/Handler/*` | No datetime parsing; delegate to adapter | OK |
| `src/Support/RateLimit.php` | Server TZ vs MySQL | Documented |
| `src/bootstrap.php` | date_default_timezone_set(config) | OK |
