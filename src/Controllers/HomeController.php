<?php

declare(strict_types=1);

/**
 * HomeController.php
 * Purpose: Home and static pages (index, privacy, terms).
 * Project: Hillmeet
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 */

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

    /** Privacy Policy page (no auth required). */
    public function privacy(): void
    {
        require dirname(__DIR__, 2) . '/views/privacy.php';
    }

    /** Terms of Service page (no auth required). */
    public function terms(): void
    {
        require dirname(__DIR__, 2) . '/views/terms.php';
    }
}
