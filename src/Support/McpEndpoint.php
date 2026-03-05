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

$server = Server::builder()
    ->setServerInfo('Hillmeet', '1.0.0', 'Hillmeet availability polls and calendar integration')
    ->setSession(new \Hillmeet\Mcp\Session\DatabaseSessionStore(3600))
    ->addTool(
        $hillmeetPingHandler,
        'hillmeet_ping',
        'Ping the Hillmeet service',
        null,
        ['type' => 'object', 'properties' => []],
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
