# Manual cURL tests: find_availability, list_nonresponders, close_poll

Use these steps to test the three MCP tools end-to-end. Replace `YOUR_API_KEY`, `YOUR_BASE_URL`, and (after step 1) `YOUR_SESSION_UUID` and `POLL_SLUG` with real values.

**Prerequisite: API key**

```bash
# Create a tenant API key (owner_email must exist in DB)
# php bin/mcp-create-key.php your-owner@example.com
# Copy the printed API key; use it as YOUR_API_KEY below.
```

Set base URL and key (local or production):

```bash
export BASE_URL="http://localhost:8080"   # or https://meet.hillwork.net
export API_KEY="YOUR_API_KEY"
```

---

## Step 1: Initialize session

**Do not** send `Mcp-Session-Id`. Capture the session UUID from the response **headers** (e.g. `Mcp-Session-Id: <uuid>`).

```bash
curl -s -i -X POST "${BASE_URL}/mcp/v1" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer ${API_KEY}" \
  -d '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2024-11-05","capabilities":{},"clientInfo":{"name":"curl-test","version":"1.0.0"}}}'
```

From the output, copy the value of **Mcp-Session-Id** (the header line). Then:

```bash
export SESSION="627a362b-02c7-476f-84d2-1e88662403ae"
```

Optional (if you have `jq` and a way to get headers into a variable):

```bash
RESP=$(curl -s -D - -X POST "${BASE_URL}/mcp/v1" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer ${API_KEY}" \
  -d '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2024-11-05","capabilities":{},"clientInfo":{"name":"curl-test","version":"1.0.0"}}}')
export SESSION=$(echo "$RESP" | grep -i '^Mcp-Session-Id:' | sed 's/Mcp-Session-Id: *//i' | tr -d '\r')
echo "Session: $SESSION"
```

---

## Step 2: Create a poll

Creates a poll with three time options and two participants. You will use the returned **poll_id** (slug) and **share_url** in later steps.

```bash
curl -s -X POST "${BASE_URL}/mcp/v1" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer ${API_KEY}" \
  -H "Mcp-Session-Id: ${SESSION}" \
  -d '{
    "jsonrpc": "2.0",
    "id": 2,
    "method": "tools/call",
    "params": {
      "name": "hillmeet_create_poll",
      "arguments": {
        "title": "Team standup",
        "duration_minutes": 30,
        "options": [
          {"start": "2026-03-10T14:00:00Z", "end": "2026-03-10T14:30:00Z"},
          {"start": "2026-03-10T15:00:00Z", "end": "2026-03-10T15:30:00Z"},
          {"start": "2026-03-10T16:00:00Z", "end": "2026-03-10T16:30:00Z"}
        ],
        "participants": [
          {"contact": "spao@spao.net"},
          {"contact": "auctions@spao.net"}
        ]
      }
    }
  }'
```

From the JSON response, copy **poll_id** (the slug, e.g. `abc123xyz`) or **share_url**. The server accepts either the short slug or the full share URL for `poll_id`.

```bash
export POLL_SLUG="8tjrm6pnq43p"
# Or use the full share URL; the server will extract the slug from the path:
# export POLL_SLUG="https://meet.hillwork.net/poll/8tjrm6pnq43p"
```

Optional (with `jq`):

```bash
# If the response is in a file or you pipe the curl output:
# export POLL_SLUG=$(curl -s ... | jq -r '.result.structuredContent.poll_id // .result.content[0].text | fromjson? | .poll_id // empty')
```

---

## Step 3: Manual – have one participant respond

To test **list_nonresponders** (one responded, one not) and **find_availability** (votes affect availability):

1. Open **share_url** from step 2 in a browser (e.g. `https://meet.hillwork.net/poll/POLL_SLUG`).
2. Sign in or use the invite link for **alice@example.com** (ensure that user exists; create via the app or DB if needed).
3. Submit votes for Alice (e.g. “yes” on the first option, “no” on others).
4. Do **not** respond as Bob — leave Bob as a non-responder.

If you skip this step, **list_nonresponders** will list both participants; **find_availability** will show 0 available for all options.

---

## Step 4: Find availability

Uses the poll slug. Optional args: `min_attendees`, `prefer_times`, `exclude_emails`. Times in the response are in the poll’s timezone (e.g. with offset, not plain `Z`).

```bash
curl -s -X POST "${BASE_URL}/mcp/v1" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer ${API_KEY}" \
  -H "Mcp-Session-Id: ${SESSION}" \
  -d "{
    \"jsonrpc\": \"2.0\",
    \"id\": 3,
    \"method\": \"tools/call\",
    \"params\": {
      \"name\": \"hillmeet_find_availability\",
      \"arguments\": {
        \"poll_id\": \"${POLL_SLUG}\"
      }
    }
  }"
```

With constraints (optional):

```bash
curl -s -X POST "${BASE_URL}/mcp/v1" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer ${API_KEY}" \
  -H "Mcp-Session-Id: ${SESSION}" \
  -d "{
    \"jsonrpc\": \"2.0\",
    \"id\": 3,
    \"method\": \"tools/call\",
    \"params\": {
      \"name\": \"hillmeet_find_availability\",
      \"arguments\": {
        \"poll_id\": \"${POLL_SLUG}\",
        \"min_attendees\": 1,
        \"prefer_times\": [{\"start\": \"2026-03-01T15:00:00Z\", \"end\": \"2026-03-01T15:30:00Z\"}]
      }
    }
  }"
```

Expected: `result.structuredContent` (or content text) with `best_slots`, `summary`, `share_url`. Slots have `start`/`end` in poll timezone (e.g. `-08:00`), `available_count`, `available_emails`, `unavailable_emails`.

---

## Step 5: List non-responders

Returns participants who have not voted (e.g. Bob if only Alice voted).

```bash
curl -s -X POST "${BASE_URL}/mcp/v1" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer ${API_KEY}" \
  -H "Mcp-Session-Id: ${SESSION}" \
  -d "{
    \"jsonrpc\": \"2.0\",
    \"id\": 4,
    \"method\": \"tools/call\",
    \"params\": {
      \"name\": \"hillmeet_list_nonresponders\",
      \"arguments\": {
        \"poll_id\": \"${POLL_SLUG}\"
      }
    }
  }"
```

Expected: `result.structuredContent` with `nonresponders` (array of `{email, name?}`) and `summary` (e.g. “1 person(s) haven't responded yet: bob@example.com.”).

---

## Step 6: Close poll

Closes the poll and can set the final time. **final_slot** must match one of the poll’s options (same start/end in UTC). Use the same times as in step 2 (e.g. first option: `2026-03-01T14:00:00Z` / `2026-03-01T14:30:00Z`).

```bash
curl -s -X POST "${BASE_URL}/mcp/v1" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer ${API_KEY}" \
  -H "Mcp-Session-Id: ${SESSION}" \
  -d "{
    \"jsonrpc\": \"2.0\",
    \"id\": 5,
    \"method\": \"tools/call\",
    \"params\": {
      \"name\": \"hillmeet_close_poll\",
      \"arguments\": {
        \"poll_id\": \"${POLL_SLUG}\",
        \"final_slot\": {
          \"start\": \"2026-03-01T14:00:00Z\",
          \"end\": \"2026-03-01T14:30:00Z\"
        },
        \"notify\": false
      }
    }
  }"
```

Expected: `result.structuredContent` with `closed: true`, `final_slot` (start/end in poll timezone), `summary`. If `notify: true`, you may also see `notified` and `calendar_event_created`.

**Idempotency:** Run the same close_poll again with the same **final_slot**; it should return success again without changing state. If you change **final_slot** to a different option after the poll is already closed, you should get a JSON-RPC error with code **-32030** (conflict).

---

## Validation / error examples

**Missing poll_id (list_nonresponders):**

```bash
curl -s -X POST "${BASE_URL}/mcp/v1" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer ${API_KEY}" \
  -H "Mcp-Session-Id: ${SESSION}" \
  -d '{"jsonrpc":"2.0","id":10,"method":"tools/call","params":{"name":"hillmeet_list_nonresponders","arguments":{}}}'
```

Expected: `error.code` **-32010** (validation), message “Validation error”, `data` with field `poll_id`.

**Wrong poll slug (not found):**

```bash
curl -s -X POST "${BASE_URL}/mcp/v1" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer ${API_KEY}" \
  -H "Mcp-Session-Id: ${SESSION}" \
  -d '{"jsonrpc":"2.0","id":11,"method":"tools/call","params":{"name":"hillmeet_list_nonresponders","arguments":{"poll_id":"nonexistent-slug-xyz"}}}'
```

Expected: `error.code` **-32020** (not found), e.g. “Poll not found or access denied.”

---

## One-shot script (optional)

Save as `scripts/mcp-curl-test.sh`, make executable, set `BASE_URL` and `API_KEY`, then run. It will initialize, create a poll, print the slug and share URL, then run find_availability, list_nonresponders, and close_poll. You still need to do step 3 (vote as one participant) in the browser before re-running the script for list_nonresponders/find_availability, or run the script twice (once before and once after voting).

```bash
#!/usr/bin/env bash
set -e
BASE_URL="${BASE_URL:-http://localhost:8080}"
API_KEY="${API_KEY:?Set API_KEY}"

# Initialize
RESP=$(curl -s -D - -X POST "${BASE_URL}/mcp/v1" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer ${API_KEY}" \
  -d '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2024-11-05","capabilities":{},"clientInfo":{"name":"curl-test","version":"1.0.0"}}}')
SESSION=$(echo "$RESP" | grep -i '^Mcp-Session-Id:' | sed 's/Mcp-Session-Id: *//i' | tr -d '\r')
BODY=$(echo "$RESP" | sed '1,/^$/d')
echo "Session: $SESSION"

# Create poll
CREATE=$(curl -s -X POST "${BASE_URL}/mcp/v1" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer ${API_KEY}" \
  -H "Mcp-Session-Id: ${SESSION}" \
  -d '{"jsonrpc":"2.0","id":2,"method":"tools/call","params":{"name":"hillmeet_create_poll","arguments":{"title":"Team standup","duration_minutes":30,"options":[{"start":"2026-03-01T14:00:00Z","end":"2026-03-01T14:30:00Z"},{"start":"2026-03-01T15:00:00Z","end":"2026-03-01T15:30:00Z"},{"start":"2026-03-01T16:00:00Z","end":"2026-03-01T16:30:00Z"}],"participants":[{"contact":"alice@example.com"},{"contact":"bob@example.com"}]}}}')
echo "Create poll: $CREATE"
POLL_SLUG=$(echo "$CREATE" | jq -r '.result.structuredContent.poll_id // .result.content[0].text | if type == "string" then . | fromjson? | .poll_id // empty else empty end // empty')
if [ -z "$POLL_SLUG" ]; then POLL_SLUG=$(echo "$CREATE" | jq -r '.result.content[0].text' | jq -r '.poll_id'); fi
echo "POLL_SLUG=$POLL_SLUG"
echo "Share URL: $(echo "$CREATE" | jq -r '.result.structuredContent.share_url // .result.content[0].text | if type == "string" then . | fromjson? | .share_url // empty else empty end // empty')"
echo "--- Now vote as one participant in the browser, then run find_availability and list_nonresponders ---"

# Find availability
curl -s -X POST "${BASE_URL}/mcp/v1" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer ${API_KEY}" \
  -H "Mcp-Session-Id: ${SESSION}" \
  -d "{\"jsonrpc\":\"2.0\",\"id\":3,\"method\":\"tools/call\",\"params\":{\"name\":\"hillmeet_find_availability\",\"arguments\":{\"poll_id\":\"${POLL_SLUG}\"}}}" | jq .

# List non-responders
curl -s -X POST "${BASE_URL}/mcp/v1" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer ${API_KEY}" \
  -H "Mcp-Session-Id: ${SESSION}" \
  -d "{\"jsonrpc\":\"2.0\",\"id\":4,\"method\":\"tools/call\",\"params\":{\"name\":\"hillmeet_list_nonresponders\",\"arguments\":{\"poll_id\":\"${POLL_SLUG}\"}}}" | jq .

# Close poll
curl -s -X POST "${BASE_URL}/mcp/v1" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer ${API_KEY}" \
  -H "Mcp-Session-Id: ${SESSION}" \
  -d "{\"jsonrpc\":\"2.0\",\"id\":5,\"method\":\"tools/call\",\"params\":{\"name\":\"hillmeet_close_poll\",\"arguments\":{\"poll_id\":\"${POLL_SLUG}\",\"final_slot\":{\"start\":\"2026-03-01T14:00:00Z\",\"end\":\"2026-03-01T14:30:00Z\"},\"notify\":false}}}" | jq .
```
