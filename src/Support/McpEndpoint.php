<?php

declare(strict_types=1);

/**
 * McpEndpoint.php
 * MCP v1 HTTP handler (POST-only JSON-RPC). No session/CSRF.
 * Invoked from front controller when path is /mcp/v1.
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
            'description' => 'Time options; each start and end are ISO8601 timestamps in UTC (e.g. 2026-02-24T14:00:00Z)',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'start' => ['type' => 'string', 'description' => 'Start time ISO8601 UTC'],
                    'end' => ['type' => 'string', 'description' => 'End time ISO8601 UTC'],
                ],
                'required' => ['start', 'end'],
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
    ->setServerInfo('Hillmeet', '1.0.0', 'Hillmeet availability polls and calendar integration')
    ->setSession(new \Hillmeet\Mcp\Session\DatabaseSessionStore(3600))
    ->addRequestHandler(new \Hillmeet\Mcp\Handler\HillmeetCreatePollRequestHandler($hillmeetAdapter))
    ->addRequestHandler(new \Hillmeet\Mcp\Handler\HillmeetFindAvailabilityRequestHandler($hillmeetAdapter))
    ->addRequestHandler(new \Hillmeet\Mcp\Handler\HillmeetListNonrespondersRequestHandler($hillmeetAdapter))
    ->addRequestHandler(new \Hillmeet\Mcp\Handler\HillmeetClosePollRequestHandler($hillmeetAdapter))
    ->addTool(
        $hillmeetPingHandler,
        'hillmeet_ping',
        'Ping the Hillmeet service',
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
        'Create a Hillmeet availability poll',
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
        'Find best time slots for a poll given optional constraints (min attendees, preferred times, exclude emails)',
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
            throw new \BadMethodCallException('hillmeet_list_nonresponders is handled by HillmeetListNonrespondersRequestHandler');
        },
        'hillmeet_list_nonresponders',
        'List participants who have not yet responded to a poll',
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
            throw new \BadMethodCallException('hillmeet_close_poll is handled by HillmeetClosePollRequestHandler');
        },
        'hillmeet_close_poll',
        'Close a poll, optionally with a final chosen slot and notification',
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
