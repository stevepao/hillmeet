<?php

declare(strict_types=1);

namespace Hillmeet\Tests\Mcp;

use Hillmeet\Adapter\StubHillmeetAdapter;
use Hillmeet\Mcp\Handler\HillmeetListNonrespondersRequestHandler;
use Hillmeet\Mcp\McpContext;
use Mcp\Schema\JsonRpc\Error;
use Mcp\Schema\JsonRpc\Response;
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Schema\Result\CallToolResult;
use PHPUnit\Framework\TestCase;

/**
 * MCP tool tests for hillmeet_list_nonresponders.
 */
final class HillmeetListNonrespondersTest extends TestCase
{
    private const OWNER_EMAIL = 'owner@example.com';

    protected function setUp(): void
    {
        parent::setUp();
        McpContext::setTenant((object) [
            'tenant_id' => 'test-tenant-list-nr',
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
     * tools/call hillmeet_list_nonresponders with valid poll_id returns nonresponders and summary.
     */
    public function testListNonrespondersSuccessReturnsNonrespondersAndSummary(): void
    {
        $request = new CallToolRequest('hillmeet_list_nonresponders', ['poll_id' => 'my-poll-slug']);
        $request = $request->withId(10);

        $adapter = new StubHillmeetAdapter();
        $handler = new HillmeetListNonrespondersRequestHandler($adapter, static function (): void {});
        $session = new MockSession();

        $response = $handler->handle($request, $session);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(10, $response->getId());
        $result = $response->result;
        $this->assertInstanceOf(CallToolResult::class, $result);
        $this->assertFalse($result->isError);
        $this->assertNotNull($result->structuredContent);
        $content = $result->structuredContent;
        $this->assertArrayHasKey('nonresponders', $content);
        $this->assertArrayHasKey('summary', $content);
        $this->assertIsArray($content['nonresponders']);
        $this->assertNotEmpty($content['nonresponders']);
        $this->assertArrayHasKey('email', $content['nonresponders'][0]);
        $this->assertNotEmpty($content['summary']);
    }

    /**
     * tools/call without poll_id returns JSON-RPC error -32010.
     */
    public function testListNonrespondersMissingPollIdReturnsValidationError(): void
    {
        $request = new CallToolRequest('hillmeet_list_nonresponders', []);
        $request = $request->withId(11);

        $adapter = new StubHillmeetAdapter();
        $handler = new HillmeetListNonrespondersRequestHandler($adapter, static function (): void {});
        $session = new MockSession();

        $response = $handler->handle($request, $session);

        $this->assertInstanceOf(Error::class, $response);
        $this->assertSame(11, $response->getId());
        $this->assertSame(-32010, $response->code);
        $this->assertSame('Validation error', $response->message);
    }
}
