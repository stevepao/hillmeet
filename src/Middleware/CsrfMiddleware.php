<?php

declare(strict_types=1);

/**
 * CsrfMiddleware.php
 * Purpose: Validate CSRF token on POST requests.
 * Project: Hillmeet
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 */

namespace Hillmeet\Middleware;

use Hillmeet\Support\Csrf;

final class CsrfMiddleware
{
    public static function validate(): bool
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return true;
        }
        if (!Csrf::validate()) {
            http_response_code(403);
            return false;
        }
        return true;
    }
}
