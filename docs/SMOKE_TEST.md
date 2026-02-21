# Smoke Test Checklist

Use this after refactors or before release to verify key flows without breaking the app.

**Prerequisites:** App running (e.g. `php -S localhost:8080 -t public` or deployed URL). Optional: known poll slug + secret for deep links.

---

## Manual checklist

### Auth
- [ ] GET `/auth/login` → 200, login page.
- [ ] Sign in (email or Google) → redirect to `/` or return_to.
- [ ] GET `/auth/signout` → redirect to login or home.

### Home
- [ ] GET `/` (logged in) → 200, list of owned and participated polls.
- [ ] GET `/` (not logged in) → redirect to login.

### Poll creation (full flow)
- [ ] GET `/poll/create` → 200, create form.
- [ ] POST create (title, timezone, duration) → 302 to `/poll/{slug}/options`.
- [ ] Add time options → 302 to `/poll/{slug}/share`.
- [ ] Share page: copy link; send invites (optional) → invitations sent or no error.

### Poll view (access methods)
- [ ] Open poll by **secret** link: `GET /poll/{slug}?secret=...` → 200, options and vote controls.
- [ ] Open poll by **invite** link: `GET /poll/{slug}?invite=...` → 200, same; invite marked accepted.
- [ ] Open poll as **organizer** (no query): `GET /poll/{slug}` → 200 when logged in as owner.
- [ ] Open poll as **participant** (no query): `GET /poll/{slug}` → 200 when logged in and already participant/voter.

### Vote
- [ ] Submit **single** vote (Works/If needed/Can't) on one option → redirect back, vote saved.
- [ ] Change votes via **inline controls** and Submit votes → 200 JSON success, page reload or toast.
- [ ] After poll is locked, submit vote → 409 or message “finalized”; page reload shows read-only.

### Results
- [ ] Expand “Results” on poll view → fragment loads (inline or AJAX), table with participants and scores.
- [ ] GET `/poll/{slug}/results?secret=...` (or invite) → 200, HTML fragment.

### Lock
- [ ] As organizer, select one time (radio), click “Lock this time” → confirm dialog → 302, success message, “Final time selected” banner.
- [ ] Lock without selecting time → error “Please select a time to lock.”
- [ ] After lock: participants receive email with time and timezone callout; optional .ics; optional Google event if connected.

### Delete
- [ ] Delete **option** (options page or AJAX) → 200 JSON success; option removed from list.
- [ ] Delete **poll** (home or AJAX) → 200 JSON success; poll removed from list.

### Calendar / availability
- [ ] GET `/calendar` → 200 (connected or connect prompt).
- [ ] On poll view, “Check my availability” → 200 JSON with `busy` or error (e.g. not connected).

### Settings
- [ ] POST `/settings/timezone` with valid IANA timezone (auth + CSRF) → 204.

---

## Optional: scripted smoke (curl)

Run from repo root. Set `BASE_URL` (e.g. `http://localhost:8080`). Script only checks status codes and presence of expected strings; no DB or auth persistence.

```bash
BASE_URL="${BASE_URL:-http://localhost:8080}"

# Public pages
curl -s -o /dev/null -w "%{http_code}" "$BASE_URL/auth/login"   # expect 200
curl -s "$BASE_URL/" | grep -q "Sign in\|Hillmeet" && echo "OK" # expect OK

# Add more as needed, e.g.:
# curl -s -o /dev/null -w "%{http_code}" "$BASE_URL/poll/NOSUCH"  # expect 404
```

Extend with logged-in requests (cookie jar or session cookie) and POST vote-batch/lock if desired.
