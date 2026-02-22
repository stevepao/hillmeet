<?php

declare(strict_types=1);

/**
 * CalendarEventRepository.php
 * Purpose: Record of Google Calendar events created from locked polls.
 * Project: Hillmeet
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 */

namespace Hillmeet\Repositories;

use Hillmeet\Support\Database;
use PDO;

final class CalendarEventRepository
{
    public function create(int $pollId, int $pollOptionId, int $userId, string $calendarId, string $eventId): void
    {
        $stmt = Database::get()->prepare("INSERT INTO calendar_events (poll_id, poll_option_id, user_id, calendar_id, event_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$pollId, $pollOptionId, $userId, $calendarId, $eventId]);
    }

    public function existsForPollAndOption(int $pollId, int $pollOptionId): bool
    {
        $stmt = Database::get()->prepare("SELECT 1 FROM calendar_events WHERE poll_id = ? AND poll_option_id = ? LIMIT 1");
        $stmt->execute([$pollId, $pollOptionId]);
        return $stmt->fetchColumn() !== false;
    }
}
