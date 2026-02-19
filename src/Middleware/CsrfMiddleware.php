<?php

declare(strict_types=1);

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
