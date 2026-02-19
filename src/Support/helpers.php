<?php

declare(strict_types=1);

namespace Hillmeet\Support;

/**
 * Escape HTML for safe output (use for all user content).
 */
function e(?string $value): string
{
    if ($value === null || $value === '') {
        return '';
    }
    return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Generate a URL for a named route (path only).
 */
function url(string $path, array $query = []): string
{
    $base = rtrim(config('app.url', ''), '/');
    $path = '/' . ltrim($path, '/');
    if ($query !== []) {
        $path .= '?' . http_build_query($query);
    }
    return $base . $path;
}

/**
 * Get config value by dot key.
 */
function config(string $key, mixed $default = null): mixed
{
    static $config = null;
    if ($config === null) {
        $config = require dirname(__DIR__, 2) . '/config/config.php';
    }
    $keys = explode('.', $key);
    $v = $config;
    foreach ($keys as $k) {
        if (!is_array($v) || !array_key_exists($k, $v)) {
            return $default;
        }
        $v = $v[$k];
    }
    return $v;
}

/**
 * Get current authenticated user from session.
 */
function current_user(): ?object
{
    return $_SESSION['user'] ?? null;
}

/**
 * Require auth; redirect to login if not signed in.
 */
function require_auth(): void
{
    if (empty($_SESSION['user'])) {
        header('Location: ' . url('/auth/login'));
        exit;
    }
}
