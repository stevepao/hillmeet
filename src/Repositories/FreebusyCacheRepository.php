<?php

declare(strict_types=1);

namespace Hillmeet\Repositories;

use Hillmeet\Support\Database;
use PDO;

final class FreebusyCacheRepository
{
    public function get(int $userId, int $pollOptionId, int $ttlSeconds): ?bool
    {
        $stmt = Database::get()->prepare("
            SELECT is_busy FROM freebusy_cache
            WHERE user_id = ? AND poll_option_id = ? AND cached_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        $stmt->execute([$userId, $pollOptionId, $ttlSeconds]);
        $row = $stmt->fetchColumn();
        if ($row === false) {
            return null;
        }
        return (int) $row === 1;
    }

    public function set(int $userId, int $pollId, int $pollOptionId, bool $isBusy): void
    {
        $stmt = Database::get()->prepare("
            INSERT INTO freebusy_cache (user_id, poll_id, poll_option_id, is_busy)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE is_busy = VALUES(is_busy), cached_at = NOW()
        ");
        $stmt->execute([$userId, $pollId, $pollOptionId, $isBusy ? 1 : 0]);
    }

    public function invalidateForUser(int $userId): void
    {
        $stmt = Database::get()->prepare("DELETE FROM freebusy_cache WHERE user_id = ?");
        $stmt->execute([$userId]);
    }
}
