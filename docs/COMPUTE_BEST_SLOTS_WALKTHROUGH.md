# computeBestSlots() Step-by-Step Walkthrough

This document walks through `AvailabilityService::computeBestSlots()` using sample poll options and votes, and shows the final ranked list with scores.

---

## Sample data

**Poll options (stored in UTC; assume option IDs 101, 102, 103):**

| Option ID | start_utc           | end_utc             |
|-----------|---------------------|---------------------|
| 101       | 2026-03-01 14:00:00 | 2026-03-01 14:30:00 |
| 102       | 2026-03-01 15:00:00 | 2026-03-01 15:30:00 |
| 103       | 2026-03-01 16:00:00 | 2026-03-01 16:30:00 |

**Participants (cohort):** Alice (`alice@example.com`), Bob (`bob@example.com`), Carol (`carol@example.com`)  
→ All are “results participants” (they have voted or been invited). **total_invited = 3.**

**Vote matrix (option_id → user_id → vote):**

| Option | Alice | Bob   | Carol |
|--------|-------|-------|-------|
| 101    | yes   | maybe | no    |
| 102    | yes   | yes   | no    |
| 103    | no    | maybe | yes   |

**Constraints for this walkthrough:** none — `min_attendees`, `prefer_times`, and `exclude_emails` are empty.

---

## Step-by-step execution

### 1. Authorization

- Load poll by `$pollId`; verify `$poll->organizer_id === $userId`.
- If not organizer → return `[]`.  
- Here we assume the caller is the organizer.

### 2. Load options

- `$options = getOptions($pollId)` → list of 3 `PollOption` objects (101, 102, 103).

### 3. Exclude list and cohort

- `$excludeEmails = normalizeExcludeEmails([])` → `[]`.
- `$cohort = buildCohort($pollId, [])`:
  - Collect all participant emails + invited emails, normalized (lowercase).
  - Remove any in `$excludeEmails`.
- **Cohort:** `{ alice@example.com, bob@example.com, carol@example.com }` → **3 people.**

### 4. User ID → email map

- `$userIdToEmail = buildUserIdToEmail($pollId)` → e.g. `{ aliceId → 'alice@example.com', bobId → 'bob@example.com', carolId → 'carol@example.com' }`.

### 5. Vote matrix

- `$matrix = getMatrix($pollId)` → for each option_id, `user_id => 'yes'|'maybe'|'no'`.

### 6. Constraints

- `$minAttendees` = `null` (not set).
- `$preferTimes` = `[]` (empty).

### 7. Loop over each option and build slot + score

For each option we:

- **Available:** cohort members who voted **yes** or **maybe** for this option (and are still in cohort).
- **Unavailable:** cohort minus available.
- **total_invited:** `count($cohort)` = 3 (unchanged for all options).
- **Score:** `computeScore(availableCount, totalInvited, option, minAttendees, preferTimes)`.

---

#### Option 101 (14:00–14:30 UTC)

- **Votes:** Alice=yes, Bob=maybe, Carol=no.
- **Available (yes/maybe in cohort):** Alice, Bob → `available_emails = ['alice@example.com', 'bob@example.com']`, **available_count = 2**.
- **Unavailable:** Carol → `unavailable_emails = ['carol@example.com']`.
- **computeScore(2, 3, option101, null, []):**
  - `min_attendees` is null → no penalty.
  - `prefer_times` is empty → no boost.
  - **score = (float) availableCount = 2.0.**

**Slot 101:** `available_count=2`, `total_invited=3`, `score=2.0`.

---

#### Option 102 (15:00–15:30 UTC)

- **Votes:** Alice=yes, Bob=yes, Carol=no.
- **Available:** Alice, Bob → **available_count = 2**.
- **Unavailable:** Carol.
- **computeScore(2, 3, option102, null, []):** score = **2.0**.

**Slot 102:** `available_count=2`, `total_invited=3`, `score=2.0`.

---

#### Option 103 (16:00–16:30 UTC)

- **Votes:** Alice=no, Bob=maybe, Carol=yes.
- **Available:** Bob, Carol → **available_count = 2**.
- **Unavailable:** Alice.
- **computeScore(2, 3, option103, null, []):** score = **2.0**.

**Slot 103:** `available_count=2`, `total_invited=3`, `score=2.0`.

---

### 8. Sort by score descending

- `usort($slots, fn($a, $b) => $b['score'] <=> $a['score'])`.
- All three have **score = 2.0** → order is stable (implementation-dependent; typically insertion order: 101, 102, 103).

---

## Final ranked list (no constraints)

| Rank | Option ID | start_utc           | end_utc             | available_count | total_invited | score | available_emails     | unavailable_emails   |
|------|-----------|---------------------|---------------------|-----------------|---------------|-------|----------------------|----------------------|
| 1    | 101       | 2026-03-01 14:00:00 | 2026-03-01 14:30:00 | 2               | 3              | 2.0   | alice, bob           | carol                |
| 2    | 102       | 2026-03-01 15:00:00 | 2026-03-01 15:30:00 | 2               | 3              | 2.0   | alice, bob           | carol                |
| 3    | 103       | 2026-03-01 16:00:00 | 2026-03-01 16:30:00 | 2               | 3              | 2.0   | bob, carol           | alice                |

---

## Example with constraints

### Same data + `min_attendees = 3`

- For every option, `available_count (2) < min_attendees (3)`.
- **computeScore** uses: `MIN_ATTENDEES_FAIL_SCORE + (availableCount * 0.01)` = **-1000 + 0.02 = -999.98** (conceptually; exact tiebreak per option).
  - Option 101: -1000 + 0.02 = **-999.98**
  - Option 102: -1000 + 0.02 = **-999.98**
  - Option 103: -1000 + 0.02 = **-999.98**
- All three are demoted (negative score) and still returned, sorted by score descending (all equal, so order as above).

### Same data + `prefer_times = [{ start: '2026-03-01T15:00:00Z', end: '2026-03-01T15:30:00Z' }]`

- Only option **102** (15:00–15:30 UTC) overlaps the window: `optionOverlapsWindow(15:00, 15:30, 15:00, 15:30)` → true (strict: optStart < winEnd && optEnd > winStart).
- Option 102 gets **PREFER_TIMES_BOOST = 100.0** → **score = 2 + 100 = 102.0**.
- Options 101 and 103 stay at **2.0**.

**Ranked list:**

| Rank | Option ID | score   |
|------|-----------|---------|
| 1    | 102       | 102.0   |
| 2    | 101       | 2.0     |
| 3    | 103       | 2.0     |

---

## Summary

- **Cohort** = participants + invitees (normalized emails), minus `exclude_emails`; **total_invited** = cohort size.
- **Available** = cohort members who voted **yes** or **maybe** for that option; **unavailable** = cohort \ available.
- **Base score** = `available_count` (float).
- **min_attendees:** if set and `available_count < min_attendees` → score = **-1000 + (available_count × 0.01)** (demoted, not dropped).
- **prefer_times:** if the option’s UTC interval overlaps any preferred window → add **+100** to score (once per slot).
- **Sort:** by `score` descending; ties keep stable order.
