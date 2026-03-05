# MCP endpoint (v1)

Hillmeet exposes an [MCP](https://modelcontextprotocol.io/) (Model Context Protocol) server at `/mcp/v1` for tool-calling integrations. The endpoint is **POST-only**, returns **JSON**, and does not use SSE or long-running connections (Apache/PHP-FPM friendly).

## Base URL

- Production: `https://meet.hillwork.net/mcp/v1`
- Local: `http://localhost:8080/mcp/v1` (or your `APP_URL` + `/mcp/v1`)

Use a trailing slash if your server requires it: `/mcp/v1/`.

## Protocol

The endpoint speaks standard MCP JSON-RPC over HTTP:

- **initialize** — handshake; returns server info and capabilities.
- **tools/list** — list tools (currently empty for v1).
- **tools/call** — invoke a tool by name (will return an error if the tool does not exist).

All requests must be `POST` with `Content-Type: application/json`. Responses are JSON. The transport supports the `Mcp-Session-Id` header for session affinity when the client sends it.

## cURL examples

### Initialize (handshake)

```bash
curl -s -X POST "https://meet.hillwork.net/mcp/v1" \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2024-11-05","capabilities":{},"clientInfo":{"name":"example","version":"1.0.0"}}}'
```

Expected: JSON-RPC result with `serverInfo` and `capabilities`.

### List tools (empty for now)

```bash
curl -s -X POST "https://meet.hillwork.net/mcp/v1" \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","id":2,"method":"tools/list"}'
```

Expected: `result.tools` array (empty in v1).

### Call a tool (will error until tools are added)

```bash
curl -s -X POST "https://meet.hillwork.net/mcp/v1" \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","id":3,"method":"tools/call","params":{"name":"example_tool","arguments":{}}}'
```

Expected: JSON-RPC error if the tool is not implemented.

## Allowed methods

- **POST** — JSON-RPC request body; response is JSON (or 202 if no immediate response).
- **OPTIONS** — CORS preflight; returns 204.
- **GET** / **DELETE** / others — **405 Method Not Allowed**.

## Implementation

- Entry point: `public/mcp/v1/index.php`
- SDK: [mcp/sdk](https://github.com/modelcontextprotocol/php-sdk) (official PHP MCP SDK)
- Transport: `StreamableHttpTransport` (POST-only JSON, no SSE in this setup)
