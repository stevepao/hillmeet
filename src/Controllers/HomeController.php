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
        $userId = (int) $user->id;
        $pollRepo = new PollRepository();
        $ownedPolls = $pollRepo->listOwnedPolls($userId);
        $participatedPolls = $pollRepo->listParticipatedPolls($userId);
        $debugCounts = null;
        if (\env('APP_ENV', '') === 'local') {
            $debugCounts = ['owned' => count($ownedPolls), 'participated' => count($participatedPolls)];
        }
        require dirname(__DIR__, 2) . '/views/home.php';
    }
}
