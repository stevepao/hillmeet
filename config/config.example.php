<?php

declare(strict_types=1);

/**
 * Copy to config.php and fill in values.
 * config.php is gitignored.
 * Uses env() so .env and server vars work on IONOS.
 */
$e = function (string $key, $default = '') { return env($key, $default); };

return [
    'db' => [
        'host'     => $e('DB_HOST', 'localhost'),
        'name'     => $e('DB_NAME', 'hillmeet'),
        'user'     => $e('DB_USER', ''),
        'pass'     => $e('DB_PASS', ''),
        'charset'  => $e('DB_CHARSET', 'utf8mb4'),
    ],
    'app' => [
        'url'           => rtrim((string) $e('APP_URL', 'http://localhost'), '/'),
        'timezone'      => 'UTC',
        'session_ttl'   => (int) $e('SESSION_LIFETIME', 7200),
        'session_cookie' => $e('SESSION_COOKIE', 'hillmeet_session'),
        'encryption_key' => (string) $e('ENCRYPTION_KEY', ''),
    ],
    'smtp' => [
        'host'     => $e('SMTP_HOST', ''),
        'port'     => (int) $e('SMTP_PORT', 587),
        'user'     => $e('SMTP_USER', ''),
        'pass'     => $e('SMTP_PASS', ''),
        'from'     => $e('SMTP_FROM', 'noreply@localhost'),
        'from_name' => $e('SMTP_FROM_NAME', 'Hillmeet'),
    ],
    'google' => [
        'client_id'     => $e('GOOGLE_CLIENT_ID', ''),
        'client_secret' => $e('GOOGLE_CLIENT_SECRET', ''),
        'redirect_uri'  => $e('GOOGLE_REDIRECT_URI', ''),
    ],
    'rate' => [
        'pin_request'   => (int) $e('RATE_PIN_REQUEST', 3),
        'pin_attempt'   => (int) $e('RATE_PIN_ATTEMPT', 5),
        'poll_create'   => (int) $e('RATE_POLL_CREATE', 5),
        'vote'          => (int) $e('RATE_VOTE', 20),
        'invite'        => (int) $e('RATE_INVITE', 10),
        'calendar_check' => (int) $e('RATE_CALENDAR_CHECK', 10),
    ],
    'freebusy_cache_ttl' => (int) $e('FREEBUSY_CACHE_TTL', 600),
];
