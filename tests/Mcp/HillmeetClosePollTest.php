<?php

declare(strict_types=1);

/**
 * HillmeetClosePollTest.php
 * Purpose: MCP tool tests for hillmeet_close_poll.
 * Project: Hillmeet
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 */

namespace Hillmeet\Tests\Mcp;

use Hillmeet\Adapter\StubHillmeetAdapter;
use Hillmeet\Mcp\Handler\HillmeetClosePollRequestHandler;
use Hillmeet\Mcp\McpContext;
use Mcp\Schema\JsonRpc\Error;
use Mcp\Schema\JsonRpc\Response;
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Schema\Result\CallToolResult;
use PHPUnit\Framework\TestCase;

/**
 * MCP tool tests for hillmeet_close_poll.
 */
final class HillmeetClosePollTest extends TestCase
{
    private const OWNER_EMAIL = 'owner@example.com';

    protected function setUp(): void
    {
        parent::setUp();
        McpContext::setTenant((object) [
            'tenant_id' => 'test-tenant-close',
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
     * tools/call hillmeet_close_poll with poll_id and optional final_slot returns closed, final_slot, summary.
     */
    public function testClosePollSuccessReturnsClosedFinalSlotSummary(): void
    {
        $request = new CallToolRequest('hillmeet_close_poll', [
            'poll_id' => 'my-poll-slug',
            'final_slot' => ['start' => '2026-03-01T14:00:00Z', 'end' => '2026-03-01T14:30:00Z'],
            'notify' => false,
        ]);
        $request = $request->withId(12);

        $adapter = new StubHillmeetAdapter();
        $handler = new HillmeetClosePollRequestHandler($adapter, static function (): void {});
        $session = new MockSession();

        $response = $handler->handle($request, $session);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(12, $response->getId());
        $result = $response->result;
        $this->assertInstanceOf(CallToolResult::class, $result);
        $this->assertFalse($result->isError);
        $content = $result->structuredContent;
        $this->assertNotNull($content);
        $this->assertArrayHasKey('closed', $content);
        $this->assertArrayHasKey('final_slot', $content);
        $this->assertArrayHasKey('summary', $content);
        $this->assertTrue($content['closed']);
        $this->assertIsArray($content['final_slot']);
        $this->assertArrayHasKey('start', $content['final_slot']);
        $this->assertArrayHasKey('end', $content['final_slot']);
        $this->assertNotEmpty($content['summary']);
    }

    /**
     * tools/call without poll_id returns JSON-RPC error -32010.
     */
    public function testClosePollMissingPollIdReturnsValidationError(): void
    {
        $request = new CallToolRequest('hillmeet_close_poll', []);
        $request = $request->withId(13);

        $adapter = new StubHillmeetAdapter();
        $handler = new HillmeetClosePollRequestHandler($adapter, static function (): void {});
        $session = new MockSession();

        $response = $handler->handle($request, $session);

        $this->assertInstanceOf(Error::class, $response);
        $this->assertSame(13, $response->getId());
        $this->assertSame(-32010, $response->code);
        $this->assertSame('Validation error', $response->message);
    }
}
