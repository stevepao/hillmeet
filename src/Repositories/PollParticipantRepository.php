<?php

declare(strict_types=1);

/**
 * PollParticipantRepository.php
 * Purpose: Poll participants (add, list, check participation).
 * Project: Hillmeet
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 */

namespace Hillmeet\Repositories;

use Hillmeet\Support\Database;
use PDO;

final class PollParticipantRepository
{
    public function add(int $pollId, int $userId): void
    {
        $stmt = Database::get()->prepare("INSERT IGNORE INTO poll_participants (poll_id, user_id) VALUES (?, ?)");
        $stmt->execute([$pollId, $userId]);
    }

    public function isParticipant(int $pollId, int $userId): bool
    {
        $stmt = Database::get()->prepare("SELECT 1 FROM poll_participants WHERE poll_id = ? AND user_id = ?");
        $stmt->execute([$pollId, $userId]);
        return $stmt->fetchColumn() !== false;
    }

    /** @return array<int> user ids */
    public function getParticipantIds(int $pollId): array
    {
        $stmt = Database::get()->prepare("SELECT user_id FROM poll_participants WHERE poll_id = ?");
        $stmt->execute([$pollId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @return array<object{id, email, name}> */
    public function getParticipantsWithUsers(int $pollId): array
    {
        $stmt = Database::get()->prepare("
            SELECT u.id, u.email, u.name
            FROM poll_participants pp
            JOIN users u ON u.id = pp.user_id
            WHERE pp.poll_id = ?
            ORDER BY u.name, u.email
        ");
        $stmt->execute([$pollId]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Participants for Results: union of poll_participants and any user who has a vote in this poll.
     * Ensures voters always appear in the results table even if a participant row was missing.
     * @return array<object{id, email, name}>
     */
    public function getResultsParticipants(int $pollId): array
    {
        $stmt = Database::get()->prepare("
            SELECT DISTINCT u.id, u.email, u.name
            FROM (
                SELECT user_id FROM poll_participants WHERE poll_id = ?
                UNION
                SELECT user_id FROM votes WHERE poll_id = ?
            ) combined
            JOIN users u ON u.id = combined.user_id
            ORDER BY u.name, u.email
        ");
        $stmt->execute([$pollId, $pollId]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }
}
