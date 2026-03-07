<?php

declare(strict_types=1);

/**
 * DbHillmeetAdapterListPollsTest.php
 * Purpose: Integration-style tests for DbHillmeetAdapter::listPolls. Requires config and database; skips if config is not available.
 * Project: Hillmeet
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 */

namespace Hillmeet\Tests\Adapter;

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
 * Integration-style tests for DbHillmeetAdapter::listPolls.
 * Requires config and database; skips if config is not available.
 */
final class DbHillmeetAdapterListPollsTest extends TestCase
{
    private ?int $pollId = null;
    private UserRepository $userRepository;
    private PollRepository $pollRepository;
    private PollInviteRepository $pollInviteRepository;
    private \Hillmeet\Adapter\DbHillmeetAdapter $adapter;

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
        $this->adapter = new \Hillmeet\Adapter\DbHillmeetAdapter(
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
            new \Hillmeet\Services\PollAccessService(
                $this->userRepository,
                $this->pollRepository,
                $this->pollInviteRepository,
                'https://meet.hillwork.net',
            ),
            'https://meet.hillwork.net',
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
     * Owner not found returns empty polls and summary "Owner not found."
     */
    public function testListPollsOwnerNotFoundReturnsEmptyResult(): void
    {
        $result = $this->adapter->listPolls('nonexistent-' . bin2hex(random_bytes(4)) . '@example.com');
        $this->assertSame([], $result->polls);
        $this->assertSame('Owner not found.', $result->summary);
    }

    /**
     * Owner with no polls returns empty list and "You have no polls."
     */
    public function testListPollsOwnerWithNoPollsReturnsEmptyListAndSummary(): void
    {
        $email = 'no-polls-owner-' . bin2hex(random_bytes(4)) . '@example.com';
        $this->userRepository->getOrCreateUserIdByEmail($email);
        $result = $this->adapter->listPolls($email);
        $this->assertSame([], $result->polls);
        $this->assertSame('You have no polls.', $result->summary);
    }

    /**
     * Owner with one poll returns correct DTO with poll_id, title, created_at, timezone, status, share_url and summary.
     */
    public function testListPollsOwnerWithOnePollReturnsCorrectDto(): void
    {
        $email = 'list-polls-one-' . bin2hex(random_bytes(4)) . '@example.com';
        $userId = (int) $this->userRepository->getOrCreateUserIdByEmail($email);
        $slug = $this->pollRepository->generateSlug();
        $poll = $this->pollRepository->create(
            $userId,
            $slug,
            password_hash('s', PASSWORD_DEFAULT),
            'My standup poll',
            null,
            null,
            'Europe/London',
            30,
        );
        $this->pollRepository->addOption($poll->id, '2026-03-01 14:00:00', '2026-03-01 14:30:00', null, 0);
        $this->pollId = $poll->id;

        $result = $this->adapter->listPolls($email);

        $this->assertCount(1, $result->polls);
        $p = $result->polls[0];
        $this->assertSame($slug, $p['poll_id']);
        $this->assertSame('My standup poll', $p['title']);
        $this->assertArrayHasKey('created_at', $p);
        $this->assertSame('Europe/London', $p['timezone']);
        $this->assertSame('open', $p['status']);
        $this->assertSame('https://meet.hillwork.net/poll/' . $slug, $p['share_url']);
        $this->assertStringContainsString('1 poll', $result->summary);
        $this->assertStringContainsString('My standup poll', $result->summary);
    }
}
