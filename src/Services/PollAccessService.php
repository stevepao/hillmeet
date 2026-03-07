<?php

declare(strict_types=1);

/**
 * PollAccessService.php
 * Purpose: Canonical poll resolution and access control for organizer and invitee flows.
 * Project: Hillmeet
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 */

namespace Hillmeet\Services;

use Hillmeet\Exception\PollForbidden;
use Hillmeet\Exception\PollNotFound;
use Hillmeet\Models\Poll;
use Hillmeet\Repositories\PollInviteRepository;
use Hillmeet\Repositories\PollRepository;
use Hillmeet\Repositories\UserRepository;
use Hillmeet\Support\AccessMode;
use Hillmeet\Support\PollContext;
use Hillmeet\Support\PollRef;

final class PollAccessService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly PollRepository $pollRepository,
        private readonly PollInviteRepository $pollInviteRepository,
        private readonly string $baseUrl = 'https://meet.hillwork.net',
    ) {
    }

    /**
     * Resolve poll for organizer by normalized email and poll reference.
     * Throws PollNotFound if poll or user not found; PollForbidden if not the owner.
     */
    public function resolveForOrganizerByEmail(string $ownerEmail, string $pollRef): PollContext
    {
        $ownerEmail = UserRepository::normalizeEmail($ownerEmail);
        $user = $this->userRepository->findByEmail($ownerEmail);
        if ($user === null) {
            throw new PollNotFound('Owner not found.');
        }
        return $this->resolveForOrganizerByUserId((int) $user->id, $pollRef);
    }

    /**
     * Resolve poll for organizer by user id and poll reference.
     * Throws PollNotFound if poll not found; PollForbidden if not the owner.
     */
    public function resolveForOrganizerByUserId(int $userId, string $pollRef): PollContext
    {
        $ref = $this->parseRef($pollRef);
        $poll = $this->resolvePollByRef($ref);
        if ($poll === null) {
            throw new PollNotFound('Poll not found.');
        }
        if ($poll->organizer_id !== $userId) {
            throw new PollForbidden('Poll not found or access denied.');
        }
        $shareUrl = $this->buildOrganizerShareUrl($poll, $userId);
        return new PollContext(
            poll: $poll,
            pollId: $poll->id,
            pollSlug: $poll->slug,
            timezone: $poll->timezone ?? 'UTC',
            closed: $poll->isLocked(),
            shareUrl: $shareUrl,
            accessMode: AccessMode::ORGANIZER,
            isOrganizer: true,
            actorEmail: $this->userRepository->findById($userId)?->email ?? '',
            invite: null,
            canVote: true,
            canViewResults: true,
            canLock: true,
            canClose: true,
        );
    }

    /**
     * Resolve poll for invitee: by secret link, invite token, or invite-by-email.
     * Throws PollNotFound when poll does not exist or access is denied (masked to avoid enumeration).
     */
    public function resolveForInvitee(string $inviteeEmail, string $pollRef, ?string $secret = null, ?string $inviteToken = null): PollContext
    {
        $inviteeEmail = UserRepository::normalizeEmail($inviteeEmail);
        $ref = $this->parseRef($pollRef);
        $poll = $this->resolvePollByRef($ref);
        if ($poll === null) {
            throw new PollNotFound('Poll not found.');
        }

        $invite = null;
        $accessMode = AccessMode::INVITEE;

        if ($secret !== null && $secret !== '') {
            $pollWithSecret = $this->pollRepository->findBySlugAndVerifySecret($poll->slug, $secret);
            if ($pollWithSecret !== null) {
                $accessMode = AccessMode::SECRET_LINK;
                $poll = $pollWithSecret;
            } else {
                throw new PollNotFound('Poll not found.');
            }
        } elseif ($inviteToken !== null && $inviteToken !== '') {
            $tokenHash = hash('sha256', $inviteToken);
            $invite = $this->pollInviteRepository->findByPollSlugAndTokenHash($poll->slug, $tokenHash);
            if ($invite === null) {
                throw new PollNotFound('Poll not found.');
            }
            $poll = $this->pollRepository->findById((int) $invite->poll_id) ?? $poll;
            $accessMode = AccessMode::INVITEE;
        } else {
            $invite = $this->pollInviteRepository->findByPollIdAndEmail($poll->id, $inviteeEmail);
            if ($invite === null) {
                throw new PollNotFound('Poll not found.');
            }
            $accessMode = AccessMode::INVITEE;
        }

        $canVote = !$poll->isLocked();
        $shareUrl = null;

        return new PollContext(
            poll: $poll,
            pollId: $poll->id,
            pollSlug: $poll->slug,
            timezone: $poll->timezone ?? 'UTC',
            closed: $poll->isLocked(),
            shareUrl: $shareUrl,
            accessMode: $accessMode,
            isOrganizer: false,
            actorEmail: $inviteeEmail,
            invite: $invite,
            canVote: $canVote,
            canViewResults: true,
            canLock: false,
            canClose: false,
        );
    }

    /**
     * Normalize poll ref string (e.g. strip URL to slug) then parse to PollRef.
     */
    private function parseRef(string $pollRef): PollRef
    {
        $v = trim($pollRef);
        if ($v !== '' && str_contains($v, '://')) {
            $path = parse_url($v, PHP_URL_PATH);
            if ($path !== null && $path !== '') {
                $segments = array_filter(explode('/', $path));
                $last = end($segments);
                if ($last !== false && $last !== '') {
                    $v = $last;
                }
            }
        }
        return PollRef::parse($v);
    }

    private function resolvePollByRef(PollRef $ref): ?Poll
    {
        if ($ref->isId()) {
            return $this->pollRepository->findById((int) $ref->value);
        }
        if ($ref->value === '') {
            return null;
        }
        return $this->pollRepository->findBySlug($ref->value);
    }

    private function buildOrganizerShareUrl(Poll $poll, int $ownerUserId): ?string
    {
        $secret = $this->pollRepository->getDecryptedSecretForOwner($poll->id, $ownerUserId);
        if ($secret !== null && $secret !== '') {
            return \Hillmeet\Support\url('/poll/' . $poll->slug, ['secret' => $secret]);
        }
        return rtrim($this->baseUrl, '/') . '/poll/' . $poll->slug;
    }
}
