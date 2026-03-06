<?php

declare(strict_types=1);

namespace Hillmeet\Tests\Services;

use Hillmeet\Exception\HillmeetNotFound;
use Hillmeet\Repositories\PollInviteRepository;
use Hillmeet\Repositories\PollRepository;
use Hillmeet\Repositories\UserRepository;
use Hillmeet\Services\PollDetailsService;
use PHPUnit\Framework\TestCase;

/**
 * Tests for PollDetailsService::getPollDetailsForOwner.
 * Requires config and database; skips if not available.
 */
final class PollDetailsServiceTest extends TestCase
{
    private ?int $pollId = null;
    private UserRepository $userRepository;
    private PollRepository $pollRepository;
    private PollInviteRepository $pollInviteRepository;
    private PollDetailsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $root = dirname(__DIR__, 2);
        if (!is_file($root . '/config/config.php')) {
            $this->markTestSkipped('config/config.php required');
        }
        require_once $root . '/config/env.php';
        require_once $root . '/vendor/autoload.php';
        $this->userRepository = new UserRepository();
        $this->pollRepository = new PollRepository();
        $this->pollInviteRepository = new PollInviteRepository();
        $this->service = new PollDetailsService(
            $this->pollRepository,
            $this->pollInviteRepository,
            $this->userRepository,
        );
    }

    protected function tearDown(): void
    {
        if ($this->pollId !== null) {
            $this->pollRepository->deletePoll($this->pollId);
        }
        parent::tearDown();
    }

    /**
     * Create poll with 2 options and 2 participants; getPollDetailsForOwner returns correct data.
     */
    public function testGetPollDetailsForOwnerReturnsTitleTimezoneStatusOptionsParticipants(): void
    {
        $ownerEmail = 'get-poll-owner-' . bin2hex(random_bytes(4)) . '@example.com';
        $ownerId = (int) $this->userRepository->getOrCreateUserIdByEmail($ownerEmail);
        $slug = $this->pollRepository->generateSlug();
        $poll = $this->pollRepository->create(
            $ownerId,
            $slug,
            password_hash('s', PASSWORD_DEFAULT),
            'Team sync',
            null,
            null,
            'America/Los_Angeles',
            30,
        );
        $this->pollRepository->addOption($poll->id, '2026-03-10 14:00:00', '2026-03-10 14:30:00', null, 0);
        $this->pollRepository->addOption($poll->id, '2026-03-10 15:00:00', '2026-03-10 15:30:00', null, 1);
        $this->pollId = $poll->id;
        $this->pollInviteRepository->createInvite($poll->id, 'alice-getpoll@example.com', hash('sha256', 't1'), $ownerId);
        $this->pollInviteRepository->createInvite($poll->id, 'bob-getpoll@example.com', hash('sha256', 't2'), $ownerId);
        $this->userRepository->getOrCreateByEmail('alice-getpoll@example.com', 'Alice');
        $this->userRepository->getOrCreateByEmail('bob-getpoll@example.com', 'Bob');

        $data = $this->service->getPollDetailsForOwner($ownerId, $slug);

        $this->assertSame($slug, $data->pollId);
        $this->assertSame('Team sync', $data->title);
        $this->assertSame('America/Los_Angeles', $data->timezone);
        $this->assertSame('open', $data->status);
        $this->assertNotEmpty($data->created_at);
        $this->assertCount(2, $data->options);
        $this->assertInstanceOf(\DateTimeImmutable::class, $data->options[0]['start_utc']);
        $this->assertSame('2026-03-10 14:00:00', $data->options[0]['start_utc']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-03-10 14:30:00', $data->options[0]['end_utc']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-03-10 15:00:00', $data->options[1]['start_utc']->format('Y-m-d H:i:s'));
        $this->assertCount(2, $data->participants);
        $emails = array_column($data->participants, 'email');
        $this->assertContains('alice-getpoll@example.com', $emails);
        $this->assertContains('bob-getpoll@example.com', $emails);
        foreach ($data->participants as $p) {
            $this->assertArrayHasKey('email', $p);
            if ($p['email'] === 'alice-getpoll@example.com') {
                $this->assertSame('Alice', $p['name'] ?? null);
            }
            if ($p['email'] === 'bob-getpoll@example.com') {
                $this->assertSame('Bob', $p['name'] ?? null);
            }
        }
    }

    /**
     * Wrong userId (not organizer) throws HillmeetNotFound.
     */
    public function testGetPollDetailsForOwnerWrongUserThrows(): void
    {
        $ownerId = (int) $this->userRepository->getOrCreateUserIdByEmail('get-poll-owner2@example.com');
        $otherId = (int) $this->userRepository->getOrCreateUserIdByEmail('get-poll-other@example.com');
        $slug = $this->pollRepository->generateSlug();
        $poll = $this->pollRepository->create($ownerId, $slug, password_hash('s', PASSWORD_DEFAULT), 'Poll', null, null, 'UTC', 30);
        $this->pollRepository->addOption($poll->id, '2026-03-01 14:00:00', '2026-03-01 14:30:00', null, 0);
        $this->pollId = $poll->id;

        $this->expectException(HillmeetNotFound::class);
        $this->expectExceptionMessage('Poll not found or access denied');
        $this->service->getPollDetailsForOwner($otherId, $slug);
    }

    /**
     * Nonexistent slug throws HillmeetNotFound.
     */
    public function testGetPollDetailsForOwnerNonexistentSlugThrows(): void
    {
        $ownerId = (int) $this->userRepository->getOrCreateUserIdByEmail('get-poll-owner3@example.com');
        $this->expectException(HillmeetNotFound::class);
        $this->service->getPollDetailsForOwner($ownerId, 'nonexistent-slug-xyz');
    }

    /**
     * Closed poll returns status 'closed'.
     */
    public function testGetPollDetailsForOwnerClosedPollReturnsStatusClosed(): void
    {
        $ownerId = (int) $this->userRepository->getOrCreateUserIdByEmail('get-poll-owner4@example.com');
        $slug = $this->pollRepository->generateSlug();
        $poll = $this->pollRepository->create($ownerId, $slug, password_hash('s', PASSWORD_DEFAULT), 'Closed poll', null, null, 'UTC', 30);
        $this->pollRepository->addOption($poll->id, '2026-03-01 14:00:00', '2026-03-01 14:30:00', null, 0);
        $options = $this->pollRepository->getOptions($poll->id);
        $this->pollRepository->setPollLocked($poll->id, $options[0]->id);
        $this->pollId = $poll->id;

        $data = $this->service->getPollDetailsForOwner($ownerId, $slug);
        $this->assertSame('closed', $data->status);
    }
}
