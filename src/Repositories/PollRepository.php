<?php

declare(strict_types=1);

namespace Hillmeet\Repositories;

use Hillmeet\Models\Poll;
use Hillmeet\Models\PollOption;
use Hillmeet\Support\Database;
use PDO;

final class PollRepository
{
    public function findBySlug(string $slug): ?Poll
    {
        $stmt = Database::get()->prepare("SELECT * FROM polls WHERE slug = ?");
        $stmt->execute([$slug]);
        $row = $stmt->fetch(PDO::FETCH_OBJ);
        return $row ? Poll::fromRow($row) : null;
    }

    /** Constant-time slug + secret validation: always do hash comparison. */
    public function findBySlugAndVerifySecret(string $slug, string $secret): ?Poll
    {
        $poll = $this->findBySlug($slug);
        if ($poll === null) {
            password_verify('dummy', '$2y$10$dummy'); // constant time
            return null;
        }
        $stmt = Database::get()->prepare("SELECT secret_hash FROM polls WHERE id = ?");
        $stmt->execute([$poll->id]);
        $hash = $stmt->fetchColumn();
        if ($hash === false || !password_verify($secret, $hash)) {
            return null;
        }
        return $poll;
    }

    public function findById(int $id): ?Poll
    {
        $stmt = Database::get()->prepare("SELECT * FROM polls WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_OBJ);
        return $row ? Poll::fromRow($row) : null;
    }

    public function getOptions(int $pollId): array
    {
        $stmt = Database::get()->prepare("SELECT * FROM poll_options WHERE poll_id = ? ORDER BY start_utc ASC, sort_order ASC");
        $stmt->execute([$pollId]);
        $rows = $stmt->fetchAll(PDO::FETCH_OBJ);
        return array_map(fn($r) => PollOption::fromRow($r), $rows);
    }

    public function getOptionsByIds(int $pollId, array $optionIds): array
    {
        if ($optionIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($optionIds), '?'));
        $stmt = Database::get()->prepare("SELECT * FROM poll_options WHERE poll_id = ? AND id IN ($placeholders) ORDER BY start_utc ASC");
        $stmt->execute([$pollId, ...$optionIds]);
        $rows = $stmt->fetchAll(PDO::FETCH_OBJ);
        return array_map(fn($r) => PollOption::fromRow($r), $rows);
    }

    public function listRecentForUser(int $userId, int $limit = 10): array
    {
        $stmt = Database::get()->prepare("
            SELECT p.* FROM polls p
            WHERE p.organizer_id = ?
            ORDER BY p.updated_at DESC
            LIMIT ?
        ");
        $stmt->execute([$userId, $limit]);
        $rows = $stmt->fetchAll(PDO::FETCH_OBJ);
        return array_map(fn($r) => Poll::fromRow($r), $rows);
    }

    /** Polls owned by the user (organizer_id = user). Ordered by most recent activity. */
    public function listOwnedPolls(int $userId, int $limit = 100): array
    {
        $stmt = Database::get()->prepare("
            SELECT p.* FROM polls p
            WHERE p.organizer_id = ?
            ORDER BY p.updated_at DESC
            LIMIT ?
        ");
        $stmt->execute([$userId, $limit]);
        $rows = $stmt->fetchAll(PDO::FETCH_OBJ);
        return array_map(fn($r) => Poll::fromRow($r), $rows);
    }

    /** Polls the user participated in (in poll_participants or has a vote), excluding polls they own. Ordered by updated_at. */
    public function listParticipatedPolls(int $userId, int $limit = 100): array
    {
        $stmt = Database::get()->prepare("
            SELECT DISTINCT p.*
            FROM polls p
            INNER JOIN (
                SELECT poll_id FROM poll_participants WHERE user_id = ?
                UNION
                SELECT poll_id FROM votes WHERE user_id = ?
            ) u ON u.poll_id = p.id
            WHERE p.organizer_id != ?
            ORDER BY p.updated_at DESC
            LIMIT ?
        ");
        $stmt->execute([$userId, $userId, $userId, $limit]);
        $rows = $stmt->fetchAll(PDO::FETCH_OBJ);
        return array_map(fn($r) => Poll::fromRow($r), $rows);
    }

    public function create(int $organizerId, string $slug, string $secretHash, string $title, ?string $description, ?string $location, string $timezone, int $durationMinutes = 60): Poll
    {
        $pdo = Database::get();
        $stmt = $pdo->prepare("INSERT INTO polls (organizer_id, slug, secret_hash, title, description, location, timezone, duration_minutes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$organizerId, $slug, $secretHash, $title, $description, $location, $timezone, $durationMinutes]);
        return $this->findById((int) $pdo->lastInsertId());
    }

    public function addOption(int $pollId, string $startUtc, string $endUtc, ?string $label, int $sortOrder): PollOption
    {
        $pdo = Database::get();
        $stmt = $pdo->prepare("INSERT INTO poll_options (poll_id, start_utc, end_utc, label, sort_order) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$pollId, $startUtc, $endUtc, $label, $sortOrder]);
        $stmt = $pdo->prepare("SELECT * FROM poll_options WHERE id = ?");
        $stmt->execute([$pdo->lastInsertId()]);
        return PollOption::fromRow($stmt->fetch(PDO::FETCH_OBJ));
    }

    public function lockPoll(int $pollId, int $optionId): void
    {
        $stmt = Database::get()->prepare("UPDATE polls SET locked_at = NOW(), locked_option_id = ? WHERE id = ?");
        $stmt->execute([$optionId, $pollId]);
    }

    public function generateSlug(): string
    {
        $chars = 'abcdefghjkmnpqrstuvwxyz23456789';
        $len = 12;
        $max = strlen($chars) - 1;
        do {
            $slug = '';
            for ($i = 0; $i < $len; $i++) {
                $slug .= $chars[random_int(0, $max)];
            }
            $exists = $this->findBySlug($slug) !== null;
        } while ($exists);
        return $slug;
    }

    /** Clear locked_option_id then delete poll; cascades remove options, votes, participants, invites, calendar_events. */
    public function deletePoll(int $pollId): void
    {
        $pdo = Database::get();
        $pdo->prepare("UPDATE polls SET locked_option_id = NULL WHERE id = ?")->execute([$pollId]);
        $pdo->prepare("DELETE FROM polls WHERE id = ?")->execute([$pollId]);
    }

    /** Delete one option (votes cascade). Returns true if a row was deleted. */
    public function deleteOption(int $pollId, int $optionId): bool
    {
        $stmt = Database::get()->prepare("DELETE FROM poll_options WHERE poll_id = ? AND id = ?");
        $stmt->execute([$pollId, $optionId]);
        return $stmt->rowCount() > 0;
    }
}
