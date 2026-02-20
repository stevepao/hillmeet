# Poll and time option deletion – verification steps

## Schema / cascade

No new migrations were added. Existing FKs already cascade:

- `poll_options.poll_id` → `polls.id` ON DELETE CASCADE
- `votes.poll_option_id` → `poll_options.id` ON DELETE CASCADE
- `votes.poll_id` → `polls.id` ON DELETE CASCADE
- `poll_participants.poll_id` → `polls.id` ON DELETE CASCADE
- `poll_invites.poll_id` → `polls.id` ON DELETE CASCADE
- `calendar_events.poll_id` / `poll_option_id` ON DELETE CASCADE

Poll delete clears `polls.locked_option_id` then deletes the poll; the DB then cascades to options, votes, participants, invites, calendar_events.

## Reproducible steps

### 1. Poll delete (Your polls list)

1. Log in, create a poll, add options, optionally invite and get votes.
2. On Home, in "Your polls", click **Delete** on a poll card.
3. Confirm modal: "Delete this poll? This removes all time options and all votes. This cannot be undone."
4. Click **Delete** → card disappears without full page reload.
5. In DB: poll row and all related rows (poll_options, votes, poll_participants, poll_invites, calendar_events for that poll) are gone.

### 2. Time option delete (Add time options page)

1. As owner, open a poll’s "Add times" (options) page with at least one saved option.
2. Click the **✕** (delete) button on a time option row.
3. Confirm modal: "Delete this time option? Votes for this option will be removed."
4. Click **Delete** → that row disappears; toast "Time option removed."
5. In DB: that `poll_options` row and all `votes` for that option_id are gone.

### 3. Stale poll (deleted poll)

1. User A opens a poll (e.g. copy URL).
2. User B (owner) deletes that poll from Home.
3. User A refreshes or reopens the poll URL.
4. Expected: 404 page with "This poll no longer exists." and "Go home" link.

### 4. Stale time option (vote form)

1. User A opens a poll and leaves the vote form open (do not submit yet).
2. Owner deletes one of the time options on the options page.
3. User A submits votes (including the deleted option id if it was still in the form).
4. Expected: server returns 400 with `error_code: "stale_options"` and message "One or more time options are no longer available. Please refresh and try again."; client shows the toast and reloads the page so the form shows current options.

### 5. Security

- Only the poll owner can call delete poll / delete option; server checks `isOrganizer`.
- Deleting another user’s poll or option returns 403.
- Locked option cannot be deleted (server returns error).
