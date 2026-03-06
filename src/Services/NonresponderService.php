<?php

declare(strict_types=1);

/**
 * NonresponderService.php
 * Purpose: List participants who have not yet responded to a poll (invited minus those who voted).
 * Project: Hillmeet
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 */

namespace Hillmeet\Services;

use Hillmeet\Repositories\PollInviteRepository;
use Hillmeet\Repositories\PollParticipantRepository;
use Hillmeet\Repositories\PollRepository;
use Hillmeet\Repositories\VoteRepository;

/**
 * Internal business logic for non-responders. Does not know about MCP.
 *
 * Invited = union of poll invite emails and results participants (by email).
 * Responded = anyone who has at least one vote (yes/maybe/no) in the poll.
 * Non-responders = invited − responded (by normalized email).
 */
final class NonresponderService
{
    public function __construct(
        private readonly PollRepository $pollRepository,
        private readonly PollInviteRepository $inviteRepository,
        private readonly PollParticipantRepository $participantRepository,
        private readonly VoteRepository $voteRepository,
    ) {
    }

    /**
     * Find non-responders for a poll. Only the poll organizer may call this.
     *
     * @return list<array{email: string, name?: string}>
     */
    public function findNonrespondersForPoll(int $userId, int $pollId): array
    {
        $poll = $this->pollRepository->findById($pollId);
        if ($poll === null || $poll->organizer_id !== $userId) {
            return [];
        }

        // Build invited set: email => optional name (normalized emails)
        $invited = [];
        foreach ($this->inviteRepository->getInvitedEmails($pollId) as $e) {
            $e = strtolower(trim($e));
            if ($e !== '') {
                $invited[$e] = null;
            }
        }
        foreach ($this->participantRepository->getResultsParticipants($pollId) as $u) {
            $e = isset($u->email) && $u->email !== '' ? strtolower(trim((string) $u->email)) : null;
            if ($e !== null) {
                $name = isset($u->name) && $u->name !== '' ? trim((string) $u->name) : null;
                $invited[$e] = $name;
            }
        }

        // Responded = distinct voter emails (normalized)
        $responded = [];
        foreach ($this->voteRepository->getVotersWithUsers($pollId) as $u) {
            $e = isset($u->email) && $u->email !== '' ? strtolower(trim((string) $u->email)) : null;
            if ($e !== null) {
                $responded[$e] = true;
            }
        }

        // Non-responders = invited keys minus responded
        $out = [];
        foreach (array_keys($invited) as $email) {
            if (isset($responded[$email])) {
                continue;
            }
            $name = $invited[$email];
            $out[] = $name !== null ? ['email' => $email, 'name' => $name] : ['email' => $email];
        }

        usort($out, static fn(array $a, array $b): int => strcasecmp($a['email'], $b['email']));
        return $out;
    }
}
