<?php

declare(strict_types=1);

namespace Hillmeet\Repositories;

use Hillmeet\Support\Database;
use PDO;

final class GoogleCalendarSelectionRepository
{
    /** @return array<object{calendar_id, calendar_summary, selected, tentative_as_busy}> */
    public function getForUser(int $userId): array
    {
        $stmt = Database::get()->prepare("SELECT calendar_id, calendar_summary, selected, tentative_as_busy FROM google_calendar_selections WHERE user_id = ? ORDER BY calendar_summary");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function setSelected(int $userId, string $calendarId, string $summary, bool $selected, bool $tentativeAsBusy = true): void
    {
        $stmt = Database::get()->prepare("
            INSERT INTO google_calendar_selections (user_id, calendar_id, calendar_summary, selected, tentative_as_busy)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE selected = VALUES(selected), tentative_as_busy = VALUES(tentative_as_busy), updated_at = NOW()
        ");
        $stmt->execute([$userId, $calendarId, $summary, $selected ? 1 : 0, $tentativeAsBusy ? 1 : 0]);
    }

    public function saveList(int $userId, array $calendars): void
    {
        foreach ($calendars as $cal) {
            $this->setSelected(
                $userId,
                $cal['id'],
                $cal['summary'] ?? $cal['id'],
                (bool) ($cal['selected'] ?? true),
                (bool) ($cal['tentative_as_busy'] ?? true)
            );
        }
    }

    /** @return array<string> calendar ids that are selected */
    public function getSelectedCalendarIds(int $userId): array
    {
        $stmt = Database::get()->prepare("SELECT calendar_id FROM google_calendar_selections WHERE user_id = ? AND selected = 1");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getTentativeAsBusy(int $userId): bool
    {
        $stmt = Database::get()->prepare("SELECT tentative_as_busy FROM google_calendar_selections WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $v = $stmt->fetchColumn();
        return $v !== false && (int) $v === 1;
    }

    public function setTentativeAsBusy(int $userId, bool $value): void
    {
        $stmt = Database::get()->prepare("UPDATE google_calendar_selections SET tentative_as_busy = ? WHERE user_id = ?");
        $stmt->execute([$value ? 1 : 0, $userId]);
    }
}
