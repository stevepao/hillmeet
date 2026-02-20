<?php

declare(strict_types=1);

namespace Hillmeet\Services;

use Hillmeet\Repositories\FreebusyCacheRepository;
use Hillmeet\Repositories\GoogleCalendarSelectionRepository;
use Hillmeet\Repositories\OAuthConnectionRepository;
use Hillmeet\Support\RateLimit;
use function Hillmeet\Support\config;

final class GoogleCalendarService
{
    public function __construct(
        private OAuthConnectionRepository $oauthRepo,
        private GoogleCalendarSelectionRepository $selectionRepo,
        private FreebusyCacheRepository $freebusyCache
    ) {}

    public function getAuthUrl(string $state): string
    {
        $clientId = config('google.client_id');
        $redirectUri = config('google.redirect_uri') ?: (rtrim((string) config('app.url', ''), '/') . '/auth/google/callback');
        $scope = urlencode('https://www.googleapis.com/auth/calendar.readonly https://www.googleapis.com/auth/calendar.events');
        return "https://accounts.google.com/o/oauth2/v2/auth?response_type=code&client_id={$clientId}&redirect_uri=" . urlencode($redirectUri) . "&scope={$scope}&access_type=offline&prompt=consent&state=" . urlencode($state);
    }

    public function exchangeCodeForTokens(string $code, int $userId): ?string
    {
        $clientId = config('google.client_id');
        $clientSecret = config('google.client_secret');
        $redirectUri = config('google.redirect_uri') ?: (rtrim((string) config('app.url', ''), '/') . '/auth/google/callback');
        $res = @file_get_contents('https://oauth2.googleapis.com/token', false, stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/x-www-form-urlencoded',
                'content' => http_build_query([
                    'code' => $code,
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'redirect_uri' => $redirectUri,
                    'grant_type' => 'authorization_code',
                ]),
            ],
        ]));
        if ($res === false) {
            return null;
        }
        $data = json_decode($res, true);
        if (empty($data['refresh_token'])) {
            return null;
        }
        $this->oauthRepo->upsert(
            $userId,
            'google',
            $data['refresh_token'],
            $data['access_token'] ?? null,
            isset($data['expires_in']) ? date('Y-m-d H:i:s', time() + $data['expires_in']) : null
        );
        return null;
    }

    /** @return array<array{id: string, summary: string}> */
    public function getCalendarList(int $userId): array
    {
        $accessToken = $this->getAccessToken($userId);
        if ($accessToken === null) {
            return [];
        }
        $res = $this->apiGet($accessToken, 'https://www.googleapis.com/calendar/v3/users/me/calendarList');
        if ($res === null || !isset($res['items'])) {
            return [];
        }
        $out = [];
        foreach ($res['items'] as $cal) {
            $out[] = ['id' => $cal['id'], 'summary' => $cal['summary'] ?? $cal['id']];
        }
        return $out;
    }

    public function saveCalendarSelections(int $userId, array $calendars): void
    {
        $this->selectionRepo->saveList($userId, $calendars);
        $this->freebusyCache->invalidateForUser($userId);
    }

    /**
     * @return array{busy: array<int, bool>, checked_at: string, error?: string}
     */
    public function getFreebusyForPoll(int $userId, int $pollId, array $options): array
    {
        $key = 'calendar_check:' . $userId . ':' . $pollId;
        if (!RateLimit::check($key, (int) config('rate.calendar_check'))) {
            return ['busy' => [], 'checked_at' => date('c'), 'error' => 'rate_limited'];
        }
        $ttl = (int) config('freebusy_cache_ttl', 600);
        $result = [];
        $toFetch = [];
        foreach ($options as $opt) {
            $cached = $this->freebusyCache->get($userId, $opt->id, $ttl);
            if ($cached !== null) {
                $result[$opt->id] = $cached;
            } else {
                $toFetch[] = $opt;
            }
        }
        if ($toFetch === []) {
            return ['busy' => $result, 'checked_at' => date('c')];
        }
        $accessToken = $this->getAccessToken($userId);
        if ($accessToken === null) {
            return ['busy' => $result, 'checked_at' => date('c'), 'error' => 'not_connected'];
        }
        $calendarIds = $this->selectionRepo->getSelectedCalendarIds($userId);
        if ($calendarIds === []) {
            return ['busy' => $result, 'checked_at' => date('c'), 'error' => 'no_calendars'];
        }
        $timeMin = $toFetch[0]->start_utc;
        $timeMax = end($toFetch)->end_utc;
        $body = [
            'timeMin' => date('c', strtotime($timeMin)),
            'timeMax' => date('c', strtotime($timeMax)),
            'items' => array_map(fn($id) => ['id' => $id], $calendarIds),
        ];
        $res = $this->apiPost($accessToken, 'https://www.googleapis.com/calendar/v3/freeBusy', $body);
        if ($res === null || !isset($res['calendars'])) {
            return ['busy' => $result, 'checked_at' => date('c'), 'error' => 'api_error'];
        }
        $tentativeAsBusy = $this->selectionRepo->getTentativeAsBusy($userId);
        foreach ($toFetch as $opt) {
            $busy = false;
            $optStart = strtotime($opt->start_utc);
            $optEnd = strtotime($opt->end_utc);
            foreach ($res['calendars'] as $cal) {
                foreach ($cal['busy'] ?? [] as $busySlot) {
                    $start = strtotime($busySlot['start']);
                    $end = strtotime($busySlot['end']);
                    if ($start < $optEnd && $end > $optStart) {
                        $busy = true;
                        break 2;
                    }
                }
                if ($tentativeAsBusy) {
                    foreach ($cal['tentative'] ?? [] as $tentSlot) {
                        $start = strtotime($tentSlot['start']);
                        $end = strtotime($tentSlot['end']);
                        if ($start < $optEnd && $end > $optStart) {
                            $busy = true;
                            break 2;
                        }
                    }
                }
            }
            $this->freebusyCache->set($userId, $pollId, $opt->id, $busy);
            $result[$opt->id] = $busy;
        }
        return ['busy' => $result, 'checked_at' => date('c')];
    }

    public function createEvent(int $userId, string $calendarId, string $title, string $description, string $location, string $startUtc, string $endUtc, array $attendeeEmails = []): ?string
    {
        $accessToken = $this->getAccessToken($userId);
        if ($accessToken === null) {
            return null;
        }
        $event = [
            'summary' => $title,
            'description' => $description,
            'location' => $location,
            'start' => ['dateTime' => date('c', strtotime($startUtc)), 'timeZone' => 'UTC'],
            'end' => ['dateTime' => date('c', strtotime($endUtc)), 'timeZone' => 'UTC'],
        ];
        if ($attendeeEmails !== []) {
            $event['attendees'] = array_map(fn($e) => ['email' => $e], $attendeeEmails);
        }
        $url = 'https://www.googleapis.com/calendar/v3/calendars/' . urlencode($calendarId) . '/events';
        $res = $this->apiPost($accessToken, $url, $event);
        return $res['id'] ?? null;
    }

    private function getAccessToken(int $userId): ?string
    {
        $refreshToken = $this->oauthRepo->getRefreshToken($userId);
        if ($refreshToken === null) {
            return null;
        }
        $clientId = config('google.client_id');
        $clientSecret = config('google.client_secret');
        $res = @file_get_contents('https://oauth2.googleapis.com/token', false, stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/x-www-form-urlencoded',
                'content' => http_build_query([
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'refresh_token' => $refreshToken,
                    'grant_type' => 'refresh_token',
                ]),
            ],
        ]));
        if ($res === false) {
            return null;
        }
        $data = json_decode($res, true);
        $accessToken = $data['access_token'] ?? null;
        if ($accessToken !== null && isset($data['expires_in'])) {
            $this->oauthRepo->upsert($userId, 'google', $refreshToken, $accessToken, date('Y-m-d H:i:s', time() + $data['expires_in']));
        }
        return $accessToken;
    }

    private function apiGet(string $accessToken, string $url): ?array
    {
        $ctx = stream_context_create([
            'http' => [
                'header' => 'Authorization: Bearer ' . $accessToken,
            ],
        ]);
        $res = @file_get_contents($url, false, $ctx);
        return $res !== false ? json_decode($res, true) : null;
    }

    private function apiPost(string $accessToken, string $url, array $body): ?array
    {
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Authorization: Bearer {$accessToken}\r\nContent-Type: application/json",
                'content' => json_encode($body),
            ],
        ]);
        $res = @file_get_contents($url, false, $ctx);
        return $res !== false ? json_decode($res, true) : null;
    }
}
