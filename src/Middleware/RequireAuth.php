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
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        $pu = parse_url($requestUri);
        $path = $pu['path'] ?? '/';
        $query = isset($pu['query']) && $pu['query'] !== '' ? $pu['query'] : '';
        $pathAndQuery = $path . ($query !== '' ? '?' . $query : '');
        if ($pathAndQuery !== '' && $pathAndQuery[0] === '/') {
            $_SESSION['return_to'] = $pathAndQuery;
        }
        $query = (isset($_GET['debug']) && $_GET['debug'] === '1') ? ['debug' => '1'] : [];
        header('Location: ' . url('/auth/login', $query));
        exit;
    }
}
