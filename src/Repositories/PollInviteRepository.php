<?php

declare(strict_types=1);

namespace Hillmeet\Repositories;

use Hillmeet\Support\Database;
use PDO;

final class PollInviteRepository
{
    /**
     * Create or update an invite (unique on poll_id + email). Returns the row id.
     */
    public function createInvite(int $pollId, string $email, string $tokenHash, ?int $invitedByUserId): int
    {
        $stmt = Database::get()->prepare("
            INSERT INTO poll_invites (poll_id, email, token_hash, invited_by_user_id)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                token_hash = VALUES(token_hash),
                invited_by_user_id = VALUES(invited_by_user_id)
        ");
        $stmt->execute([$pollId, $email, $tokenHash, $invitedByUserId]);
        $id = (int) Database::get()->lastInsertId();
        if ($id === 0) {
            $sel = Database::get()->prepare("SELECT id FROM poll_invites WHERE poll_id = ? AND email = ?");
            $sel->execute([$pollId, $email]);
            $id = (int) $sel->fetchColumn();
        }
        return $id;
    }

    /** @return array<object{id, email, sent_at}> */
    public function listInvites(int $pollId): array
    {
        $stmt = Database::get()->prepare("SELECT id, email, sent_at FROM poll_invites WHERE poll_id = ? ORDER BY sent_at DESC, email");
        $stmt->execute([$pollId]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function markSent(int $id): void
    {
        $stmt = Database::get()->prepare("UPDATE poll_invites SET sent_at = NOW() WHERE id = ?");
        $stmt->execute([$id]);
    }

    /** @return array<object{id, email, sent_at}> */
    public function getByPoll(int $pollId): array
    {
        return $this->listInvites($pollId);
    }

    /**
     * Find invite by poll slug and token hash (constant-time comparison).
     * @return object{id, poll_id, email, token_hash}|null
     */
    public function findByPollSlugAndTokenHash(string $slug, string $tokenHash): ?object
    {
        $stmt = Database::get()->prepare("
            SELECT pi.id, pi.poll_id, pi.email, pi.token_hash
            FROM poll_invites pi
            JOIN polls p ON p.id = pi.poll_id
            WHERE p.slug = ?
        ");
        $stmt->execute([$slug]);
        $rows = $stmt->fetchAll(PDO::FETCH_OBJ);
        foreach ($rows as $row) {
            if (hash_equals($row->token_hash, $tokenHash)) {
                return $row;
            }
        }
        return null;
    }

    public function markAccepted(int $id, int $userId): void
    {
        $stmt = Database::get()->prepare("UPDATE poll_invites SET accepted_at = NOW(), accepted_by_user_id = ? WHERE id = ?");
        $stmt->execute([$userId, $id]);
    }
}
