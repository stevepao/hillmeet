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
 * Build the poll page URL for redirects, using secret or invite param when present.
 */
function poll_back_url(string $slug, string $secret, string $inviteToken): string
{
    if ($secret !== '') {
        return url('/poll/' . $slug . '?secret=' . urlencode($secret));
    }
    if ($inviteToken !== '') {
        return url('/poll/' . $slug . '?invite=' . urlencode($inviteToken));
    }
    return url('/poll/' . $slug);
}

/**
 * Get an integer from $_POST (for form validation). Returns 0 when key is missing or not numeric.
 */
function post_int(string $key): int
{
    $v = $_POST[$key] ?? 0;
    return (int) $v;
}

/**
 * Get config value by dot key.
 */
function config(string $key, $default = null)
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

/**
 * Validate return_to to prevent open redirect: only relative path starting with /, no //, no http.
 */
function validate_return_to(?string $returnTo): bool
{
    if ($returnTo === null || $returnTo === '' || $returnTo[0] !== '/') {
        return false;
    }
    if (strpos($returnTo, '//') !== false) {
        return false;
    }
    if (stripos($returnTo, 'http') !== false) {
        return false;
    }
    return true;
}

/**
 * Redirect to return_to if valid (and clear it), otherwise redirect to default path.
 */
function redirect_to_return_or(string $defaultPath = '/'): void
{
    $rt = $_SESSION['return_to'] ?? null;
    unset($_SESSION['return_to']);
    if (validate_return_to($rt)) {
        header('Location: ' . rtrim((string) config('app.url', ''), '/') . $rt);
        exit;
    }
    header('Location: ' . url($defaultPath));
    exit;
}
