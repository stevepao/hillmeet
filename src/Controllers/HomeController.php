<?php

declare(strict_types=1);

namespace Hillmeet\Controllers;

use Hillmeet\Repositories\PollRepository;

final class HomeController
{
    public function index(): void
    {
        \Hillmeet\Middleware\RequireAuth::check();
        $user = \Hillmeet\Support\current_user();
        $pollRepo = new PollRepository();
        $recentPolls = $pollRepo->listRecentForUser((int) $user->id, 10);
        require dirname(__DIR__, 2) . '/views/home.php';
    }
}
