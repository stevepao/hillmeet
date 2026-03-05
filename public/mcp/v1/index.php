<?php

declare(strict_types=1);

/**
 * MCP v1 endpoint (Apache/PHP-FPM).
 * POST-only JSON-RPC; responds to initialize, tools/list, tools/call.
 * No long-running daemon, no SSE.
 */

use Mcp\Server;
use Mcp\Server\Transport\StreamableHttpTransport;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';

$psr17 = new Psr17Factory();
$creator = new ServerRequestCreator($psr17, $psr17, $psr17, $psr17);
$request = $creator->fromGlobals();

$server = Server::builder()
    ->setServerInfo('Hillmeet', '1.0.0', 'Hillmeet availability polls and calendar integration')
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
