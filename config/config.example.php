<?php

declare(strict_types=1);

/**
 * Copy to config.php and fill in values.
 * config.php is gitignored.
 */

return [
    'db' => [
        'host'     => getenv('DB_HOST') ?: 'localhost',
        'name'     => getenv('DB_NAME') ?: 'hillmeet',
        'user'     => getenv('DB_USER') ?: '',
        'pass'     => getenv('DB_PASS') ?: '',
        'charset'  => getenv('DB_CHARSET') ?: 'utf8mb4',
    ],
    'app' => [
        'url'           => rtrim(getenv('APP_URL') ?: 'http://localhost', '/'),
        'timezone'      => 'UTC',
        'session_ttl'   => (int) (getenv('SESSION_LIFETIME') ?: 7200),
        'session_cookie' => getenv('SESSION_COOKIE') ?: 'hillmeet_session',
        'encryption_key' => getenv('ENCRYPTION_KEY') ?: '',
    ],
    'smtp' => [
        'host'     => getenv('SMTP_HOST') ?: '',
        'port'     => (int) (getenv('SMTP_PORT') ?: 587),
        'user'     => getenv('SMTP_USER') ?: '',
        'pass'     => getenv('SMTP_PASS') ?: '',
        'from'     => getenv('SMTP_FROM') ?: 'noreply@localhost',
        'from_name' => getenv('SMTP_FROM_NAME') ?: 'Hillmeet',
    ],
    'google' => [
        'client_id'     => getenv('GOOGLE_CLIENT_ID') ?: '',
        'client_secret' => getenv('GOOGLE_CLIENT_SECRET') ?: '',
        'redirect_uri'  => getenv('GOOGLE_REDIRECT_URI') ?: '',
    ],
    'rate' => [
        'pin_request'   => (int) (getenv('RATE_PIN_REQUEST') ?: 3),
        'pin_attempt'   => (int) (getenv('RATE_PIN_ATTEMPT') ?: 5),
        'poll_create'   => (int) (getenv('RATE_POLL_CREATE') ?: 5),
        'vote'          => (int) (getenv('RATE_VOTE') ?: 20),
        'invite'        => (int) (getenv('RATE_INVITE') ?: 10),
        'calendar_check' => (int) (getenv('RATE_CALENDAR_CHECK') ?: 10),
    ],
    'freebusy_cache_ttl' => (int) (getenv('FREEBUSY_CACHE_TTL') ?: 600),
];
