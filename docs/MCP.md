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

## cURL examples

Use a valid API key from `bin/mcp-create-key.php` in the `Authorization` header.

### Initialize (handshake)

```bash
curl -s -X POST "https://meet.hillwork.net/mcp/v1" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -d '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2024-11-05","capabilities":{},"clientInfo":{"name":"example","version":"1.0.0"}}}'
```

Expected: JSON-RPC result with `serverInfo` and `capabilities`; response `id` equals request `id`.

### List tools

```bash
curl -s -X POST "https://meet.hillwork.net/mcp/v1" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -d '{"jsonrpc":"2.0","id":2,"method":"tools/list"}'
```

Expected: `result.tools` array including `hillmeet_ping` with empty input schema, e.g.:

```json
{"jsonrpc":"2.0","id":2,"result":{"tools":[{"name":"hillmeet_ping","inputSchema":{"type":"object","properties":{}},"description":"Ping the Hillmeet service"}]}}
```

### Call tool: hillmeet_ping

```bash
curl -s -X POST "https://meet.hillwork.net/mcp/v1" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -d '{"jsonrpc":"2.0","id":3,"method":"tools/call","params":{"name":"hillmeet_ping","arguments":{}}}'
```

Expected: JSON-RPC result with content describing the ping response; response `id` equals `3`. Example result content:

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
