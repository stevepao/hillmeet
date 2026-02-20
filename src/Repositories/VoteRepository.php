<?php

declare(strict_types=1);

namespace Hillmeet\Repositories;

use Hillmeet\Support\Database;
use PDO;

final class VoteRepository
{
    /** @return 'yes'|'maybe'|'no'|null */
    public function getVote(int $pollOptionId, int $userId): ?string
    {
        $stmt = Database::get()->prepare("SELECT vote FROM votes WHERE poll_option_id = ? AND user_id = ?");
        $stmt->execute([$pollOptionId, $userId]);
        $v = $stmt->fetchColumn();
        return $v !== false ? $v : null;
    }

    public function hasVoteInPoll(int $pollId, int $userId): bool
    {
        $stmt = Database::get()->prepare("SELECT 1 FROM votes WHERE poll_id = ? AND user_id = ? LIMIT 1");
        $stmt->execute([$pollId, $userId]);
        return $stmt->fetchColumn() !== false;
    }

    /** @return array<int> distinct user_ids who have at least one vote in this poll */
    public function getDistinctVoterIds(int $pollId): array
    {
        $stmt = Database::get()->prepare("SELECT DISTINCT user_id FROM votes WHERE poll_id = ?");
        $stmt->execute([$pollId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @return array<object{id, email, name}> distinct users who have voted in this poll (for diagnostics) */
    public function getVotersWithUsers(int $pollId): array
    {
        $stmt = Database::get()->prepare("
            SELECT DISTINCT u.id, u.email, u.name
            FROM votes v
            JOIN users u ON u.id = v.user_id
            WHERE v.poll_id = ?
            ORDER BY u.name, u.email
        ");
        $stmt->execute([$pollId]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function setVote(int $pollId, int $pollOptionId, int $userId, string $vote): void
    {
        $pdo = Database::get();
        $stmt = $pdo->prepare("INSERT INTO votes (poll_id, poll_option_id, user_id, vote) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE vote = VALUES(vote), updated_at = NOW()");
        $stmt->execute([$pollId, $pollOptionId, $userId, $vote]);
    }

    public function removeVote(int $pollId, int $pollOptionId, int $userId): void
    {
        $stmt = Database::get()->prepare("DELETE FROM votes WHERE poll_id = ? AND poll_option_id = ? AND user_id = ?");
        $stmt->execute([$pollId, $pollOptionId, $userId]);
    }

    /** @return array<int, string> option_id => vote (yes|maybe|no) for this user in this poll */
    public function getVotesForUser(int $pollId, int $userId): array
    {
        $stmt = Database::get()->prepare("SELECT poll_option_id, vote FROM votes WHERE poll_id = ? AND user_id = ?");
        $stmt->execute([$pollId, $userId]);
        $out = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $out[(int) $row['poll_option_id']] = $row['vote'];
        }
        return $out;
    }

    /** @return array<int, array{yes: int, maybe: int, no: int}> option_id => counts */
    public function getTotalsByPoll(int $pollId): array
    {
        $stmt = Database::get()->prepare("
            SELECT poll_option_id, vote, COUNT(*) AS cnt
            FROM votes
            WHERE poll_id = ?
            GROUP BY poll_option_id, vote
        ");
        $stmt->execute([$pollId]);
        $out = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $id = (int) $row['poll_option_id'];
            if (!isset($out[$id])) {
                $out[$id] = ['yes' => 0, 'maybe' => 0, 'no' => 0];
            }
            $out[$id][$row['vote']] = (int) $row['cnt'];
        }
        return $out;
    }

    /** @return array<int, array<int, string>> option_id => [ user_id => vote ] */
    public function getMatrix(int $pollId): array
    {
        $stmt = Database::get()->prepare("SELECT poll_option_id, user_id, vote FROM votes WHERE poll_id = ?");
        $stmt->execute([$pollId]);
        $out = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $optId = (int) $row['poll_option_id'];
            if (!isset($out[$optId])) {
                $out[$optId] = [];
            }
            $out[$optId][(int) $row['user_id']] = $row['vote'];
        }
        return $out;
    }

    /** Weighted score: Works=2, If needed=1, Can't=0. Tiebreak: earliest start. */
    public function getBestOptionId(int $pollId, array $optionIdsOrdered): ?int
    {
        $totals = $this->getTotalsByPoll($pollId);
        $bestId = null;
        $bestScore = -1;
        $bestStart = null;
        foreach ($optionIdsOrdered as $opt) {
            $id = is_object($opt) ? $opt->id : (int) $opt;
            $t = $totals[$id] ?? ['yes' => 0, 'maybe' => 0, 'no' => 0];
            $score = $t['yes'] * 2 + $t['maybe'];
            $stmt = Database::get()->prepare("SELECT start_utc FROM poll_options WHERE id = ?");
            $stmt->execute([$id]);
            $start = $stmt->fetchColumn();
            if ($score > $bestScore || ($score === $bestScore && $start !== false && ($bestStart === null || $start < $bestStart))) {
                $bestScore = $score;
                $bestId = $id;
                $bestStart = $start ?: null;
            }
        }
        return $bestId;
    }
}
