<?php

declare(strict_types=1);

namespace Hillmeet\Middleware;

use function Hillmeet\Support\url;

final class RequireAuth
{
    public static function check(): bool
    {
        if (!empty($_SESSION['user'])) {
            return true;
        }
        $query = (isset($_GET['debug']) && $_GET['debug'] === '1') ? ['debug' => '1'] : [];
        header('Location: ' . url('/auth/login', $query));
        exit;
    }
}
