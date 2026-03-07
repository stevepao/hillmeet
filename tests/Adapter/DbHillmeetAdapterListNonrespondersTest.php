<?php

declare(strict_types=1);

/**
 * DbHillmeetAdapterListNonrespondersTest.php
 * Purpose: Integration-style tests for DbHillmeetAdapter::listNonresponders. Requires config and database; skips if config is not available.
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
use PHPUnit\Framework\TestCase;

/**
 * Integration-style tests for DbHillmeetAdapter::listNonresponders.
 * Requires config and database; skips if config is not available.
 */
final class DbHillmeetAdapterListNonrespondersTest extends TestCase
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
     * Valid owner + poll with one non-responder returns result with that email and summary.
     */
    public function testListNonrespondersReturnsResultWithEmailsAndSummary(): void
    {
        $ownerEmail = 'list-nr-owner@example.com';
        $userId = (int) $this->userRepository->getOrCreateUserIdByEmail($ownerEmail);
        $slug = $this->pollRepository->generateSlug();
        $poll = $this->pollRepository->create(
            $userId,
            $slug,
            password_hash('secret', PASSWORD_DEFAULT),
            'List NR test',
            null,
            null,
            'UTC',
            30,
        );
        $this->pollRepository->addOption($poll->id, '2026-03-01 14:00:00', '2026-03-01 14:30:00', null, 0);
        $this->pollId = $poll->id;
        $this->pollInviteRepository->createInvite($poll->id, 'lee@example.com', hash('sha256', 'tok1'), $userId);
        $this->pollInviteRepository->createInvite($poll->id, 'morgan@example.com', hash('sha256', 'tok2'), $userId);
        $morganId = (int) $this->userRepository->getOrCreateUserIdByEmail('morgan@example.com');
        (new PollParticipantRepository())->add($poll->id, $morganId);
        $options = $this->pollRepository->getOptions($poll->id);
        (new VoteRepository())->setVote($poll->id, $options[0]->id, $morganId, 'yes');

        $result = $this->adapter->listNonresponders($ownerEmail, $slug);

        $this->assertCount(1, $result->nonresponders);
        $this->assertSame('lee@example.com', $result->nonresponders[0]['email']);
        $this->assertStringContainsString('lee@example.com', $result->summary);
        $this->assertStringContainsString('haven\'t responded', $result->summary);
    }

    /**
     * Owner not found throws HillmeetNotFound.
     */
    public function testListNonrespondersOwnerNotFoundThrows(): void
    {
        $this->expectException(HillmeetNotFound::class);
        $this->expectExceptionMessage('Owner not found');
        $this->adapter->listNonresponders('nonexistent-' . bin2hex(random_bytes(4)) . '@example.com', 'any-slug');
    }

    /**
     * Poll not found or not owned throws HillmeetNotFound.
     */
    public function testListNonrespondersPollNotFoundThrows(): void
    {
        $ownerEmail = 'list-nr-other@example.com';
        $this->userRepository->getOrCreateUserIdByEmail($ownerEmail);
        $this->expectException(HillmeetNotFound::class);
        $this->expectExceptionMessage('Poll not found');
        $this->adapter->listNonresponders($ownerEmail, 'non-existent-slug-abc');
    }
}
