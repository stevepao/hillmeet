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
        $tokenResult = $this->getAccessToken($userId);
        $accessToken = $tokenResult['access_token'] ?? null;
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
        $tokenResult = $this->getAccessToken($userId);
        $accessToken = $tokenResult['access_token'] ?? null;
        if ($accessToken === null) {
            $err = $tokenResult['error'] ?? 'not_connected';
            $code = ($err === 'invalid_grant' || $err === 'invalid_token') ? 'token_refresh_failed' : 'not_connected';
            return ['busy' => $result, 'checked_at' => date('c'), 'error' => $code, 'error_description' => $tokenResult['error_description'] ?? ''];
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
        $freebusyUrl = 'https://www.googleapis.com/calendar/v3/freeBusy';
        $apiResult = $this->apiPostWithStatus($accessToken, $freebusyUrl, $body);
        $res = $apiResult['body'];
        $status = $apiResult['status'];
        if ($res === null || !isset($res['calendars'])) {
            $code = 'api_error';
            if ($status === 401) {
                $code = 'token_refresh_failed';
            } elseif ($status === 403) {
                $code = 'insufficient_permissions';
            } elseif ($status === 429) {
                $code = 'rate_limited';
            } elseif ($status >= 500) {
                $code = 'api_error';
            }
            return [
                'busy' => $result,
                'checked_at' => date('c'),
                'error' => $code,
                'error_description' => isset($res['error']['message']) ? $res['error']['message'] : ('HTTP ' . $status),
                'api_status' => $status,
                'api_error_body' => $res,
            ];
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
        $tokenResult = $this->getAccessToken($userId);
        $accessToken = $tokenResult['access_token'] ?? null;
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

    /**
     * @return array{access_token: ?string, error: ?string, error_description: ?string}
     */
    private function getAccessToken(int $userId): array
    {
        $refreshToken = $this->oauthRepo->getRefreshToken($userId);
        if ($refreshToken === null) {
            return ['access_token' => null, 'error' => null, 'error_description' => null];
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
                'ignore_errors' => true,
            ],
        ]));
        if ($res === false) {
            return ['access_token' => null, 'error' => 'request_failed', 'error_description' => 'Token refresh request failed'];
        }
        $data = json_decode($res, true);
        if (isset($data['error'])) {
            return ['access_token' => null, 'error' => $data['error'], 'error_description' => $data['error_description'] ?? ''];
        }
        $accessToken = $data['access_token'] ?? null;
        if ($accessToken !== null && isset($data['expires_in'])) {
            $this->oauthRepo->upsert($userId, 'google', $refreshToken, $accessToken, date('Y-m-d H:i:s', time() + $data['expires_in']));
        }
        return ['access_token' => $accessToken, 'error' => null, 'error_description' => null];
    }

    private function apiGet(string $accessToken, string $url): ?array
    {
        $r = $this->apiRequest('GET', $url, $accessToken, null);
        return $r['body'];
    }

    /**
     * @return array{body: ?array, status: int}
     */
    private function apiPostWithStatus(string $accessToken, string $url, array $body): array
    {
        return $this->apiRequest('POST', $url, $accessToken, $body);
    }

    /**
     * @return array{body: ?array, status: int}
     */
    private function apiRequest(string $method, string $url, string $accessToken, ?array $body): array
    {
        $opts = [
            'http' => [
                'method' => $method,
                'header' => 'Authorization: Bearer ' . $accessToken . "\r\n",
                'ignore_errors' => true,
            ],
        ];
        if ($body !== null) {
            $opts['http']['header'] .= "Content-Type: application/json\r\n";
            $opts['http']['content'] = json_encode($body);
        }
        $ctx = stream_context_create($opts);
        $res = @file_get_contents($url, false, $ctx);
        $status = 0;
        if (isset($http_response_header[0]) && preg_match('/HTTP\/\d\.\d\s+(\d+)/', $http_response_header[0], $m)) {
            $status = (int) $m[1];
        }
        return ['body' => $res !== false ? json_decode($res, true) : null, 'status' => $status];
    }

    private function apiPost(string $accessToken, string $url, array $body): ?array
    {
        $r = $this->apiPostWithStatus($accessToken, $url, $body);
        return $r['body'];
    }
}
