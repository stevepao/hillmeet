<?php

declare(strict_types=1);

/**
 * DbHillmeetAdapterFindAvailabilityTest.php
 * Purpose: Integration-style tests for DbHillmeetAdapter::findAvailability. Requires config and database; skips if config is not available.
 * Project: Hillmeet
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 */

namespace Hillmeet\Tests\Adapter;

use Hillmeet\Adapter\DbHillmeetAdapter;
use Hillmeet\Repositories\PollInviteRepository;
use Hillmeet\Repositories\PollParticipantRepository;
use Hillmeet\Repositories\PollRepository;
use Hillmeet\Repositories\UserRepository;
use Hillmeet\Repositories\VoteRepository;
use Hillmeet\Services\AvailabilityService;
use Hillmeet\Services\EmailService;
use Hillmeet\Services\NonresponderService;
use Hillmeet\Services\PollDetailsService;
use PHPUnit\Framework\TestCase;

/**
 * Integration-style tests for DbHillmeetAdapter::findAvailability.
 * Requires config and database; skips if config is not available.
 */
final class DbHillmeetAdapterFindAvailabilityTest extends TestCase
{
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
        $this->userRepository = new UserRepository();
        $this->pollRepository = new PollRepository();
        $this->pollInviteRepository = new PollInviteRepository();
        $this->adapter = new DbHillmeetAdapter(
            $this->userRepository,
            $this->pollRepository,
            $this->pollInviteRepository,
            new EmailService(),
            new AvailabilityService(
                $this->pollRepository,
                new VoteRepository(),
                new PollParticipantRepository(),
                $this->pollInviteRepository,
            ),
            new NonresponderService(
                $this->pollRepository,
                $this->pollInviteRepository,
                new PollParticipantRepository(),
                new VoteRepository(),
            ),
            new PollDetailsService(
                $this->pollRepository,
                $this->pollInviteRepository,
                $this->userRepository,
            ),
            'https://meet.hillwork.net',
        );
    }

    protected function tearDown(): void
    {
        if ($this->createdPollId !== null) {
            $this->pollRepository->deletePoll($this->createdPollId);
        }
        parent::tearDown();
    }

    /**
     * Owner email that has no user in DB returns empty bestSlots and summary "Owner not found."
     */
    public function testFindAvailabilityOwnerNotFoundReturnsEmptyResultWithMessage(): void
    {
        $unknownOwner = 'no-such-owner-' . bin2hex(random_bytes(4)) . '@example.com';
        $result = $this->adapter->findAvailability($unknownOwner, 'any-slug', []);

        $this->assertSame([], $result->bestSlots);
        $this->assertSame('Owner not found.', $result->summary);
        $this->assertStringContainsString('/poll/any-slug', $result->shareUrl);
    }

    /**
     * Valid owner but non-existent poll slug returns empty bestSlots and "Poll not found or access denied."
     */
    public function testFindAvailabilityPollNotFoundReturnsEmptyResultWithMessage(): void
    {
        $ownerEmail = 'find-avail-owner-' . bin2hex(random_bytes(4)) . '@example.com';
        $this->userRepository->getOrCreateUserIdByEmail($ownerEmail);

        $result = $this->adapter->findAvailability($ownerEmail, 'non-existent-slug-xyz', []);

        $this->assertSame([], $result->bestSlots);
        $this->assertSame('Poll not found or access denied.', $result->summary);
        $this->assertStringContainsString('/poll/non-existent-slug-xyz', $result->shareUrl);
    }

    /**
     * Poll exists but is owned by another user: returns empty bestSlots and "Poll not found or access denied."
     */
    public function testFindAvailabilityPollOwnedByOtherUserReturnsEmptyResultWithMessage(): void
    {
        $ownerA = 'find-avail-a-' . bin2hex(random_bytes(4)) . '@example.com';
        $ownerB = 'find-avail-b-' . bin2hex(random_bytes(4)) . '@example.com';
        $userIdA = (int) $this->userRepository->getOrCreateUserIdByEmail($ownerA);
        $this->userRepository->getOrCreateUserIdByEmail($ownerB);

        $slug = $this->pollRepository->generateSlug();
        $poll = $this->pollRepository->create(
            $userIdA,
            $slug,
            password_hash('secret', PASSWORD_DEFAULT),
            'Poll owned by A',
            null,
            null,
            'UTC',
            30,
        );
        $this->pollRepository->addOption($poll->id, '2026-03-01 14:00:00', '2026-03-01 14:30:00', null, 0);
        $this->createdPollId = $poll->id;

        $result = $this->adapter->findAvailability($ownerB, $slug, []);

        $this->assertSame([], $result->bestSlots);
        $this->assertSame('Poll not found or access denied.', $result->summary);
        $this->assertStringContainsString('/poll/' . $slug, $result->shareUrl);
    }
}
