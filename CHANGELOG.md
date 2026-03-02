# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

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
