<?php

declare(strict_types=1);

/**
 * PollAccessServiceTest.php
 * Purpose: Tests for PollAccessService (organizer and invitee resolution, access modes, errors).
 * Project: Hillmeet
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 */

namespace Hillmeet\Tests\Services;

use Hillmeet\Exception\PollForbidden;
use Hillmeet\Exception\PollNotFound;
use Hillmeet\Repositories\PollInviteRepository;
use Hillmeet\Repositories\PollRepository;
use Hillmeet\Repositories\UserRepository;
use Hillmeet\Services\PollAccessService;
use Hillmeet\Support\AccessMode;
use PHPUnit\Framework\TestCase;

final class PollAccessServiceTest extends TestCase
{
    private UserRepository $userRepository;
    private PollRepository $pollRepository;
    private PollInviteRepository $pollInviteRepository;
    private PollAccessService $service;
    private ?int $pollIdToClean = null;

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
        $this->service = new PollAccessService(
            $this->userRepository,
            $this->pollRepository,
            $this->pollInviteRepository,
            'https://meet.hillwork.net',
        );
    }

    protected function tearDown(): void
    {
        if ($this->pollIdToClean !== null) {
            $this->pollRepository->deletePoll($this->pollIdToClean);
        }
        parent::tearDown();
    }

    /**
     * Organizer resolves their own poll by slug -> ctx.isOrganizer=true, accessMode=organizer.
     */
    public function testOrganizerResolvesOwnPollBySlug(): void
    {
        $ownerEmail = 'access-owner-' . bin2hex(random_bytes(4)) . '@example.com';
        $ownerId = (int) $this->userRepository->getOrCreateUserIdByEmail($ownerEmail);
        $slug = $this->pollRepository->generateSlug();
        $poll = $this->pollRepository->create(
            $ownerId,
            $slug,
            password_hash('secret123', PASSWORD_DEFAULT),
            'Test poll',
            null,
            null,
            'America/New_York',
            60,
            'secret123',
        );
        $this->pollRepository->addOption($poll->id, '2026-04-01 14:00:00', '2026-04-01 15:00:00', null, 0);
        $this->pollIdToClean = $poll->id;

        $ctx = $this->service->resolveForOrganizerByEmail($ownerEmail, $slug);

        $this->assertTrue($ctx->isOrganizer);
        $this->assertSame(AccessMode::ORGANIZER, $ctx->accessMode);
        $this->assertSame($poll->id, $ctx->pollId);
        $this->assertSame($slug, $ctx->pollSlug);
        $this->assertSame('America/New_York', $ctx->timezone);
        $this->assertFalse($ctx->closed);
        $this->assertTrue($ctx->canLock);
        $this->assertTrue($ctx->canClose);
        $this->assertNotEmpty($ctx->shareUrl);
    }

    /**
     * Organizer tries to access someone else's poll -> PollForbidden.
     */
    public function testOrganizerAccessOtherPollThrowsForbidden(): void
    {
        $ownerEmail = 'access-owner2-' . bin2hex(random_bytes(4)) . '@example.com';
        $otherEmail = 'access-other-' . bin2hex(random_bytes(4)) . '@example.com';
        $ownerId = (int) $this->userRepository->getOrCreateUserIdByEmail($ownerEmail);
        $this->userRepository->getOrCreateUserIdByEmail($otherEmail);
        $slug = $this->pollRepository->generateSlug();
        $poll = $this->pollRepository->create($ownerId, $slug, password_hash('x', PASSWORD_DEFAULT), 'Mine', null, null, 'UTC', 60);
        $this->pollRepository->addOption($poll->id, '2026-04-01 12:00:00', '2026-04-01 13:00:00', null, 0);
        $this->pollIdToClean = $poll->id;

        $this->expectException(PollForbidden::class);
        $this->expectExceptionMessage('Poll not found or access denied');
        $this->service->resolveForOrganizerByEmail($otherEmail, $slug);
    }

    /**
     * Invitee with valid invite (by email, no token) can resolve poll -> accessMode=invitee.
     */
    public function testInviteeWithValidInviteByEmailResolves(): void
    {
        $ownerEmail = 'access-owner3-' . bin2hex(random_bytes(4)) . '@example.com';
        $inviteeEmail = 'access-invitee-' . bin2hex(random_bytes(4)) . '@example.com';
        $ownerId = (int) $this->userRepository->getOrCreateUserIdByEmail($ownerEmail);
        $slug = $this->pollRepository->generateSlug();
        $poll = $this->pollRepository->create($ownerId, $slug, password_hash('s', PASSWORD_DEFAULT), 'Invite poll', null, null, 'Europe/London', 30);
        $this->pollRepository->addOption($poll->id, '2026-05-01 10:00:00', '2026-05-01 10:30:00', null, 0);
        $this->pollInviteRepository->createInvite($poll->id, $inviteeEmail, hash('sha256', 't'), $ownerId);
        $this->pollIdToClean = $poll->id;

        $ctx = $this->service->resolveForInvitee($inviteeEmail, $slug, null, null);

        $this->assertFalse($ctx->isOrganizer);
        $this->assertSame(AccessMode::INVITEE, $ctx->accessMode);
        $this->assertSame($inviteeEmail, $ctx->actorEmail);
        $this->assertNotNull($ctx->invite);
        $this->assertSame('Europe/London', $ctx->timezone);
        $this->assertTrue($ctx->canViewResults);
        $this->assertFalse($ctx->canLock);
    }

    /**
     * Invitee with valid secret link can resolve poll -> accessMode=secret-link.
     */
    public function testInviteeWithValidSecretLinkResolves(): void
    {
        $ownerEmail = 'access-owner4-' . bin2hex(random_bytes(4)) . '@example.com';
        $ownerId = (int) $this->userRepository->getOrCreateUserIdByEmail($ownerEmail);
        $slug = $this->pollRepository->generateSlug();
        $secret = 'link-secret-' . bin2hex(random_bytes(8));
        $poll = $this->pollRepository->create(
            $ownerId,
            $slug,
            password_hash($secret, PASSWORD_DEFAULT),
            'Secret link poll',
            null,
            null,
            'UTC',
            60,
            $secret,
        );
        $this->pollRepository->addOption($poll->id, '2026-06-01 09:00:00', '2026-06-01 10:00:00', null, 0);
        $this->pollIdToClean = $poll->id;

        $ctx = $this->service->resolveForInvitee('anyone@example.com', $slug, $secret, null);

        $this->assertFalse($ctx->isOrganizer);
        $this->assertSame(AccessMode::SECRET_LINK, $ctx->accessMode);
        $this->assertSame($slug, $ctx->pollSlug);
        $this->assertNull($ctx->invite);
    }

    /**
     * Invitee with valid invite token (URL ?invite=...) can resolve poll -> accessMode=invitee.
     */
    public function testInviteeWithValidInviteTokenResolves(): void
    {
        $ownerEmail = 'access-owner-token-' . bin2hex(random_bytes(4)) . '@example.com';
        $inviteeEmail = 'access-invitee-token-' . bin2hex(random_bytes(4)) . '@example.com';
        $ownerId = (int) $this->userRepository->getOrCreateUserIdByEmail($ownerEmail);
        $slug = $this->pollRepository->generateSlug();
        $poll = $this->pollRepository->create($ownerId, $slug, password_hash('s', PASSWORD_DEFAULT), 'Token invite poll', null, null, 'Europe/Paris', 45);
        $this->pollRepository->addOption($poll->id, '2026-06-15 11:00:00', '2026-06-15 11:45:00', null, 0);
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $this->pollInviteRepository->createInvite($poll->id, $inviteeEmail, $tokenHash, $ownerId);
        $this->pollIdToClean = $poll->id;

        $ctx = $this->service->resolveForInvitee($inviteeEmail, $slug, null, $rawToken);

        $this->assertFalse($ctx->isOrganizer);
        $this->assertSame(AccessMode::INVITEE, $ctx->accessMode);
        $this->assertSame($inviteeEmail, $ctx->actorEmail);
        $this->assertNotNull($ctx->invite);
        $this->assertSame($poll->id, (int) $ctx->invite->poll_id);
        $this->assertSame('Europe/Paris', $ctx->timezone);
        $this->assertTrue($ctx->canViewResults);
        $this->assertTrue($ctx->canVote);
        $this->assertFalse($ctx->canLock);
    }

    /**
     * Invitee without invite and without secret -> PollNotFound.
     */
    public function testInviteeWithoutInviteOrSecretThrowsNotFound(): void
    {
        $ownerEmail = 'access-owner5-' . bin2hex(random_bytes(4)) . '@example.com';
        $ownerId = (int) $this->userRepository->getOrCreateUserIdByEmail($ownerEmail);
        $slug = $this->pollRepository->generateSlug();
        $poll = $this->pollRepository->create($ownerId, $slug, password_hash('s', PASSWORD_DEFAULT), 'Private', null, null, 'UTC', 60);
        $this->pollRepository->addOption($poll->id, '2026-07-01 12:00:00', '2026-07-01 13:00:00', null, 0);
        $this->pollIdToClean = $poll->id;

        $this->expectException(PollNotFound::class);
        $this->expectExceptionMessage('Poll not found');
        $this->service->resolveForInvitee('stranger@example.com', $slug, null, null);
    }

    /**
     * Emails are normalized (case-insensitive).
     */
    public function testEmailNormalization(): void
    {
        $ownerEmail = 'Access-Owner-' . bin2hex(random_bytes(4)) . '@Example.COM';
        $ownerId = (int) $this->userRepository->getOrCreateUserIdByEmail(strtolower(trim($ownerEmail)));
        $slug = $this->pollRepository->generateSlug();
        $poll = $this->pollRepository->create($ownerId, $slug, password_hash('s', PASSWORD_DEFAULT), 'Norm', null, null, 'UTC', 60);
        $this->pollRepository->addOption($poll->id, '2026-08-01 14:00:00', '2026-08-01 15:00:00', null, 0);
        $this->pollIdToClean = $poll->id;

        $ctx = $this->service->resolveForOrganizerByEmail($ownerEmail, $slug);

        $this->assertTrue($ctx->isOrganizer);
        $this->assertSame($poll->id, $ctx->pollId);
    }

    /**
     * resolveForOrganizerByUserId works like resolveForOrganizerByEmail when user exists.
     */
    public function testResolveForOrganizerByUserId(): void
    {
        $ownerEmail = 'access-owner6-' . bin2hex(random_bytes(4)) . '@example.com';
        $ownerId = (int) $this->userRepository->getOrCreateUserIdByEmail($ownerEmail);
        $slug = $this->pollRepository->generateSlug();
        $poll = $this->pollRepository->create($ownerId, $slug, password_hash('s', PASSWORD_DEFAULT), 'By UserId', null, null, 'Asia/Tokyo', 45);
        $this->pollRepository->addOption($poll->id, '2026-09-01 08:00:00', '2026-09-01 08:45:00', null, 0);
        $this->pollIdToClean = $poll->id;

        $ctx = $this->service->resolveForOrganizerByUserId($ownerId, $slug);

        $this->assertTrue($ctx->isOrganizer);
        $this->assertSame(AccessMode::ORGANIZER, $ctx->accessMode);
        $this->assertSame('Asia/Tokyo', $ctx->timezone);
    }

    /**
     * Owner not found throws PollNotFound.
     */
    public function testOwnerNotFoundThrows(): void
    {
        $this->expectException(PollNotFound::class);
        $this->expectExceptionMessage('Owner not found');
        $this->service->resolveForOrganizerByEmail('nonexistent-' . bin2hex(random_bytes(8)) . '@example.com', 'any-slug');
    }
}
