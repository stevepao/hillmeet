# MCP endpoint (v1)

Hillmeet exposes an [MCP](https://modelcontextprotocol.io/) (Model Context Protocol) server at `/mcp/v1` for tool-calling integrations. The endpoint is **POST-only**, returns **JSON**, and does not use SSE or long-running connections (Apache/PHP-FPM friendly).

## Authentication

All requests require a valid API key via the `Authorization: Bearer <api_key>` header. Invalid or missing auth returns **401** with a JSON-RPC error:

```json
{"jsonrpc":"2.0","error":{"code":-32001,"message":"Unauthorized"}}
```

Create keys with `php bin/mcp-create-key.php [owner_email]`.

## Base URL

- Production: `https://meet.hillwork.net/mcp/v1`
- Local: `http://localhost:8080/mcp/v1` (or your `APP_URL` + `/mcp/v1`)

Use a trailing slash if your server requires it: `/mcp/v1/`.

## Protocol

The endpoint speaks standard MCP JSON-RPC over HTTP:

- **initialize** — handshake; returns server info and capabilities.
- **tools/list** — list available tools (e.g. `hillmeet_ping`).
- **tools/call** — invoke a tool by name with optional `arguments`.

All responses preserve the JSON-RPC **id** from the request (request_id propagation). Tool calls are audit-logged (tenant_id, tool name, duration_ms, ok/error, request_id). JSON-RPC errors use standard codes (e.g. -32601 method not found, -32602 invalid params, -32001 unauthorized).

All requests must be `POST` with `Content-Type: application/json`. The transport supports the `Mcp-Session-Id` header for session affinity when the client sends it.

## Session flow

The MCP transport **requires a session** for every request except the first:

1. **First request** must be `initialize` — do **not** send `Mcp-Session-Id`. The response body is JSON and the response **headers** include `Mcp-Session-Id: <uuid>`.
2. **All later requests** (e.g. `tools/list`, `tools/call`) must send that same UUID in the header: `Mcp-Session-Id: <uuid>`.

If you call `tools/list` or `tools/call` without having called `initialize` first (or without sending the session id), you get:

```json
{"jsonrpc":"2.0","id":"","error":{"code":-32600,"message":"A valid session id is REQUIRED for non-initialize requests."}}
```

So in practice: call `initialize` once, capture `Mcp-Session-Id` from the response headers, then pass it on every subsequent request.

## Tools overview (for AI / tool-calling clients)

Each tool returns a `summary` (and often `share_url`, `poll_id`, or structured data) suitable for natural-language replies. Typical flow:

| Tool | When to use | Returns |
|------|-------------|--------|
| **hillmeet_ping** | Verify API key and connectivity. | `ok`, `service`, `time`. |
| **hillmeet_create_poll** | User wants to schedule a meeting; you have title, duration, time options (start only), and optional participants. | `poll_id`, `share_url`, `summary`, `timezone`. **Next:** Share `share_url` with participants so they can vote. |
| **hillmeet_list_polls** | List the user's polls or get a `poll_id` for other tools. | `polls` (each with `poll_id`, `title`, `status`, `share_url`), `summary`. |
| **hillmeet_get_poll** | Fetch full poll details (options, participants, status). | Full poll object; options include `start`/`end` in poll timezone. |
| **hillmeet_find_availability** | After participants have voted; find which time(s) work best. | `best_slots` (start, end, available_count, total_invited), `summary`, `share_url`. |
| **hillmeet_list_nonresponders** | See who has not voted yet (e.g. to send a reminder). | `nonresponders` (email, name), `summary`. |
| **hillmeet_close_poll** | User has chosen a final time; lock the poll and optionally notify participants. | `closed`, `final_slot`, `summary`; if `notify` was true, summary mentions email/calendar. |

**Share URL:** For **hillmeet_create_poll**, the returned `share_url` is the full shareable link (includes a secret in the query string). Anyone with this URL can open the poll; treat it like a password and share it only with intended participants. For **hillmeet_list_polls**, **hillmeet_get_poll**, and **hillmeet_find_availability**, `share_url` is the full shareable link (with secret) when the poll has a stored encrypted secret (polls created via MCP or the web app after this feature); otherwise it is the base poll URL without the secret. So the AI agent can call **list_polls** or **get_poll** anytime and share the returned `share_url` with participants without maintaining state from the create response.

All times in **create_poll** options are **start only** (ISO8601 UTC); the server computes end from `duration_minutes`. Do not send `end` in options. For **close_poll**, `final_slot` must include both `start` and `end` (ISO8601 UTC) and must match one of the poll's options.

## cURL examples

Use a valid API key from `bin/mcp-create-key.php` in the `Authorization` header. Replace `YOUR_API_KEY` and the base URL if needed.

**Step 1: Initialize and capture session id**

```bash
# Option A: show response headers (-i), then copy Mcp-Session-Id from the output
curl -s -i -X POST "https://meet.hillwork.net/mcp/v1" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -d '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2024-11-05","capabilities":{},"clientInfo":{"name":"example","version":"1.0.0"}}}'

# Option B: save session id to a variable (requires grep/sed)
RESP=$(curl -s -D - -X POST "https://meet.hillwork.net/mcp/v1" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -d '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2024-11-05","capabilities":{},"clientInfo":{"name":"example","version":"1.0.0"}}}')
SESSION=$(echo "$RESP" | grep -i '^Mcp-Session-Id:' | cut -d' ' -f2 | tr -d '\r')
# Now use $SESSION in the next requests
```

Expected: JSON-RPC result with `serverInfo` and `capabilities`; response headers include `Mcp-Session-Id: <uuid>`.

**Step 2: List tools** (send the session id from step 1)

```bash
curl -s -X POST "https://meet.hillwork.net/mcp/v1" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Mcp-Session-Id: YOUR_SESSION_UUID" \
  -d '{"jsonrpc":"2.0","id":2,"method":"tools/list"}'
```

Replace `YOUR_SESSION_UUID` with the `Mcp-Session-Id` value from the initialize response. Expected: `result.tools` array including `hillmeet_ping`, e.g.:

```json
{"jsonrpc":"2.0","id":2,"result":{"tools":[{"name":"hillmeet_ping","inputSchema":{"type":"object","properties":{}},"description":"Ping the Hillmeet service"}]}}
```

**Step 3: Call tool** `hillmeet_ping` (same session id)

```bash
curl -s -X POST "https://meet.hillwork.net/mcp/v1" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Mcp-Session-Id: YOUR_SESSION_UUID" \
  -d '{"jsonrpc":"2.0","id":3,"method":"tools/call","params":{"name":"hillmeet_ping","arguments":{}}}'
```

Expected: JSON-RPC result with content describing the ping response. Example result content:

```json
{"ok":true,"service":"hillmeet","time":"2026-02-24T12:00:00+00:00"}
```

Each tool call is audit-logged with tenant_id, tool name, duration_ms, ok/error, and request_id.

## Allowed methods

- **POST** — JSON-RPC request body; response is JSON (or 202 if no immediate response).
- **OPTIONS** — CORS preflight; returns 204.
- **GET** / **DELETE** / others — **405 Method Not Allowed**.

## Implementation

- Handler: `src/Support/McpEndpoint.php` (included by front controller when path is `/mcp/v1`; no physical directory at `public/mcp/v1` to avoid Apache 301).
- Auth: `src/Mcp/Auth.php` (Bearer API key → tenant); audit: `src/Mcp/Audit.php` (tool call logging).
- SDK: [mcp/sdk](https://github.com/modelcontextprotocol/php-sdk) (official PHP MCP SDK)
- Transport: `StreamableHttpTransport` (POST-only JSON, no SSE in this setup)
