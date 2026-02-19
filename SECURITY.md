# Security

## Reporting a vulnerability

Please report security issues privately (e.g. via the repository’s security contact or GitHub Security Advisories). Do not open public issues for vulnerabilities.

## Practices in this project

- **Database:** All queries use prepared statements (PDO). No raw user input in SQL.
- **Output:** User content is escaped via the `e()` helper (HTML entity encoding) in views.
- **CSRF:** All state-changing POST requests require a valid CSRF token (`Csrf::field()` in forms, `Csrf::validate()` in the front controller).
- **Poll access:** Polls are identified by slug + secret; the secret is stored hashed and compared with `password_verify()` in constant time.
- **Auth:** Google ID tokens are verified server-side. Email PINs are hashed, expire in 10 minutes, and are rate-limited.
- **OAuth tokens:** Google refresh tokens are encrypted at rest (AES-256-GCM) using `ENCRYPTION_KEY`.
- **Sessions:** Stored in MySQL with secure cookie settings (HttpOnly, SameSite=Lax, optional Secure).
- **Rate limiting:** Applied to PIN request/attempt, poll creation, votes, invites, and calendar checks.

Ensure `ENCRYPTION_KEY` is a strong 32-byte (64-character hex) value and is kept secret. Do not commit `config/config.php` or `.env` (they are gitignored).
