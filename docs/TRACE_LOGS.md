# Request tracing for vote save / results

## Correlation

- The client generates a `trace_id` (UUID or timestamp-random) per vote submission.
- It sends `X-Trace-Id: <trace_id>` on the **saveVotes** POST and on the **results** GET that runs after save.
- Use the same `trace_id` in server logs to correlate one save + one results request.

## Log lines

### POST /poll/{slug}/vote-batch (saveVotes)

**On success:**
```
[Hillmeet vote-batch] trace_id=... session_id=... session_user_id=... resolved_user_id=... email=... poll_id=... invite_flow=invite|secret|direct user_id_written=... vote_rows_in_db=...
```

**On assertion failure (wrote votes but DB count 0):**
```
[Hillmeet vote-batch] IDENTITY MISMATCH OR WRITE FAILED trace_id=... session_id=... user_id=... email=... poll_id=... votes_expected=... db_count=0
```
Response: HTTP 500, `{"error":"Vote write verification failed. Please try again."}`

### GET /poll/{slug}/results (results fragment)

```
[Hillmeet results-fragment] trace_id=... session_id=... session_user_id=... resolved_user_id=... email=... poll_id=... invite_flow=... participant_user_ids=[1,2,3] total_vote_rows_poll=... vote_rows_for_user=...
```

## Comparing “bad” (first save) vs “good” (second save)

1. **Same trace_id**  
   Find one trace_id that appears in both vote-batch and results-fragment logs.

2. **vote-batch**  
   - `session_user_id` vs `resolved_user_id`: should match.  
   - `vote_rows_in_db`: after first save should be > 0 if any vote was submitted.  
   - If you see `IDENTITY MISMATCH OR WRITE FAILED`, the write did not persist for that user.

3. **results-fragment**  
   - `resolved_user_id` and `vote_rows_for_user`: should match the user and count you expect.  
   - `participant_user_ids`: should include `resolved_user_id` so the user has a column.  
   - If `vote_rows_for_user=0` but vote-batch for the same trace_id shows `vote_rows_in_db>0`, the results request may be using a different session/identity.

4. **DB check**  
   After a “bad” run, run:
   ```sql
   SELECT COUNT(*) FROM votes WHERE poll_id = ? AND user_id = ?;
   ```
   Compare with `vote_rows_in_db` (and `vote_rows_for_user` in results) for that trace_id.

## Minimal fix (if identity/write is correct)

If logs show the same `resolved_user_id` and non-zero vote rows in both vote-batch and results for the same trace_id, but the UI still shows “—” on first view:

- The first time the user opens Results they may be seeing **cached or pre-expand HTML**.  
- The client now **refetches results with the same trace_id and cache-busting (`_t=<trace_id>`) after every successful save**, and **refetches when the user expands Results**.  
- Ensure the results request after save uses the same session (cookies) and that no CDN/proxy caches the results response.
