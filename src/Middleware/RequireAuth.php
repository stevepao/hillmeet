<?php

declare(strict_types=1);

namespace Hillmeet\Middleware;

use Hillmeet\Support\url;

final class RequireAuth
{
    public static function check(): bool
    {
        if (!empty($_SESSION['user'])) {
            return true;
        }
        header('Location: ' . url('/auth/login'));
        exit;
    }
}
