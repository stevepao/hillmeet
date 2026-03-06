<?php

declare(strict_types=1);

namespace Hillmeet\Tests\Mcp;

use Hillmeet\Adapter\StubHillmeetAdapter;
use Hillmeet\Mcp\Handler\HillmeetGetPollRequestHandler;
use Hillmeet\Mcp\McpContext;
use Mcp\Schema\JsonRpc\Error;
use Mcp\Schema\JsonRpc\Response;
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Schema\Result\CallToolResult;
use PHPUnit\Framework\TestCase;

/**
 * MCP tool tests for hillmeet_get_poll.
 */
final class HillmeetGetPollTest extends TestCase
{
    private const OWNER_EMAIL = 'owner@example.com';

    protected function setUp(): void
    {
        parent::setUp();
        McpContext::setTenant((object) [
            'tenant_id' => 'test-tenant-get-poll',
            'owner_user_id' => 1,
            'owner_email' => self::OWNER_EMAIL,
            'name' => 'Test',
        ]);
    }

    protected function tearDown(): void
    {
        McpContext::clear();
        parent::tearDown();
    }

    /**
     * tools/call hillmeet_get_poll with valid poll_id returns poll details (options, participants, status).
     */
    public function testGetPollSuccessReturnsPollDetails(): void
    {
        $request = new CallToolRequest('hillmeet_get_poll', ['poll_id' => 'my-poll-slug']);
        $request = $request->withId(30);

        $adapter = new StubHillmeetAdapter();
        $handler = new HillmeetGetPollRequestHandler($adapter, static function (): void {});
        $session = new MockSession();

        $response = $handler->handle($request, $session);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(30, $response->getId());
        $result = $response->result;
        $this->assertInstanceOf(CallToolResult::class, $result);
        $this->assertFalse($result->isError);
        $this->assertNotNull($result->structuredContent);
        $content = $result->structuredContent;
        $this->assertArrayHasKey('poll_id', $content);
        $this->assertArrayHasKey('title', $content);
        $this->assertArrayHasKey('timezone', $content);
        $this->assertArrayHasKey('status', $content);
        $this->assertArrayHasKey('created_at', $content);
        $this->assertArrayHasKey('options', $content);
        $this->assertArrayHasKey('participants', $content);
        $this->assertSame('open', $content['status']);
        $this->assertIsArray($content['options']);
        $this->assertCount(2, $content['options']);
        $this->assertArrayHasKey('start', $content['options'][0]);
        $this->assertArrayHasKey('end', $content['options'][0]);
        $this->assertIsArray($content['participants']);
        $this->assertCount(2, $content['participants']);
        $this->assertSame('alice@example.com', $content['participants'][0]['email']);
        $this->assertSame('Alice', $content['participants'][0]['name'] ?? null);
    }

    /**
     * Missing poll_id returns JSON-RPC error -32010.
     */
    public function testGetPollMissingPollIdReturnsValidationError(): void
    {
        $request = new CallToolRequest('hillmeet_get_poll', []);
        $request = $request->withId(31);

        $adapter = new StubHillmeetAdapter();
        $handler = new HillmeetGetPollRequestHandler($adapter, static function (): void {});
        $session = new MockSession();

        $response = $handler->handle($request, $session);

        $this->assertInstanceOf(Error::class, $response);
        $this->assertSame(31, $response->getId());
        $this->assertSame(-32010, $response->code);
        $this->assertSame('Validation error', $response->message);
        $this->assertIsArray($response->data);
        $found = false;
        foreach ($response->data as $item) {
            if (isset($item['field']) && $item['field'] === 'poll_id') {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);
    }
}
