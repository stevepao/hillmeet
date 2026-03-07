<?php

declare(strict_types=1);

/**
 * McpToolsIntegrationTest.php
 * Purpose: End-to-end integration tests for MCP tools (create poll, find availability, list nonresponders, close poll, list polls, get poll).
 * Project: Hillmeet
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 */

namespace Hillmeet\Tests\Integration;

use Hillmeet\Adapter\DbHillmeetAdapter;
use Hillmeet\Mcp\Handler\HillmeetClosePollRequestHandler;
use Hillmeet\Mcp\Handler\HillmeetCreatePollRequestHandler;
use Hillmeet\Mcp\Handler\HillmeetFindAvailabilityRequestHandler;
use Hillmeet\Mcp\Handler\HillmeetListNonrespondersRequestHandler;
use Hillmeet\Mcp\Handler\HillmeetListPollsRequestHandler;
use Hillmeet\Mcp\McpContext;
use Hillmeet\Repositories\CalendarEventRepository;
use Hillmeet\Repositories\PollInviteRepository;
use Hillmeet\Repositories\PollParticipantRepository;
use Hillmeet\Repositories\PollRepository;
use Hillmeet\Repositories\UserRepository;
use Hillmeet\Repositories\VoteRepository;
use Hillmeet\Services\AvailabilityService;
use Hillmeet\Services\EmailService;
use Hillmeet\Services\NonresponderService;
use Hillmeet\Services\PollService;
use Hillmeet\Services\PollDetailsService;
use Hillmeet\Services\PollAccessService;
use Hillmeet\Support\Database;
use Hillmeet\Tests\Mcp\MockSession;
use Mcp\Schema\JsonRpc\Response;
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Schema\JsonRpc\Error;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for MCP tools: create_poll, find_availability, list_nonresponders, close_poll.
 * Requires config and database; writes to mcp_audit_log and asserts DB state.
 */
final class McpToolsIntegrationTest extends TestCase
{
    private const BASE_URL = 'https://meet.hillwork.net';

    private string $ownerEmail;
    private string $tenantId;
    private object $tenant;
    private PollRepository $pollRepository;
    private DbHillmeetAdapter $adapter;
    private HillmeetCreatePollRequestHandler $createPollHandler;
    private HillmeetFindAvailabilityRequestHandler $findAvailabilityHandler;
    private HillmeetListNonrespondersRequestHandler $listNonrespondersHandler;
    private HillmeetListPollsRequestHandler $listPollsHandler;
    private HillmeetClosePollRequestHandler $closePollHandler;
    private ?int $createdPollId = null;

    protected function setUp(): void
    {
        parent::setUp();
        $root = dirname(__DIR__, 2);
        if (!is_file($root . '/config/config.php')) {
            $this->markTestSkipped('config/config.php required for integration tests');
        }
        require_once $root . '/config/env.php';
        require_once $root . '/vendor/autoload.php';

        $config = require $root . '/config/config.php';
        if (empty($config['db']['host'] ?? null) || ($config['db']['name'] ?? '') === '') {
            $this->markTestSkipped('DB config required');
        }

        $this->ownerEmail = 'mcp-int-' . bin2hex(random_bytes(6)) . '@example.com';
        $userRepo = new UserRepository();
        $ownerUserId = (int) $userRepo->getOrCreateUserIdByEmail($this->ownerEmail);

        $this->tenantId = $this->randomUuid();
        $pdo = Database::get();
        $stmt = $pdo->prepare("INSERT INTO tenants (tenant_id, owner_user_id, name) VALUES (?, ?, ?)");
        $stmt->execute([$this->tenantId, $ownerUserId, 'MCP integration test']);

        $this->tenant = (object) [
            'tenant_id' => $this->tenantId,
            'owner_user_id' => $ownerUserId,
            'owner_email' => $this->ownerEmail,
            'name' => 'MCP integration test',
        ];

        $this->pollRepository = new PollRepository();
        $pollInviteRepo = new PollInviteRepository();
        $voteRepo = new VoteRepository();
        $participantRepo = new PollParticipantRepository();
        $this->adapter = new DbHillmeetAdapter(
            $userRepo,
            $this->pollRepository,
            $pollInviteRepo,
            new EmailService(),
            new AvailabilityService($this->pollRepository, $voteRepo, $participantRepo, $pollInviteRepo),
            new NonresponderService($this->pollRepository, $pollInviteRepo, $participantRepo, $voteRepo),
            new PollDetailsService($this->pollRepository, $pollInviteRepo, $userRepo),
            new PollAccessService($userRepo, $this->pollRepository, $pollInviteRepo, self::BASE_URL),
            self::BASE_URL,
            new PollService($this->pollRepository, $voteRepo, $participantRepo, $pollInviteRepo, new EmailService()),
            new CalendarEventRepository(),
        );

        $this->createPollHandler = new HillmeetCreatePollRequestHandler($this->adapter);
        $this->findAvailabilityHandler = new HillmeetFindAvailabilityRequestHandler($this->adapter);
        $this->listNonrespondersHandler = new HillmeetListNonrespondersRequestHandler($this->adapter);
        $this->listPollsHandler = new HillmeetListPollsRequestHandler($this->adapter);
        $this->closePollHandler = new HillmeetClosePollRequestHandler($this->adapter);
    }

    protected function tearDown(): void
    {
        if ($this->createdPollId !== null) {
            $this->pollRepository->deletePoll($this->createdPollId);
        }
        $pdo = Database::get();
        $pdo->prepare("DELETE FROM tenants WHERE tenant_id = ?")->execute([$this->tenantId]);
        McpContext::clear();
        parent::tearDown();
    }

    private function randomUuid(): string
    {
        $hex = bin2hex(random_bytes(16));
        return sprintf('%08s-%04s-%04s-%04s-%12s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20, 12));
    }

    private function getAuditRowsForTenant(): array
    {
        $stmt = Database::get()->prepare("SELECT tool, ok, error_code, request_id, duration_ms FROM mcp_audit_log WHERE tenant_id = ? ORDER BY id ASC");
        $stmt->execute([$this->tenantId]);
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    private function assertAuditHasTool(array $rows, string $tool, bool $ok, ?int $errorCode = null): void
    {
        $found = null;
        foreach ($rows as $row) {
            if ($row->tool === $tool) {
                $found = $row;
                break;
            }
        }
        $this->assertNotNull($found, "Expected mcp_audit_log row for tool {$tool}");
        $this->assertSame($ok ? 1 : 0, (int) $found->ok, "Audit row for {$tool} ok flag");
        if ($errorCode !== null) {
            $this->assertSame($errorCode, (int) $found->error_code, "Audit row for {$tool} error_code");
        }
    }

    public function testCreatePollFindAvailabilityListNonrespondersClosePollAndAudit(): void
    {
        McpContext::setTenant($this->tenant);
        $session = new MockSession();

        $options = [
            ['start' => '2026-03-10T14:00:00Z'],
            ['start' => '2026-03-10T15:00:00Z'],
            ['start' => '2026-03-10T16:00:00Z'],
        ];
        $participants = [
            ['contact' => 'alice-int@example.com'],
            ['contact' => 'bob-int@example.com'],
        ];

        $createRequest = new CallToolRequest('hillmeet_create_poll', [
            'title' => 'MCP integration poll',
            'duration_minutes' => 30,
            'options' => $options,
            'participants' => $participants,
        ]);
        $createRequest = $createRequest->withId(100);

        $createResponse = $this->createPollHandler->handle($createRequest, $session);

        $this->assertInstanceOf(Response::class, $createResponse);
        $this->assertSame(100, $createResponse->getId());
        $createResult = $createResponse->result;
        $this->assertInstanceOf(CallToolResult::class, $createResult);
        $this->assertFalse($createResult->isError);
        $this->assertNotNull($createResult->structuredContent);
        $content = $createResult->structuredContent;
        $this->assertArrayHasKey('poll_id', $content);
        $this->assertArrayHasKey('share_url', $content);
        $this->assertArrayHasKey('summary', $content);
        $pollSlug = $content['poll_id'];
        $this->assertNotEmpty($pollSlug);
        $this->assertMatchesRegularExpression('#^https?://[^/]+/poll/#', $content['share_url']);

        $poll = $this->pollRepository->findBySlug($pollSlug);
        $this->assertNotNull($poll);
        $this->createdPollId = $poll->id;

        $auditRows = $this->getAuditRowsForTenant();
        $this->assertCount(1, $auditRows);
        $this->assertAuditHasTool($auditRows, 'hillmeet_create_poll', true);

        $listPollsRequest = new CallToolRequest('hillmeet_list_polls', []);
        $listPollsRequest = $listPollsRequest->withId(104);
        $listPollsResponse = $this->listPollsHandler->handle($listPollsRequest, $session);
        $this->assertInstanceOf(Response::class, $listPollsResponse);
        $listPollsResult = $listPollsResponse->result;
        $this->assertNotNull($listPollsResult->structuredContent);
        $listPollsContent = $listPollsResult->structuredContent;
        $this->assertArrayHasKey('polls', $listPollsContent);
        $this->assertArrayHasKey('summary', $listPollsContent);
        $this->assertCount(1, $listPollsContent['polls']);
        $this->assertSame($pollSlug, $listPollsContent['polls'][0]['poll_id']);
        $this->assertSame('MCP integration poll', $listPollsContent['polls'][0]['title']);
        $this->assertSame('open', $listPollsContent['polls'][0]['status']);

        $findRequest = new CallToolRequest('hillmeet_find_availability', ['poll_id' => $pollSlug]);
        $findRequest = $findRequest->withId(101);
        $findResponse = $this->findAvailabilityHandler->handle($findRequest, $session);

        $this->assertInstanceOf(Response::class, $findResponse);
        $this->assertSame(101, $findResponse->getId());
        $findResult = $findResponse->result;
        $this->assertInstanceOf(CallToolResult::class, $findResult);
        $this->assertFalse($findResult->isError);
        $this->assertNotNull($findResult->structuredContent);
        $findContent = $findResult->structuredContent;
        $this->assertArrayHasKey('best_slots', $findContent);
        $this->assertArrayHasKey('summary', $findContent);
        $this->assertArrayHasKey('share_url', $findContent);
        $this->assertIsArray($findContent['best_slots']);
        $this->assertNotEmpty($findContent['share_url']);

        $auditRows = $this->getAuditRowsForTenant();
        $this->assertCount(3, $auditRows);
        $this->assertAuditHasTool($auditRows, 'hillmeet_list_polls', true);
        $this->assertAuditHasTool($auditRows, 'hillmeet_find_availability', true);

        $listRequest = new CallToolRequest('hillmeet_list_nonresponders', ['poll_id' => $pollSlug]);
        $listRequest = $listRequest->withId(102);
        $listResponse = $this->listNonrespondersHandler->handle($listRequest, $session);

        $this->assertInstanceOf(Response::class, $listResponse);
        $this->assertSame(102, $listResponse->getId());
        $listResult = $listResponse->result;
        $this->assertInstanceOf(CallToolResult::class, $listResult);
        $this->assertFalse($listResult->isError);
        $this->assertNotNull($listResult->structuredContent);
        $listContent = $listResult->structuredContent;
        $this->assertArrayHasKey('nonresponders', $listContent);
        $this->assertArrayHasKey('summary', $listContent);
        $this->assertIsArray($listContent['nonresponders']);
        $this->assertCount(2, $listContent['nonresponders']);
        $emails = array_column($listContent['nonresponders'], 'email');
        $this->assertContains('alice-int@example.com', $emails);
        $this->assertContains('bob-int@example.com', $emails);
        $this->assertStringContainsString("haven't responded", $listContent['summary']);

        $auditRows = $this->getAuditRowsForTenant();
        $this->assertCount(4, $auditRows);
        $this->assertAuditHasTool($auditRows, 'hillmeet_list_nonresponders', true);

        $pollOptions = $this->pollRepository->getOptions($poll->id);
        $this->assertNotEmpty($pollOptions);
        $firstOption = $pollOptions[0];
        $finalSlotStart = (new \DateTimeImmutable($firstOption->start_utc, new \DateTimeZone('UTC')))->format('c');
        $finalSlotEnd = (new \DateTimeImmutable($firstOption->end_utc, new \DateTimeZone('UTC')))->format('c');

        $closeRequest = new CallToolRequest('hillmeet_close_poll', [
            'poll_id' => $pollSlug,
            'final_slot' => ['start' => $finalSlotStart, 'end' => $finalSlotEnd],
            'notify' => false,
        ]);
        $closeRequest = $closeRequest->withId(103);
        $closeResponse = $this->closePollHandler->handle($closeRequest, $session);

        $this->assertInstanceOf(Response::class, $closeResponse);
        $this->assertSame(103, $closeResponse->getId());
        $closeResult = $closeResponse->result;
        $this->assertInstanceOf(CallToolResult::class, $closeResult);
        $this->assertFalse($closeResult->isError);
        $this->assertNotNull($closeResult->structuredContent);
        $closeContent = $closeResult->structuredContent;
        $this->assertArrayHasKey('closed', $closeContent);
        $this->assertArrayHasKey('final_slot', $closeContent);
        $this->assertArrayHasKey('summary', $closeContent);
        $this->assertTrue($closeContent['closed']);
        $this->assertIsArray($closeContent['final_slot']);
        $this->assertArrayHasKey('start', $closeContent['final_slot']);
        $this->assertArrayHasKey('end', $closeContent['final_slot']);
        $this->assertStringContainsString('Poll closed', $closeContent['summary']);

        $pollAfterClose = $this->pollRepository->findById($poll->id);
        $this->assertNotNull($pollAfterClose);
        $this->assertTrue($pollAfterClose->isLocked());
        $this->assertSame($firstOption->id, $pollAfterClose->locked_option_id);

        $auditRows = $this->getAuditRowsForTenant();
        $this->assertCount(5, $auditRows);
        $this->assertAuditHasTool($auditRows, 'hillmeet_close_poll', true);

        foreach ($auditRows as $row) {
            $this->assertNotEmpty($row->tool);
            $this->assertNotNull($row->request_id);
            $this->assertGreaterThanOrEqual(0, (int) $row->duration_ms);
        }
    }

    public function testFindAvailabilityWithInvalidPollIdLogsFailedAudit(): void
    {
        McpContext::setTenant($this->tenant);
        $session = new MockSession();

        $request = new CallToolRequest('hillmeet_find_availability', ['poll_id' => 'nonexistent-slug-xyz']);
        $request = $request->withId(200);
        $response = $this->findAvailabilityHandler->handle($request, $session);

        $this->assertInstanceOf(Response::class, $response);
        $result = $response->result;
        $this->assertInstanceOf(CallToolResult::class, $result);
        $this->assertFalse($result->isError);
        $content = $result->structuredContent;
        $this->assertSame([], $content['best_slots']);
        $this->assertStringContainsString('Poll not found or access denied', $content['summary']);

        $auditRows = $this->getAuditRowsForTenant();
        $this->assertCount(1, $auditRows);
        $this->assertSame('hillmeet_find_availability', $auditRows[0]->tool);
        $this->assertSame(1, (int) $auditRows[0]->ok);
    }

    public function testListNonrespondersMissingPollIdReturnsValidationErrorAndLogsAudit(): void
    {
        McpContext::setTenant($this->tenant);
        $session = new MockSession();

        $request = new CallToolRequest('hillmeet_list_nonresponders', []);
        $request = $request->withId(201);
        $response = $this->listNonrespondersHandler->handle($request, $session);

        $this->assertInstanceOf(Error::class, $response);
        $this->assertSame(201, $response->getId());
        $this->assertSame(-32010, $response->code);
        $this->assertSame('Validation error', $response->message);

        $auditRows = $this->getAuditRowsForTenant();
        $this->assertCount(1, $auditRows);
        $this->assertSame('hillmeet_list_nonresponders', $auditRows[0]->tool);
        $this->assertSame(0, (int) $auditRows[0]->ok);
        $this->assertSame(-32010, (int) $auditRows[0]->error_code);
    }
}
