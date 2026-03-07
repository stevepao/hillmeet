# Timezone audit report

**Audit date:** 2026-02-24  
**Scope:** Store slot options in UTC; default input in user/poll timezone; display in user TZ when known, else poll TZ, else UTC; ensure conversions are explicit everywhere.

---

## Summary

- **Storage:** Poll option times are correctly stored as UTC (`poll_options.start_utc`, `end_utc`). No issues found.
- **Input (web):** Poll options from the web form are correctly interpreted as poll timezone and converted to UTC in `PollController::optionsPost`. Create step 1 defaults timezone to browser/user; options step uses poll timezone for manual/bulk entry. OK.
- **Input (MCP):** MCP `create_poll` option starts are parsed as UTC and stored. OK.
- **Outbound:** One **bug** (ICS) and one **fixed** (Google Calendar) where UTC strings were parsed with server timezone. One remaining bug in ICS. Display preference (user TZ when known) is **not** applied in several views.

---

## 1. Definite bugs (wrong time)

### 1.1 IcsGenerator — UTC parsed as server timezone

**File:** `src/Services/IcsGenerator.php`  
**Method:** `formatUtcIcs(string $mysqlUtc)`

**Issue:** Uses `strtotime($mysqlUtc)` on strings in `Y-m-d H:i:s` format. In PHP, a datetime string without timezone is interpreted in the **server default timezone**, not UTC. So the same bug as the one fixed in Google Calendar: UTC times are mis-parsed and the ICS attachment can show the wrong time (e.g. 11am → 3pm depending on server TZ).

**Fix:** Parse as UTC explicitly, e.g.:

```php
private static function formatUtcIcs(string $mysqlUtc): string
{
    $dt = new \DateTimeImmutable($mysqlUtc, new \DateTimeZone('UTC'));
    return $dt->format('Ymd\THis\Z');
}
```

---

## 2. Display preference not applied (user TZ when known)

**Your rule:** Display times in the **user’s timezone** when known; else **poll/event timezone**; else **UTC**. The app already stores the user’s timezone (e.g. from `/settings/timezone` and browser) but several places always use **poll timezone** and never the viewer’s.

### 2.1 Poll view — option times and lock labels

**Files:**  
- `views/polls/view.php` (lines 99–100, 239–240)  
- `views/polls/results_fragment.php` (lines 48, 81)

**Issue:** Option start/end and lock time are rendered with:

- `(new DateTime($opt->start_utc, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone($poll->timezone))->format(...)`

So **poll timezone** is always used. For a signed-in user we have `current_user()->timezone` (and we could pass a resolved display TZ from the controller). We never use it here.

**Fix:** Resolve display timezone once: `$displayTz = (current_user()->timezone ?? null) ?: $poll->timezone ?: 'UTC'`, then use `new DateTimeZone($displayTz)` for all option/lock formatting in these views. Same idea in the controller when building `$finalTimeLabel` (see below).

### 2.2 Final time label (lock) — controller

**File:** `src/Controllers/PollController.php` (around 424–427)

**Issue:** `$finalTimeLabel` is built using `$tz = new \DateTimeZone($poll->timezone)` only. The viewer may have a saved timezone; we don’t use it.

**Fix:** Resolve viewer timezone (e.g. `current_user()->timezone ?? $poll->timezone ?? 'UTC'`) and use that for `$finalTimeLabel` so the “Final time selected” line matches the rest of the display preference.

### 2.3 Options edit page

**File:** `views/polls/options.php` (lines 28–33)

**Issue:** Start/end are shown in **poll timezone** for the organizer. That’s consistent with “event timezone” and is acceptable; no change required unless you want organizer to always see their own TZ. Not a bug, just a note.

---

## 3. Ambiguous / server-dependent (recommendations)

### 3.1 sent_at / created_at (MySQL DATETIME)

**Status: Documented.** See README “Timezone” note: ensure PHP default timezone and MySQL session timezone match for correct display.

**Files:**  
- `views/polls/view.php` line 219: `date('M j, g:i A', strtotime($inv->sent_at))`  
- `views/polls/share.php` line 63: `(new DateTime($inv->sent_at))->format('M j, Y g:i A')`

**Issue:** `sent_at` (and similar `created_at`) are MySQL `DATETIME` set with `NOW()`. They have no timezone. Display uses `strtotime()` or `DateTime` without an explicit zone, so PHP uses the default timezone. If MySQL and PHP don’t share the same effective timezone (e.g. one UTC, one local), these labels can be wrong.

**Recommendation:** Either:

- Document that server and MySQL should use the same timezone for these columns, or  
- Store and treat them as UTC (e.g. set in app as UTC, and format with `DateTime(..., new DateTimeZone('UTC'))` then `setTimezone(user or poll TZ)` for display).

**Done:** Documented in README (Environment variables / Timezone).

### 3.2 get_poll / list_polls created_at

**Status: Documented.** MCP treats `created_at` as UTC when formatting; if MySQL stores server-local time, ensure server is UTC or document the assumption. See README Timezone note.

**File:** `src/Adapter/DbHillmeetAdapter.php` (getPoll, ~493)

**Issue:** `created_at` is formatted as:

- `(new \DateTimeImmutable($data->created_at, new \DateTimeZone('UTC')))->format('c')`

So we **assume** DB `created_at` is UTC. If MySQL stores server-local time, MCP `created_at` will be wrong.

**Recommendation:** Align with 3.1: decide whether `created_at` (and similar) are UTC or server-local and document/enforce it (e.g. UTC in app when writing, and same assumption when reading).

### 3.3 RateLimit

**File:** `src/Support/RateLimit.php` (lines 29–31)

**Issue:** Uses `date('Y-m-d H:i:s', $windowStart)` (PHP default TZ) and `NOW()` in MySQL. For correct windows, PHP and MySQL need to agree on time.

**Recommendation:** Low priority; ensure server TZ is consistent or use UTC for both (e.g. `gmdate` and MySQL in UTC).

---

## 4. Paths verified OK

- **PollController::optionsPost:** Input `start`/`end` parsed as poll timezone, converted to UTC before save. OK.
- **DbHillmeetAdapter::createPoll:** MCP option starts parsed as UTC (`parseUtcDatetime`), stored as UTC. OK.
- **PollService::generateTimeOptions:** Builds slots in poll timezone, converts to UTC for storage. OK.
- **GoogleCalendarService::createEvent:** After recent fix, start/end are parsed as UTC and sent to the API. OK.
- **GoogleCalendarService freebusy:** timeMin/timeMax and option timestamps use explicit UTC. OK.
- **PollService lock emails:** `formatLockedTime` uses recipient/organizer timezone; lock notification text is correct. OK.
- **AvailabilityService / PollDetailsService:** Option times from DB are parsed with `DateTimeZone('UTC')`. OK.
- **MCP adapter:** `formatInPollTimezone` takes UTC and poll timezone; used consistently for get_poll, list_polls, close_poll, find_availability. OK.

---

## 5. Recommended order of work

1. **Fix IcsGenerator** (1.1) — same class of bug as the calendar fix; small, clear change.  
2. **Apply display timezone preference** (2.1, 2.2) — use viewer timezone when known in poll view and final time label.  
3. **Document or normalize sent_at/created_at** (3.1, 3.2) — decide UTC vs server-local and make code/documentation consistent.  
4. **Optionally** tighten RateLimit (3.3) to UTC if you standardize on UTC everywhere.

---

## 6. Checklist (for future changes)

- [ ] Any new code that parses a “UTC” string (`Y-m-d H:i:s` or similar) uses `new \DateTimeImmutable($str, new \DateTimeZone('UTC'))` (or equivalent), not `strtotime()` or `DateTime` without a zone.
- [ ] Any new display of poll/option times uses: viewer TZ if known → poll TZ → UTC.
- [ ] Any new outbound integration (calendar, email, ICS) sends or formats UTC explicitly.
