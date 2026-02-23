<?php

declare(strict_types=1);

/**
 * CalendarController.php
 * Purpose: Google Calendar settings, connect, callback, save, disconnect.
 * Project: Hillmeet
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 */

namespace Hillmeet\Controllers;

use Hillmeet\Repositories\FreebusyCacheRepository;
use Hillmeet\Repositories\GoogleCalendarSelectionRepository;
use Hillmeet\Repositories\OAuthConnectionRepository;
use Hillmeet\Services\GoogleCalendarService;
use function Hillmeet\Support\config;
use function Hillmeet\Support\current_user;
use function Hillmeet\Support\url;

final class CalendarController
{
    public function settings(): void
    {
        \Hillmeet\Middleware\RequireAuth::check();
        $userId = (int) current_user()->id;
        $oauthRepo = new OAuthConnectionRepository();
        $selectionRepo = new GoogleCalendarSelectionRepository();
        $connected = $oauthRepo->hasConnection($userId);
        $calendars = [];
        $calendarService = null;
        if ($connected) {
            $calendarService = new GoogleCalendarService($oauthRepo, $selectionRepo, new FreebusyCacheRepository());
            $list = $calendarService->getCalendarList($userId);
            $saved = $selectionRepo->getForUser($userId);
            $savedById = [];
            foreach ($saved as $s) {
                $savedById[$s->calendar_id] = $s;
            }
            foreach ($list as $cal) {
                $s = $savedById[$cal['id']] ?? null;
                $calendars[] = [
                    'id' => $cal['id'],
                    'summary' => $cal['summary'],
                    'selected' => $s ? (bool) $s->selected : true,
                    'tentative_as_busy' => $s ? (bool) $s->tentative_as_busy : true,
                ];
            }
        }
        $authUrl = $connected ? '' : url('/calendar/connect');
        $cacheTtl = config('freebusy_cache_ttl', 600);
        require dirname(__DIR__, 2) . '/views/calendar/settings.php';
    }

    public function connect(): void
    {
        \Hillmeet\Middleware\RequireAuth::check();
        $state = bin2hex(random_bytes(16));
        $_SESSION['oauth2state_calendar'] = $state;
        $calendarService = new GoogleCalendarService(
            new OAuthConnectionRepository(),
            new GoogleCalendarSelectionRepository(),
            new FreebusyCacheRepository()
        );
        $url = $calendarService->getAuthUrl($state);
        header('Location: ' . $url);
        exit;
    }

    public function callback(): void
    {
        \Hillmeet\Middleware\RequireAuth::check();
        $code = $_GET['code'] ?? '';
        if ($code === '') {
            header('Location: ' . url('/calendar'));
            exit;
        }
        $calendarService = new GoogleCalendarService(
            new OAuthConnectionRepository(),
            new GoogleCalendarSelectionRepository(),
            new FreebusyCacheRepository()
        );
        $calendarService->exchangeCodeForTokens($code, (int) current_user()->id);
        header('Location: ' . url('/calendar'));
        exit;
    }

    public function save(): void
    {
        \Hillmeet\Middleware\RequireAuth::check();
        $userId = (int) current_user()->id;
        $selectionRepo = new GoogleCalendarSelectionRepository();
        $calendars = $_POST['calendars'] ?? [];
        $tentativeAsBusy = !empty($_POST['tentative_as_busy']);
        $list = [];
        foreach ($calendars as $id => $cal) {
            if (is_array($cal) && !empty($cal['id'])) {
                $list[] = [
                    'id' => $cal['id'],
                    'summary' => $cal['summary'] ?? $cal['id'],
                    'selected' => !empty($cal['selected']),
                    'tentative_as_busy' => $tentativeAsBusy,
                ];
            }
        }
        $selectionRepo->saveList($userId, $list);
        $selectionRepo->setTentativeAsBusy($userId, $tentativeAsBusy);
        $_SESSION['calendar_saved'] = true;
        header('Location: ' . url('/calendar'));
        exit;
    }

    public function disconnect(): void
    {
        \Hillmeet\Middleware\RequireAuth::check();
        $userId = (int) current_user()->id;
        $oauthRepo = new OAuthConnectionRepository();
        $refreshToken = $oauthRepo->getRefreshToken($userId, 'google');
        if ($refreshToken !== null) {
            GoogleCalendarService::revokeRefreshToken($refreshToken);
        }
        $oauthRepo->deleteForUser($userId, 'google');
        (new GoogleCalendarSelectionRepository())->deleteForUser($userId);
        (new FreebusyCacheRepository())->invalidateForUser($userId);
        $_SESSION['calendar_disconnected'] = true;
        header('Location: ' . url('/calendar'));
        exit;
    }
}
