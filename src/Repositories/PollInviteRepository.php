<?php

declare(strict_types=1);

namespace Hillmeet\Repositories;

use Hillmeet\Support\Database;
use PDO;

final class PollInviteRepository
{
    public function add(int $pollId, string $email): void
    {
        $stmt = Database::get()->prepare("INSERT IGNORE INTO poll_invites (poll_id, email) VALUES (?, ?)");
        $stmt->execute([$pollId, $email]);
    }

    public function markSent(int $id): void
    {
        $stmt = Database::get()->prepare("UPDATE poll_invites SET sent_at = NOW() WHERE id = ?");
        $stmt->execute([$id]);
    }

    /** @return array<object{id, email, sent_at}> */
    public function getByPoll(int $pollId): array
    {
        $stmt = Database::get()->prepare("SELECT id, email, sent_at FROM poll_invites WHERE poll_id = ?");
        $stmt->execute([$pollId]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }
}
