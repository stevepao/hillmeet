# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [1.1.0] - 2026-03-06

### Added

- **MCP server** — Documented in README; tools exposed at `/mcp/v1` for AI assistants and integrations (create poll, find availability, list polls, get poll, list nonresponders, close poll). API keys via `php bin/mcp-create-key.php [owner_email]`.
- **Acknowledgements** — README section listing third-party libraries and their licenses (MIT/LGPL-2.1); satisfies notice requirements for dependencies.
- **Privacy Policy** — API (programmatic) access: same data collected when using the API; API key usage and tool-call logging; retention of API key hashes; choice to revoke API keys.
- **Terms of Service** — Programmatic access via API subject to Terms; API keys and responsibility for their confidentiality.

### Changed

- **MCP create_poll API** — Time options now accept **start only** (ISO8601 UTC). The server computes each option’s end from `start + duration_minutes`. Clients must not send `end` in options; sending `end` returns JSON-RPC -32010 (validation error). Output DTOs still include both start and end (localized).
- **MCP tool descriptions** — Richer descriptions in `tools/list` for AI/tool-calling clients (when to use each tool, what is returned, next steps e.g. share `share_url` with participants).
- **Create-poll result summary** — When the poll has participants, the result summary now includes: “Share the share_url with participants so they can vote.”
- **docs/MCP.md** — Tools overview table for AI clients (when to use, returns, start-only vs final_slot rules).
- **Legal dates** — Privacy Policy “Last updated” and Terms “Effective date” set to 2026-03-06 for this release.
- **Version** — Bumped to 1.1.0 in `composer.json` and MCP server info.

### Fixed

- None.

### Technical

- **Copyright/headers** — Consistent file headers (Purpose, Project: Hillmeet, SPDX-License-Identifier: MIT, Copyright (c) 2026 Hillwork, LLC) across MCP, Adapter, DTO, Exception, and related test files; `bin/mcp-create-key.php`.
- **Tests** — All poll-creation tests use options with only `start`; new test rejects `end` in options with -32010.
- **Manual tests** — `docs/MCP_CURL_MANUAL_TEST.md` and one-shot script use start-only options for `hillmeet_create_poll`.

[1.1.0]: https://github.com/stevepao/hillmeet/releases/tag/v1.1.0

## [1.0.0] - 2026-02-24

### Added

- **Availability polls** — Create polls with time options; participants vote Works / If needed / Can’t. Results and final time with timezone callouts.
- **Google sign-in** — OAuth 2.0 (OpenID Connect) for identity. Optional Google Calendar integration (free/busy and event creation) with incremental scope when creating events.
- **Email sign-in** — One-time PIN to email when Google is not used or not configured.
- **Poll access by link** — Unguessable slug + secret; invite links with optional token. Organizer and participant views; lock to set final time.
- **Google Calendar** — Check availability (free/busy) for time options; create calendar event on lock and invite participants; optional “Notify by email only” with ICS attachment.
- **Lock flow** — Lock a time slot; create Google Calendar event when connected (or notify by email with ICS); send lock emails to participants and invitees.
- **Location in events** — Poll location included in ICS attachments and Google Calendar events.
- **Sign-in at root** — Sign-in page served at `/`; `/auth/login` redirects to `/` (301). Canonical URLs for SEO (home, login, privacy, terms, auth pages).
- **Legal pages** — Privacy Policy and Terms of Service; configurable company and support details.
- **Security** — CSRF on all state-changing forms; hashed poll secrets; encrypted OAuth tokens; rate limiting (PIN, votes, invites, calendar); secure session and cookies.
- **Deployment** — IONOS-friendly (Apache, `.htaccess`); migrations and optional cron for cleanup.

### Technical

- PHP 8.4+, MySQL/MariaDB, Composer. Vanilla PHP (no framework). PSR-4 autoload.
- Dependencies: `league/oauth2-google`, `phpmailer/phpmailer`.
- Smoke tests and manual testing notes in README.

[1.0.0]: https://github.com/stevepao/hillmeet/releases/tag/v1.0.0
