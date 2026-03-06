<?php

declare(strict_types=1);

namespace Hillmeet\Tests\Repositories;

use Hillmeet\Repositories\PollRepository;
use Hillmeet\Repositories\UserRepository;
use PHPUnit\Framework\TestCase;

/**
 * Tests for PollRepository::findPollsOwnedByUser.
 * Requires config and database; skips if not available.
 */
final class PollRepositoryFindPollsOwnedByUserTest extends TestCase
{
    private ?int $userId = null;
    private array $pollIds = [];
    private PollRepository $pollRepository;
    private UserRepository $userRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $root = dirname(__DIR__, 2);
        if (!is_file($root . '/config/config.php')) {
            $this->markTestSkipped('config/config.php required');
        }
        require_once $root . '/config/env.php';
        require_once $root . '/vendor/autoload.php';
        $this->pollRepository = new PollRepository();
        $this->userRepository = new UserRepository();
    }

    protected function tearDown(): void
    {
        foreach ($this->pollIds as $id) {
            $this->pollRepository->deletePoll($id);
        }
        parent::tearDown();
    }

    /**
     * findPollsOwnedByUser returns polls in updated_at DESC order with poll_id, title, created_at, timezone, status.
     */
    public function testFindPollsOwnedByUserReturnsPollsInOrderWithExpectedFields(): void
    {
        $email = 'list-polls-owner-' . bin2hex(random_bytes(4)) . '@example.com';
        $this->userId = (int) $this->userRepository->getOrCreateUserIdByEmail($email);

        $slug1 = $this->pollRepository->generateSlug();
        $poll1 = $this->pollRepository->create(
            $this->userId,
            $slug1,
            password_hash('s', PASSWORD_DEFAULT),
            'First poll',
            null,
            null,
            'America/Los_Angeles',
            30,
        );
        $this->pollRepository->addOption($poll1->id, '2026-03-01 14:00:00', '2026-03-01 14:30:00', null, 0);
        $this->pollIds[] = $poll1->id;

        $slug2 = $this->pollRepository->generateSlug();
        $poll2 = $this->pollRepository->create(
            $this->userId,
            $slug2,
            password_hash('s', PASSWORD_DEFAULT),
            'Second poll',
            null,
            null,
            'UTC',
            60,
        );
        $this->pollRepository->addOption($poll2->id, '2026-03-02 10:00:00', '2026-03-02 11:00:00', null, 0);
        $this->pollIds[] = $poll2->id;

        $rows = $this->pollRepository->findPollsOwnedByUser($this->userId);

        $this->assertCount(2, $rows);
        $this->assertSame($slug2, $rows[0]['poll_id']);
        $this->assertSame('Second poll', $rows[0]['title']);
        $this->assertSame('UTC', $rows[0]['timezone']);
        $this->assertSame('open', $rows[0]['status']);
        $this->assertArrayHasKey('created_at', $rows[0]);

        $this->assertSame($slug1, $rows[1]['poll_id']);
        $this->assertSame('First poll', $rows[1]['title']);
        $this->assertSame('America/Los_Angeles', $rows[1]['timezone']);
        $this->assertSame('open', $rows[1]['status']);

        $this->pollRepository->setPollLocked($poll1->id, $this->pollRepository->getOptions($poll1->id)[0]->id);
        $rowsAfterLock = $this->pollRepository->findPollsOwnedByUser($this->userId);
        $firstPollRow = null;
        foreach ($rowsAfterLock as $r) {
            if ($r['poll_id'] === $slug1) {
                $firstPollRow = $r;
                break;
            }
        }
        $this->assertNotNull($firstPollRow);
        $this->assertSame('closed', $firstPollRow['status']);
    }

    /**
     * findPollsOwnedByUser returns empty array for user with no polls.
     */
    public function testFindPollsOwnedByUserReturnsEmptyForUserWithNoPolls(): void
    {
        $email = 'no-polls-' . bin2hex(random_bytes(4)) . '@example.com';
        $userId = (int) $this->userRepository->getOrCreateUserIdByEmail($email);
        $rows = $this->pollRepository->findPollsOwnedByUser($userId);
        $this->assertSame([], $rows);
    }
}
