<?php

declare(strict_types=1);

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
}
