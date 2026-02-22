<?php

declare(strict_types=1);

/**
 * env.php
 * Purpose: Load .env and provide env() helper for config.
 * Project: Hillmeet
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 */

/**
 * Get env var (works on IONOS/shared hosts where getenv() can be unreliable).
 */
function env(string $key, $default = null)
{
    $v = getenv($key);
    if ($v !== false) {
        return $v;
    }
    return $_ENV[$key] ?? $_SERVER[$key] ?? $default;
}

/**
 * Load environment from .env file if present.
 * Does not override existing env vars.
 */
function loadEnv(string $path): void
{
    if (!is_readable($path)) {
        return;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        if (strpos($line, '=') === false) {
            continue;
        }
        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value, " \t\"'");
        if ($name !== '') {
            $existing = getenv($name);
            if ($existing === false && !isset($_ENV[$name])) {
                putenv("$name=$value");
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}

$envPath = dirname(__DIR__) . '/.env';
loadEnv($envPath);
