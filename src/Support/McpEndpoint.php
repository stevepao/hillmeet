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
        \Hillmeet\Mcp\Audit::logToolCall($tenant, $toolName, $durationMs, false, $requestId, $e->getMessage());
        throw $e;
    }
};

$hillmeetAdapter = new \Hillmeet\Adapter\StubHillmeetAdapter(\Hillmeet\Support\config('app.url', 'https://meet.hillwork.net'));
$hillmeetCreatePollInputSchema = [
    'type' => 'object',
    'properties' => [
        'title' => ['type' => 'string', 'description' => 'Poll title'],
        'description' => ['type' => 'string', 'description' => 'Optional description'],
        'timezone' => ['type' => 'string', 'description' => 'Timezone (e.g. America/Los_Angeles). Default: UTC'],
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
