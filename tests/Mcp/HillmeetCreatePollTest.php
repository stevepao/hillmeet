<?php

declare(strict_types=1);

namespace Hillmeet\Tests\Mcp;

use Hillmeet\Adapter\StubHillmeetAdapter;
use Hillmeet\Mcp\Handler\HillmeetCreatePollRequestHandler;
use Hillmeet\Mcp\McpContext;
use Mcp\Schema\JsonRpc\Response;
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Schema\JsonRpc\Error;
use Mcp\Server\Session\SessionInterface;
use Mcp\Server\Session\SessionStoreInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Minimal session mock for tests (handler does not use session).
 */
final class MockSession implements SessionInterface
{
    public function __construct(private readonly Uuid $id = new \Symfony\Component\Uid\UuidV4()) {}

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function save(): bool
    {
        return true;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $default;
    }

    public function set(string $key, mixed $value, bool $overwrite = true): void {}

    public function has(string $key): bool
    {
        return false;
    }

    public function forget(string $key): void {}

    public function clear(): void {}

    public function pull(string $key, mixed $default = null): mixed
    {
        return $default;
    }

    public function all(): array
    {
        return [];
    }

    public function hydrate(array $attributes): void {}

    public function getStore(): SessionStoreInterface
    {
        return new MockSessionStore();
    }

    public function jsonSerialize(): array
    {
        return [];
    }
}

final class MockSessionStore implements SessionStoreInterface
{
    public function exists(Uuid $id): bool
    {
        return false;
    }

    public function read(Uuid $id): string|false
    {
        return false;
    }

    public function write(Uuid $id, string $data): bool
    {
        return true;
    }

    public function destroy(Uuid $id): bool
    {
        return true;
    }

    public function gc(): array
    {
        return [];
    }
}

/**
 * Integration-style tests for hillmeet_create_poll MCP tool.
 * Exercises the handler with a resolved tenant (ownerEmail) and stub adapter.
 */
final class HillmeetCreatePollTest extends TestCase
{
    private const OWNER_EMAIL = 'owner@example.com';

    protected function setUp(): void
    {
        parent::setUp();
        McpContext::setTenant((object) [
            'tenant_id' => 'test-tenant-id',
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

    private function createSession(): SessionInterface
    {
        return new MockSession();
    }

    /**
     * Valid tools/call request: title, timezone, duration_minutes, two options (UTC), two participants, deadline, idempotency_key.
     */
    public function testCreatePollSuccessReturnsPollIdShareUrlSummary(): void
    {
        $request = new CallToolRequest('hillmeet_create_poll', [
            'title' => 'Team standup',
            'description' => 'Weekly sync',
            'timezone' => 'America/Los_Angeles',
            'duration_minutes' => 30,
            'options' => [
                ['start' => '2026-02-24T14:00:00Z', 'end' => '2026-02-24T14:30:00Z'],
                ['start' => '2026-02-24T15:00:00Z', 'end' => '2026-02-24T15:30:00Z'],
            ],
            'participants' => [
                ['name' => 'Alice', 'contact' => 'alice@example.com'],
                ['name' => 'Bob', 'contact' => 'bob@example.com'],
            ],
            'deadline' => '2026-02-23T23:59:59Z',
            'idempotency_key' => 'test-key-123',
        ]);
        $request = $request->withId(42);

        $adapter = new StubHillmeetAdapter('https://meet.hillwork.net');
        $handler = new HillmeetCreatePollRequestHandler($adapter, static function (): void {}); // no-op audit in tests
        $session = $this->createSession();

        $response = $handler->handle($request, $session);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(42, $response->getId());
        $result = $response->result;
        $this->assertInstanceOf(CallToolResult::class, $result);
        $this->assertFalse($result->isError);
        $this->assertNotNull($result->structuredContent);
        $content = $result->structuredContent;
        $this->assertArrayHasKey('poll_id', $content);
        $this->assertArrayHasKey('share_url', $content);
        $this->assertArrayHasKey('summary', $content);
        $this->assertNotEmpty($content['poll_id']);
        $this->assertMatchesRegularExpression('#^https?://[^/]+/poll/#', $content['share_url'], 'share_url should look like a Hillmeet poll URL');
        $this->assertNotEmpty($content['summary']);
    }

    /**
     * Invalid participants.contact (not an email) returns JSON-RPC error -32010 with field-level data.
     */
    public function testCreatePollInvalidParticipantEmailReturnsValidationError(): void
    {
        $request = new CallToolRequest('hillmeet_create_poll', [
            'title' => 'Standup',
            'duration_minutes' => 30,
            'options' => [
                ['start' => '2026-02-24T14:00:00Z', 'end' => '2026-02-24T14:30:00Z'],
            ],
            'participants' => [
                ['contact' => 'alice@example.com'],
                ['contact' => 'not-an-email'],
            ],
        ]);
        $request = $request->withId(99);

        $adapter = new StubHillmeetAdapter();
        $handler = new HillmeetCreatePollRequestHandler($adapter, static function (): void {}); // no-op audit in tests
        $session = $this->createSession();

        $response = $handler->handle($request, $session);

        $this->assertInstanceOf(Error::class, $response);
        $this->assertSame(99, $response->getId());
        $this->assertSame(-32010, $response->code);
        $this->assertSame('Validation error', $response->message);
        $this->assertIsArray($response->data);
        $this->assertNotEmpty($response->data);
        $found = false;
        foreach ($response->data as $item) {
            if (isset($item['field']) && str_contains((string) $item['field'], 'participants') && isset($item['reason'])) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Error data should include a participants-related field and reason');
    }
}
