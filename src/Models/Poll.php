<?php

declare(strict_types=1);

/**
 * Poll.php
 * Purpose: Poll model (fromRow, isLocked, isOrganizer).
 * Project: Hillmeet
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 */

namespace Hillmeet\Models;

use stdClass;

final class Poll
{
    public int $id;
    public int $organizer_id;
    public string $slug;
    public string $title;
    public ?string $description;
    public ?string $location;
    public string $timezone;
    public int $duration_minutes;
    public ?string $locked_at;
    public ?int $locked_option_id;
    public string $created_at;
    public string $updated_at;

    public static function fromRow(stdClass $row): self
    {
        $p = new self();
        $p->id = (int) $row->id;
        $p->organizer_id = (int) $row->organizer_id;
        $p->slug = $row->slug;
        $p->title = $row->title;
        $p->description = $row->description ?? null;
        $p->location = $row->location ?? null;
        $p->timezone = $row->timezone ?? 'UTC';
        $p->duration_minutes = isset($row->duration_minutes) ? (int) $row->duration_minutes : 60;
        $p->locked_at = $row->locked_at ?? null;
        $p->locked_option_id = isset($row->locked_option_id) ? (int) $row->locked_option_id : null;
        $p->created_at = $row->created_at;
        $p->updated_at = $row->updated_at;
        return $p;
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }

    public function isOrganizer(int $userId): bool
    {
        return $this->organizer_id === $userId;
    }
}
