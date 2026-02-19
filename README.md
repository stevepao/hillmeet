# Hillmeet

Doodle-like group availability polls with Google Calendar integration. Accessible only by unguessable link (slug + secret). Sign-in required to vote. Optional Google Calendar free/busy and event creation.

**Stack:** Vanilla PHP 8.4 + MySQL. No frameworks. IONOS shared hosting friendly (Apache, no Docker).

## Requirements

- PHP 8.4+ with extensions: `pdo`, `pdo_mysql`, `json`, `mbstring`, `openssl`
- MySQL 5.7+ or MariaDB 10.3+
- Composer

## Quick start (local)

```bash
composer install
cp config/config.example.php config/config.php
cp .env.example .env
# Edit .env: DB_*, APP_URL, ENCRYPTION_KEY (32-byte hex)
php bin/migrate.php
# Point your web server document root to public/
```

## IONOS shared hosting deployment

1. **Upload** the project (e.g. via FTP/Git) so that the repo root is in a folder (e.g. `hillmeet/`).

2. **Set document root** to the `public/` directory:
   - In IONOS: Domain & SSL → Manage → Document root → set to `hillmeet/public` (or the path that points to `public` inside the project).

3. **`.htaccess` routing**  
   `public/.htaccess` is already set to send all requests that are not existing files to `index.php`. Ensure `mod_rewrite` is enabled (default on IONOS).

4. **Environment and config**
   - Create `.env` in the project root (same level as `composer.json`) from `.env.example`.
   - Set at least: `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `APP_URL`, `ENCRYPTION_KEY`.
   - Ensure `config/config.php` exists (copy from `config/config.example.php`). The app reads config from `config/config.php`, which in turn uses `getenv()` so that values can come from `.env` or the server environment.

5. **Run migrations**  
   From SSH (if available) or a one-off script/cron that can run CLI:
   ```bash
   cd /path/to/hillmeet && php bin/migrate.php
   ```

6. **SMTP (email)**  
   In `.env` set IONOS SMTP: `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASS`, `SMTP_FROM`, `SMTP_FROM_NAME`. Used for PIN emails and poll invites.

7. **Assets**  
   If the document root is `public/`, ensure `public/assets/` contains the CSS and JS (this repo includes a copy under `public/assets/`). If you only have `assets/` at the repo root, copy or symlink it into `public/assets/`.

8. **Cron (optional)**  
   Schedule `php /path/to/hillmeet/bin/cron.php` (e.g. every 15 minutes) to clean expired PINs and old data.

## Google Cloud setup

1. Create a project in [Google Cloud Console](https://console.cloud.google.com/).
2. Enable **Google Identity** (for sign-in) and **Google Calendar API** (for calendar list, freebusy, events).
3. Create **OAuth 2.0 credentials** (Desktop or Web application):
   - **Authorized redirect URIs:** `https://your-domain.com/auth/google/callback` and `https://your-domain.com/calendar/callback` (replace with your `APP_URL`).
4. **Scopes** (minimal):
   - Identity: openid, email, profile (for GIS sign-in).
   - Calendar: `https://www.googleapis.com/auth/calendar.readonly`, `https://www.googleapis.com/auth/calendar.events` (for calendar list, freebusy, create event).
5. In `.env` set:
   - `GOOGLE_CLIENT_ID`
   - `GOOGLE_CLIENT_SECRET`
   - `GOOGLE_REDIRECT_URI` = `$APP_URL/auth/google/callback` (or leave and set `APP_URL` so the example config builds it).

If Google Identity is not configured, the app shows **Use email instead** and uses email + one-time PIN sign-in.

## Environment variables (summary)

| Variable | Description |
|----------|-------------|
| `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` | MySQL connection |
| `APP_URL` | Full app URL (no trailing slash), for links and OAuth redirects |
| `ENCRYPTION_KEY` | 32-byte hex (64 chars) for encrypting OAuth refresh tokens and sensitive data |
| `SESSION_LIFETIME`, `SESSION_COOKIE` | Session TTL (seconds) and cookie name |
| `SMTP_*` | SMTP for PIN and invite emails |
| `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI` | Google OAuth |
| `RATE_*` | Rate limit counts per window (see `.env.example`) |
| `FREEBUSY_CACHE_TTL` | Free/busy cache TTL in seconds |

## License

MIT. See [LICENSE.md](LICENSE.md).
