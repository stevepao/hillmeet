<?php

declare(strict_types=1);

/**
 * McpEndpoint.php
 * Purpose: MCP v1 HTTP handler (POST-only JSON-RPC). No session/CSRF. Invoked from front controller when path is /mcp/v1.
 * Project: Hillmeet
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 */

namespace Hillmeet\Support;

use Mcp\Server;
use Mcp\Server\RequestContext;
use Mcp\Server\Transport\StreamableHttpTransport;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/config/env.php';

$tenant = \Hillmeet\Mcp\Auth::requireApiKey();
\Hillmeet\Mcp\McpContext::setTenant($tenant);

$psr17 = new Psr17Factory();
$creator = new ServerRequestCreator($psr17, $psr17, $psr17, $psr17);
$request = $creator->fromGlobals();

$hillmeetPingHandler = static function (RequestContext $ctx) use ($tenant): array {
    $toolName = 'hillmeet_ping';
    $start = hrtime(true);
    $requestId = $ctx->getRequest()->getId();
    try {
        $result = [
            'ok' => true,
            'service' => 'hillmeet',
            'time' => date('c'),
        ];
        $durationMs = (int) round((hrtime(true) - $start) / 1e6);
        \Hillmeet\Mcp\Audit::logToolCall($tenant, $toolName, $durationMs, true, $requestId);
        return $result;
        } catch (\Throwable $e) {
            $durationMs = (int) round((hrtime(true) - $start) / 1e6);
            \Hillmeet\Mcp\Audit::logToolCall($tenant, $toolName, $durationMs, false, $requestId, $e->getMessage(), -32050);
            throw $e;
        }
};

$hillmeetAdapter = new \Hillmeet\Adapter\DbHillmeetAdapter(
    new \Hillmeet\Repositories\UserRepository(),
    new \Hillmeet\Repositories\PollRepository(),
    new \Hillmeet\Repositories\PollInviteRepository(),
    new \Hillmeet\Services\EmailService(),
    new \Hillmeet\Services\AvailabilityService(
        new \Hillmeet\Repositories\PollRepository(),
        new \Hillmeet\Repositories\VoteRepository(),
        new \Hillmeet\Repositories\PollParticipantRepository(),
        new \Hillmeet\Repositories\PollInviteRepository(),
    ),
    new \Hillmeet\Services\NonresponderService(
        new \Hillmeet\Repositories\PollRepository(),
        new \Hillmeet\Repositories\PollInviteRepository(),
        new \Hillmeet\Repositories\PollParticipantRepository(),
        new \Hillmeet\Repositories\VoteRepository(),
    ),
    new \Hillmeet\Services\PollDetailsService(
        new \Hillmeet\Repositories\PollRepository(),
        new \Hillmeet\Repositories\PollInviteRepository(),
        new \Hillmeet\Repositories\UserRepository(),
    ),
    \Hillmeet\Support\config('app.url', 'https://meet.hillwork.net'),
    new \Hillmeet\Services\PollService(
        new \Hillmeet\Repositories\PollRepository(),
        new \Hillmeet\Repositories\VoteRepository(),
        new \Hillmeet\Repositories\PollParticipantRepository(),
        new \Hillmeet\Repositories\PollInviteRepository(),
        new \Hillmeet\Services\EmailService(),
    ),
    new \Hillmeet\Repositories\CalendarEventRepository(),
);
$hillmeetCreatePollInputSchema = [
    'type' => 'object',
    'properties' => [
        'title' => ['type' => 'string', 'description' => 'Poll title'],
        'description' => ['type' => 'string', 'description' => 'Optional description'],
        'timezone' => ['type' => 'string', 'description' => "Optional. IANA timezone (e.g. America/Los_Angeles). Defaults to organizer's timezone if set, otherwise UTC."],
        'duration_minutes' => ['type' => 'integer', 'description' => 'Duration of each option in minutes'],
        'options' => [
            'type' => 'array',
            'description' => 'Time options; each item has start (ISO8601 UTC). End is computed by server from start + duration_minutes.',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'start' => ['type' => 'string', 'description' => 'Start time ISO8601 UTC'],
                ],
                'required' => ['start'],
            ],
        ],
        'participants' => [
            'type' => 'array',
            'description' => 'Optional list of participants; contact MUST be an email address',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string', 'description' => 'Optional display name'],
                    'contact' => ['type' => 'string', 'description' => 'Email address'],
                ],
                'required' => ['contact'],
            ],
        ],
        'deadline' => ['type' => 'string', 'description' => 'Optional deadline (ISO8601)'],
        'idempotency_key' => ['type' => 'string', 'description' => 'Optional idempotency key'],
    ],
    'required' => ['title', 'duration_minutes', 'options'],
];

$server = Server::builder()
    ->setServerInfo('Hillmeet', '1.1.0', 'Hillmeet availability polls and calendar integration')
    ->setSession(new \Hillmeet\Mcp\Session\DatabaseSessionStore(3600))
    ->addRequestHandler(new \Hillmeet\Mcp\Handler\HillmeetCreatePollRequestHandler($hillmeetAdapter))
    ->addRequestHandler(new \Hillmeet\Mcp\Handler\HillmeetFindAvailabilityRequestHandler($hillmeetAdapter))
    ->addRequestHandler(new \Hillmeet\Mcp\Handler\HillmeetGetPollRequestHandler($hillmeetAdapter))
    ->addRequestHandler(new \Hillmeet\Mcp\Handler\HillmeetListNonrespondersRequestHandler($hillmeetAdapter))
    ->addRequestHandler(new \Hillmeet\Mcp\Handler\HillmeetListPollsRequestHandler($hillmeetAdapter))
    ->addRequestHandler(new \Hillmeet\Mcp\Handler\HillmeetClosePollRequestHandler($hillmeetAdapter))
    ->addTool(
        $hillmeetPingHandler,
        'hillmeet_ping',
        'Check that the Hillmeet MCP service is reachable. Call this first to verify the API key and connection.',
        null,
        ['type' => 'object', 'properties' => new \stdClass()],
        null,
        null,
        null,
    )
    ->addTool(
        static function (): never {
            throw new \BadMethodCallException('hillmeet_create_poll is handled by HillmeetCreatePollRequestHandler');
        },
        'hillmeet_create_poll',
        'Create an availability poll with time options and optional participants. Provide options as start times only (ISO8601 UTC); the server computes each option end from start + duration_minutes. Returns poll_id and share_url — share the share_url with participants so they can vote.',
        null,
        $hillmeetCreatePollInputSchema,
        null,
        null,
        null,
    )
    ->addTool(
        static function (): never {
            throw new \BadMethodCallException('hillmeet_find_availability is handled by HillmeetFindAvailabilityRequestHandler');
        },
        'hillmeet_find_availability',
        'Find the best time slots for a poll based on how many participants voted "available" for each option. Call this after participants have voted. Returns best_slots (start, end, available_count, total_invited), summary, and share_url. Optionally pass min_attendees, prefer_times (windows to boost), or exclude_emails.',
        null,
        [
            'type' => 'object',
            'properties' => [
                'poll_id' => ['type' => 'string', 'description' => 'Poll identifier (slug)'],
                'min_attendees' => ['type' => 'integer', 'description' => 'Optional minimum number of available attendees'],
                'prefer_times' => [
                    'type' => 'array',
                    'description' => 'Optional windows to boost (ISO8601 UTC)',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'start' => ['type' => 'string', 'description' => 'Start ISO8601 UTC'],
                            'end' => ['type' => 'string', 'description' => 'End ISO8601 UTC'],
                        ],
                        'required' => ['start', 'end'],
                    ],
                ],
                'exclude_emails' => [
                    'type' => 'array',
                    'description' => 'Optional emails to exclude from availability counts',
                    'items' => ['type' => 'string'],
                ],
            ],
            'required' => ['poll_id'],
        ],
        null,
        null,
        null,
    )
    ->addTool(
        static function (): never {
            throw new \BadMethodCallException('hillmeet_get_poll is handled by HillmeetGetPollRequestHandler');
        },
        'hillmeet_get_poll',
        'Fetch full details for one poll: title, description, timezone, duration_minutes, options (start/end in poll timezone), participants, status. Use poll_id from create_poll or list_polls. Only returns polls owned by the authenticated user.',
        null,
        [
            'type' => 'object',
            'properties' => [
                'poll_id' => ['type' => 'string', 'description' => 'Poll identifier (slug)'],
            ],
            'required' => ['poll_id'],
        ],
        null,
        null,
        null,
    )
    ->addTool(
        static function (): never {
            throw new \BadMethodCallException('hillmeet_list_nonresponders is handled by HillmeetListNonrespondersRequestHandler');
        },
        'hillmeet_list_nonresponders',
        'List participants who have not yet voted on a poll. Use this to see who still needs to respond; you can share the poll share_url with them or send a reminder. Returns nonresponders (email, name) and a summary.',
        null,
        [
            'type' => 'object',
            'properties' => [
                'poll_id' => ['type' => 'string', 'description' => 'Poll identifier (slug)'],
            ],
            'required' => ['poll_id'],
        ],
        null,
        null,
        null,
    )
    ->addTool(
        static function (): never {
            throw new \BadMethodCallException('hillmeet_list_polls is handled by HillmeetListPollsRequestHandler');
        },
        'hillmeet_list_polls',
        'List all polls owned by the authenticated user. Returns poll_id, title, created_at, timezone, status, share_url for each. Use poll_id from this list when calling find_availability, get_poll, list_nonresponders, or close_poll.',
        null,
        ['type' => 'object', 'properties' => new \stdClass()],
        null,
        null,
        null,
    )
    ->addTool(
        static function (): never {
            throw new \BadMethodCallException('hillmeet_close_poll is handled by HillmeetClosePollRequestHandler');
        },
        'hillmeet_close_poll',
        'Close (lock) a poll, optionally to a final chosen time slot. Provide final_slot (start and end, ISO8601 UTC) matching one of the poll options. If notify is true, participants are emailed and a Google Calendar event can be created. Call this when the organizer has decided the meeting time.',
        null,
        [
            'type' => 'object',
            'properties' => [
                'poll_id' => ['type' => 'string', 'description' => 'Poll identifier (slug)'],
                'final_slot' => [
                    'type' => 'object',
                    'description' => 'Optional chosen slot (ISO8601 start/end in UTC)',
                    'properties' => [
                        'start' => ['type' => 'string', 'description' => 'Start ISO8601 UTC'],
                        'end' => ['type' => 'string', 'description' => 'End ISO8601 UTC'],
                    ],
                    'required' => ['start', 'end'],
                ],
                'notify' => ['type' => 'boolean', 'description' => 'Optional; if true, notify participants (default false)'],
            ],
            'required' => ['poll_id'],
        ],
        null,
        null,
        null,
    )
    ->build();

$transport = new StreamableHttpTransport($request, $psr17, $psr17);
$response = $server->run($transport);

http_response_code($response->getStatusCode());
foreach ($response->getHeaders() as $name => $values) {
    foreach ($values as $value) {
        header(sprintf('%s: %s', $name, $value), false);
    }
}
echo $response->getBody()->getContents();
