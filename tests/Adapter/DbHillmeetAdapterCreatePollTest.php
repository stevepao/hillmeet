<?php

declare(strict_types=1);

namespace Hillmeet\Tests\Adapter;

use Hillmeet\Adapter\DbHillmeetAdapter;
use Hillmeet\Repositories\PollInviteRepository;
use Hillmeet\Repositories\PollRepository;
use Hillmeet\Repositories\UserRepository;
use Hillmeet\Services\EmailService;
use Hillmeet\Support\Database;
use PHPUnit\Framework\TestCase;

/**
 * Integration-style tests for DbHillmeetAdapter::createPoll.
 * Requires config and database; skips if config is not available.
 */
final class DbHillmeetAdapterCreatePollTest extends TestCase
{
    private ?string $ownerEmail = null;
    private ?string $tenantId = null;
    private ?int $createdPollId = null;
    private UserRepository $userRepository;
    private PollRepository $pollRepository;
    private PollInviteRepository $pollInviteRepository;
    private DbHillmeetAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();
        $root = dirname(__DIR__, 2);
        if (!is_file($root . '/config/config.php')) {
            $this->markTestSkipped('config/config.php required');
        }
        require_once $root . '/config/env.php';
        require_once $root . '/vendor/autoload.php';
        $this->ownerEmail = 'db-adapter-test-' . bin2hex(random_bytes(4)) . '@example.com';
        $this->userRepository = new UserRepository();
        $this->pollRepository = new PollRepository();
        $this->pollInviteRepository = new PollInviteRepository();
        $this->adapter = new DbHillmeetAdapter(
            $this->userRepository,
            $this->pollRepository,
            $this->pollInviteRepository,
            new EmailService(),
            'https://meet.hillwork.net',
        );
        $userId = $this->userRepository->getOrCreateUserIdByEmail($this->ownerEmail);
        $this->tenantId = $this->uuid();
        $pdo = Database::get();
        $pdo->prepare("INSERT INTO tenants (tenant_id, owner_user_id, name) VALUES (?, ?, ?)")
            ->execute([$this->tenantId, $userId, 'Test tenant']);
    }

    protected function tearDown(): void
    {
        if ($this->createdPollId !== null) {
            $this->pollRepository->deletePoll($this->createdPollId);
        }
        if ($this->tenantId !== null) {
            try {
                Database::get()->prepare("DELETE FROM tenant_poll_idempotency WHERE tenant_id = ?")->execute([$this->tenantId]);
                Database::get()->prepare("DELETE FROM tenants WHERE tenant_id = ?")->execute([$this->tenantId]);
            } catch (\Throwable) {
                // ignore
            }
        }
        parent::tearDown();
    }

    private function uuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0xffff),
        );
    }

    public function testCreatePollInsertsPollOptionsAndInvitesAndReturnsResult(): void
    {
        $payload = [
            'title' => 'Team standup',
            'timezone' => 'UTC',
            'duration_minutes' => 30,
            'options' => [
                ['start' => '2026-02-24T14:00:00Z', 'end' => '2026-02-24T14:30:00Z'],
                ['start' => '2026-02-24T15:00:00Z', 'end' => '2026-02-24T15:30:00Z'],
            ],
            'participants' => [
                ['email' => 'alice@example.com'],
                ['email' => 'bob@example.com'],
            ],
            '_tenant_id' => $this->tenantId,
        ];

        $result = $this->adapter->createPoll($this->ownerEmail, $payload);

        $this->assertNotEmpty($result->pollId);
        $this->assertStringStartsWith('https://meet.hillwork.net/poll/', $result->shareUrl);
        $this->assertStringContainsString($result->pollId, $result->shareUrl);
        $this->assertNotEmpty($result->summary);

        $poll = $this->pollRepository->findBySlug($result->pollId);
        $this->assertNotNull($poll);
        $this->createdPollId = $poll->id;

        $user = $this->userRepository->findByEmail($this->ownerEmail);
        $this->assertNotNull($user);
        $this->assertSame($user->id, $poll->organizer_id);

        $options = $this->pollRepository->getOptions($poll->id);
        $this->assertCount(2, $options);
        $this->assertSame('2026-02-24 14:00:00', $options[0]->start_utc);
        $this->assertSame('2026-02-24 14:30:00', $options[0]->end_utc);
        $this->assertSame('2026-02-24 15:00:00', $options[1]->start_utc);
        $this->assertSame('2026-02-24 15:30:00', $options[1]->end_utc);

        $invites = $this->pollInviteRepository->listInvites($poll->id);
        $emails = array_map(fn ($i) => strtolower($i->email), $invites);
        $this->assertContains('alice@example.com', $emails);
        $this->assertContains('bob@example.com', $emails);
    }

    public function testCreatePollIdempotencyReturnsSamePollAndDoesNotCreateExtraRows(): void
    {
        $idempotencyKey = 'idem-' . bin2hex(random_bytes(4));
        $payload = [
            'title' => 'Idempotent poll',
            'timezone' => 'UTC',
            'duration_minutes' => 60,
            'options' => [
                ['start' => '2026-03-01T10:00:00Z', 'end' => '2026-03-01T11:00:00Z'],
                ['start' => '2026-03-01T11:00:00Z', 'end' => '2026-03-01T12:00:00Z'],
            ],
            'participants' => [
                ['email' => 'one@example.com'],
                ['email' => 'two@example.com'],
            ],
            '_tenant_id' => $this->tenantId,
            'idempotency_key' => $idempotencyKey,
        ];

        $result1 = $this->adapter->createPoll($this->ownerEmail, $payload);
        $poll1 = $this->pollRepository->findBySlug($result1->pollId);
        $this->assertNotNull($poll1);
        $this->createdPollId = $poll1->id;

        $result2 = $this->adapter->createPoll($this->ownerEmail, $payload);

        $this->assertSame($result1->pollId, $result2->pollId);

        $optionsCount = count($this->pollRepository->getOptions($poll1->id));
        $this->assertSame(2, $optionsCount, 'Should still have exactly 2 options (no duplicate poll)');

        $poll2 = $this->pollRepository->findBySlug($result2->pollId);
        $this->assertSame($poll1->id, $poll2->id);
    }
}
