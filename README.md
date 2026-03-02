# Hillmeet

**Find a time that works for everyone—then send meeting invitations automatically.**

Hillmeet is a Doodle-like group availability poll app with Google Calendar integration. Create a poll, share a link, collect votes, lock a time, and optionally create a Google Calendar event and notify participants. Polls are accessible only via unguessable links (slug + secret). Sign-in (Google or email PIN) is required to vote.

**Live demo:** [meet.hillwork.net](https://meet.hillwork.net)

---

## Features

- **Availability polls** — Add time options; participants vote Works / If needed / Can’t.
- **Google Calendar** — Optional free/busy check (“Check my availability”), then create a calendar event when you lock the poll and invite participants.
- **Email sign-in** — One-time PIN to your email if you don’t use Google.
- **Privacy-first** — No public listing; each poll is identified by a secret link. See [Privacy Policy](https://meet.hillwork.net/privacy) and [Terms of Service](https://meet.hillwork.net/terms) on the live site.

**Stack:** PHP 8.4+, MySQL, Composer. No front-end framework. IONOS shared hosting friendly (Apache, no Docker).

---

## Requirements

- **PHP 8.4+** with extensions: `pdo`, `pdo_mysql`, `json`, `mbstring`, `openssl`
- **MySQL 5.7+** or MariaDB 10.3+
- **Composer**

---

## Quick start (local)

```bash
composer install
cp config/config.example.php config/config.php
cp .env.example .env
# Edit .env: DB_*, APP_URL, ENCRYPTION_KEY (32-byte hex)
php bin/migrate.php
# Point your web server document root to public/
```

Then open your app URL in the browser. Sign in with Google (after configuring OAuth) or use **Use email instead** for PIN sign-in.

---

## Google Cloud setup (OAuth)

Hillmeet uses Google OAuth for sign-in and (optionally) Google Calendar (free/busy and event creation). To run your own instance:

1. Create a project in [Google Cloud Console](https://console.cloud.google.com/).
2. Enable **Google Calendar API** (for calendar list, free/busy, and events). Sign-in uses OpenID Connect (openid, email, profile) via the same OAuth client.
3. Create **OAuth 2.0 credentials** (Web application):
   - **Authorized redirect URIs:**  
     `https://your-domain.com/auth/google/callback`  
     `https://your-domain.com/calendar/callback`  
     (Use your `APP_URL`; no trailing slash.)
4. In `.env` set:
   - `GOOGLE_CLIENT_ID`
   - `GOOGLE_CLIENT_SECRET`
   - `GOOGLE_REDIRECT_URI` (optional; defaults to `APP_URL/auth/google/callback`).

If OAuth is not configured, the app still works with **Use email instead** (email + one-time PIN).

---

## IONOS shared hosting deployment

1. **Upload** the project (e.g. via Git or FTP) so the repo root is in a folder (e.g. `hillmeet/`).
2. **Document root** — Set your domain’s document root to the `public/` directory (e.g. `hillmeet/public`). `public/.htaccess` routes all requests to `index.php`; ensure `mod_rewrite` is enabled.
3. **Environment** — Create `.env` in the project root from `.env.example`. Set at least: `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `APP_URL`, `ENCRYPTION_KEY`. Ensure `config/config.php` exists (copy from `config/config.example.php`).
4. **Migrations** — Run `php bin/migrate.php` from SSH or a one-off script.
5. **SMTP** — Configure `SMTP_*` in `.env` for PIN and invite emails (e.g. IONOS SMTP).
6. **Assets** — The repo includes `public/assets/` (CSS, JS, images). If your document root is `public/`, they are already served.
7. **Cron (optional)** — Schedule `php /path/to/hillmeet/bin/cron.php` (e.g. every 15 minutes) to clean expired PINs and old data.

---

## Environment variables (summary)

| Variable | Description |
|----------|-------------|
| `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` | MySQL connection |
| `APP_URL` | Full app URL (no trailing slash); used for links and OAuth redirects |
| `ENCRYPTION_KEY` | 32-byte hex (64 chars) for OAuth tokens and sensitive data |
| `SESSION_LIFETIME`, `SESSION_COOKIE` | Session TTL (seconds) and cookie name |
| `SMTP_*` | SMTP for PIN and invite emails |
| `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI` | Google OAuth |
| `RATE_*` | Rate limit counts per window (see `.env.example`) |
| `FREEBUSY_CACHE_TTL` | Free/busy cache TTL in seconds |
| `LEGAL_*` | Company name, support email, governing state (for Privacy & Terms pages) |

---

## Manual testing

- **Invite link after login** — Open an invite link in a private window while logged out. Sign in (Google or email PIN); you should land back on the poll view for that invite.
- **Vote submission** — Vote on a time slot; confirm “Vote saved,” the button stays selected, and results update after refresh.
- **Lock and calendar** — Lock a poll, then use “Create calendar event” (or “Notify by email only”) and confirm participants are notified.

---

## License

MIT. See [LICENSE.md](LICENSE.md).

---

## Links

- [Privacy Policy](https://meet.hillwork.net/privacy) · [Terms of Service](https://meet.hillwork.net/terms)  
- [Contributing](CONTRIBUTING.md) · [Security](SECURITY.md)
