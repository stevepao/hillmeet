<?php

declare(strict_types=1);

namespace Hillmeet\Tests\Mcp;

use Hillmeet\Adapter\StubHillmeetAdapter;
use Hillmeet\Mcp\Handler\HillmeetListPollsRequestHandler;
use Hillmeet\Mcp\McpContext;
use Mcp\Schema\JsonRpc\Error;
use Mcp\Schema\JsonRpc\Response;
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Schema\Result\CallToolResult;
use PHPUnit\Framework\TestCase;

/**
 * MCP tool tests for hillmeet_list_polls.
 */
final class HillmeetListPollsTest extends TestCase
{
    private const OWNER_EMAIL = 'owner@example.com';

    protected function setUp(): void
    {
        parent::setUp();
        McpContext::setTenant((object) [
            'tenant_id' => 'test-tenant-list-polls',
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
     * tools/call hillmeet_list_polls (no args) returns polls and summary in JSON-RPC result.
     */
    public function testListPollsSuccessReturnsPollsAndSummary(): void
    {
        $request = new CallToolRequest('hillmeet_list_polls', []);
        $request = $request->withId(20);

        $adapter = new StubHillmeetAdapter('https://meet.hillwork.net');
        $handler = new HillmeetListPollsRequestHandler($adapter, static function (): void {});
        $session = new MockSession();

        $response = $handler->handle($request, $session);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(20, $response->getId());
        $result = $response->result;
        $this->assertInstanceOf(CallToolResult::class, $result);
        $this->assertFalse($result->isError);
        $this->assertNotNull($result->structuredContent);
        $content = $result->structuredContent;
        $this->assertArrayHasKey('polls', $content);
        $this->assertArrayHasKey('summary', $content);
        $this->assertIsArray($content['polls']);
        $this->assertCount(1, $content['polls']);
        $poll = $content['polls'][0];
        $this->assertArrayHasKey('poll_id', $poll);
        $this->assertArrayHasKey('title', $poll);
        $this->assertArrayHasKey('created_at', $poll);
        $this->assertArrayHasKey('timezone', $poll);
        $this->assertArrayHasKey('status', $poll);
        $this->assertArrayHasKey('share_url', $poll);
        $this->assertSame('open', $poll['status']);
        $this->assertMatchesRegularExpression('#^https?://[^/]+/poll/#', $poll['share_url']);
        $this->assertNotEmpty($content['summary']);
    }

    /**
     * No tenant in context returns JSON-RPC error -32050.
     */
    public function testListPollsNoTenantReturnsInternalError(): void
    {
        McpContext::clear();
        $request = new CallToolRequest('hillmeet_list_polls', []);
        $request = $request->withId(21);

        $adapter = new StubHillmeetAdapter();
        $handler = new HillmeetListPollsRequestHandler($adapter, static function (): void {});
        $session = new MockSession();

        $response = $handler->handle($request, $session);

        $this->assertInstanceOf(Error::class, $response);
        $this->assertSame(21, $response->getId());
        $this->assertSame(-32050, $response->code);
        $this->assertSame('No tenant or owner email in context', $response->message);
    }
}
