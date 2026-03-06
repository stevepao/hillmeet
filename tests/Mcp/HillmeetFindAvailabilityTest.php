<?php

declare(strict_types=1);

namespace Hillmeet\Tests\Mcp;

use Hillmeet\Adapter\StubHillmeetAdapter;
use Hillmeet\Mcp\Handler\HillmeetFindAvailabilityRequestHandler;
use Hillmeet\Mcp\McpContext;
use Mcp\Schema\JsonRpc\Response;
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Schema\JsonRpc\Error;
use Mcp\Server\Session\SessionInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Minimal session mock for find_availability tests.
 */
final class FindAvailabilityMockSession implements SessionInterface
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

    public function getStore(): \Mcp\Server\Session\SessionStoreInterface
    {
        return new class implements \Mcp\Server\Session\SessionStoreInterface {
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
        };
    }

    public function jsonSerialize(): array
    {
        return [];
    }
}

/**
 * High-level test for hillmeet_find_availability MCP tool.
 * Uses StubHillmeetAdapter to return a fixed result without database.
 */
final class HillmeetFindAvailabilityTest extends TestCase
{
    private const OWNER_EMAIL = 'owner@example.com';

    protected function setUp(): void
    {
        parent::setUp();
        McpContext::setTenant((object) [
            'tenant_id' => 'test-tenant-find-avail',
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
     * tools/call hillmeet_find_availability with poll_id, min_attendees, prefer_times, exclude_emails.
     * Assert HTTP 200 shape: result.best_slots, result.summary, result.share_url.
     */
    public function testFindAvailabilitySuccessReturnsBestSlotsSummaryShareUrl(): void
    {
        $request = new CallToolRequest('hillmeet_find_availability', [
            'poll_id' => 'test-poll-slug',
            'min_attendees' => 1,
            'prefer_times' => [
                ['start' => '2026-02-24T14:00:00Z', 'end' => '2026-02-24T15:00:00Z'],
            ],
            'exclude_emails' => ['skip@example.com'],
        ]);
        $request = $request->withId(7);

        $adapter = new StubHillmeetAdapter('https://meet.hillwork.net');
        $handler = new HillmeetFindAvailabilityRequestHandler($adapter, static function (): void {});
        $session = new FindAvailabilityMockSession();

        $response = $handler->handle($request, $session);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(7, $response->getId());
        $result = $response->result;
        $this->assertInstanceOf(CallToolResult::class, $result);
        $this->assertFalse($result->isError);
        $this->assertNotNull($result->structuredContent);
        $content = $result->structuredContent;
        $this->assertArrayHasKey('best_slots', $content);
        $this->assertArrayHasKey('summary', $content);
        $this->assertArrayHasKey('share_url', $content);
        $this->assertIsArray($content['best_slots']);
        $this->assertNotEmpty($content['best_slots']);
        $slot = $content['best_slots'][0];
        $this->assertArrayHasKey('start', $slot);
        $this->assertArrayHasKey('end', $slot);
        $this->assertArrayHasKey('available_count', $slot);
        $this->assertArrayHasKey('total_invited', $slot);
        $this->assertArrayHasKey('available_emails', $slot);
        $this->assertArrayHasKey('unavailable_emails', $slot);
        $this->assertNotEmpty($content['summary']);
        $this->assertNotEmpty($content['share_url']);
        $this->assertMatchesRegularExpression('#^https?://[^/]+/poll/#', $content['share_url']);
    }

    public function testFindAvailabilityMissingPollIdReturnsValidationError(): void
    {
        $request = new CallToolRequest('hillmeet_find_availability', []);
        $request = $request->withId(8);

        $adapter = new StubHillmeetAdapter();
        $handler = new HillmeetFindAvailabilityRequestHandler($adapter, static function (): void {});
        $session = new FindAvailabilityMockSession();

        $response = $handler->handle($request, $session);

        $this->assertInstanceOf(Error::class, $response);
        $this->assertSame(8, $response->getId());
        $this->assertSame(-32010, $response->code);
        $this->assertSame('Validation error', $response->message);
    }

    /**
     * Invalid min_attendees (non-integer) returns JSON-RPC -32010 with field-level details.
     */
    public function testFindAvailabilityInvalidMinAttendeesReturnsValidationError(): void
    {
        $request = new CallToolRequest('hillmeet_find_availability', [
            'poll_id' => 'some-slug',
            'min_attendees' => 'two',
        ]);
        $request = $request->withId(9);

        $adapter = new StubHillmeetAdapter();
        $handler = new HillmeetFindAvailabilityRequestHandler($adapter, static function (): void {});
        $session = new FindAvailabilityMockSession();

        $response = $handler->handle($request, $session);

        $this->assertInstanceOf(Error::class, $response);
        $this->assertSame(9, $response->getId());
        $this->assertSame(-32010, $response->code);
        $this->assertSame('Validation error', $response->message);
        $this->assertIsArray($response->data);
        $found = false;
        foreach ($response->data as $item) {
            if (isset($item['field']) && $item['field'] === 'min_attendees' && isset($item['reason'])) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Error data should include field min_attendees with reason');
    }
}
