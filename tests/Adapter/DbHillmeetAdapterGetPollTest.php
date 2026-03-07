<?php

declare(strict_types=1);

/**
 * DbHillmeetAdapterGetPollTest.php
 * Purpose: Integration-style tests for DbHillmeetAdapter::getPoll. Requires config and database; skips if config is not available.
 * Project: Hillmeet
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 */

namespace Hillmeet\Tests\Adapter;

use Hillmeet\Exception\HillmeetNotFound;
use Hillmeet\Repositories\PollInviteRepository;
use Hillmeet\Repositories\PollParticipantRepository;
use Hillmeet\Repositories\PollRepository;
use Hillmeet\Repositories\UserRepository;
use Hillmeet\Repositories\VoteRepository;
use Hillmeet\Services\AvailabilityService;
use Hillmeet\Services\EmailService;
use Hillmeet\Services\NonresponderService;
use Hillmeet\Services\PollDetailsService;
use Hillmeet\Services\PollAccessService;
use PHPUnit\Framework\TestCase;

/**
 * Integration-style tests for DbHillmeetAdapter::getPoll.
 * Requires config and database; skips if config is not available.
 */
final class DbHillmeetAdapterGetPollTest extends TestCase
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
            new PollAccessService(
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
     * getPoll returns HillmeetPollDetails with localized options (poll timezone) and participants.
     */
    public function testGetPollReturnsDetailsWithLocalizedOptionsAndParticipants(): void
    {
        $ownerEmail = 'getpoll-owner-' . bin2hex(random_bytes(4)) . '@example.com';
        $ownerId = (int) $this->userRepository->getOrCreateUserIdByEmail($ownerEmail);
        $slug = $this->pollRepository->generateSlug();
        $poll = $this->pollRepository->create(
            $ownerId,
            $slug,
            password_hash('s', PASSWORD_DEFAULT),
            'Standup poll',
            null,
            null,
            'Europe/London',
            30,
        );
        $this->pollRepository->addOption($poll->id, '2026-03-10 14:00:00', '2026-03-10 14:30:00', null, 0);
        $this->pollId = $poll->id;
        $this->pollInviteRepository->createInvite($poll->id, 'participant-getpoll@example.com', hash('sha256', 'tok'), $ownerId);

        $result = $this->adapter->getPoll($ownerEmail, $slug);

        $this->assertSame($slug, $result->pollId);
        $this->assertSame('Standup poll', $result->title);
        $this->assertSame('Europe/London', $result->timezone);
        $this->assertFalse($result->closed);
        $this->assertNotEmpty($result->created_at);
        $this->assertCount(1, $result->options);
        $this->assertArrayHasKey('start', $result->options[0]);
        $this->assertArrayHasKey('end', $result->options[0]);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $result->options[0]['start']);
        $this->assertCount(1, $result->participants);
        $this->assertSame('participant-getpoll@example.com', $result->participants[0]['email']);
    }

    /**
     * Owner not found throws HillmeetNotFound.
     */
    public function testGetPollOwnerNotFoundThrows(): void
    {
        $this->expectException(HillmeetNotFound::class);
        $this->expectExceptionMessage('Owner not found');
        $this->adapter->getPoll('nonexistent-' . bin2hex(random_bytes(4)) . '@example.com', 'any-slug');
    }

    /**
     * Poll not found or not owned throws HillmeetNotFound.
     */
    public function testGetPollPollNotFoundThrows(): void
    {
        $ownerEmail = 'getpoll-other@example.com';
        $this->userRepository->getOrCreateUserIdByEmail($ownerEmail);
        $this->expectException(HillmeetNotFound::class);
        $this->expectExceptionMessage('Poll not found');
        $this->adapter->getPoll($ownerEmail, 'nonexistent-slug-abc');
    }

    /**
     * Closed poll returns closed true.
     */
    public function testGetPollClosedPollReturnsClosedTrue(): void
    {
        $ownerEmail = 'getpoll-closed@example.com';
        $ownerId = (int) $this->userRepository->getOrCreateUserIdByEmail($ownerEmail);
        $slug = $this->pollRepository->generateSlug();
        $poll = $this->pollRepository->create($ownerId, $slug, password_hash('s', PASSWORD_DEFAULT), 'Closed', null, null, 'UTC', 30);
        $this->pollRepository->addOption($poll->id, '2026-03-01 14:00:00', '2026-03-01 14:30:00', null, 0);
        $options = $this->pollRepository->getOptions($poll->id);
        $this->pollRepository->setPollLocked($poll->id, $options[0]->id);
        $this->pollId = $poll->id;

        $result = $this->adapter->getPoll($ownerEmail, $slug);
        $this->assertTrue($result->closed);
    }
}
