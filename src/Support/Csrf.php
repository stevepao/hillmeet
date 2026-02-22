<?php

declare(strict_types=1);

/**
 * Csrf.php
 * Purpose: CSRF token generation and validation.
 * Project: Hillmeet
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 */

namespace Hillmeet\Support;

final class Csrf
{
    private const SESSION_KEY = '_csrf_token';

    public static function token(): string
    {
        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::SESSION_KEY];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . \Hillmeet\Support\e(self::token()) . '">';
    }

    public static function validate(): bool
    {
        $token = $_POST['csrf_token'] ?? '';
        $expected = $_SESSION[self::SESSION_KEY] ?? '';
        return $expected !== '' && hash_equals($expected, $token);
    }
}
