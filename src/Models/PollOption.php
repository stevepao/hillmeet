<?php

declare(strict_types=1);

/**
 * PollOption.php
 * Purpose: Poll option model (fromRow).
 * Project: Hillmeet
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 */

namespace Hillmeet\Models;

use stdClass;

final class PollOption
{
    public int $id;
    public int $poll_id;
    public string $start_utc;
    public string $end_utc;
    public ?string $label;
    public int $sort_order;
    public string $created_at;

    public static function fromRow(stdClass $row): self
    {
        $o = new self();
        $o->id = (int) $row->id;
        $o->poll_id = (int) $row->poll_id;
        $o->start_utc = $row->start_utc;
        $o->end_utc = $row->end_utc;
        $o->label = $row->label ?? null;
        $o->sort_order = (int) $row->sort_order;
        $o->created_at = $row->created_at;
        return $o;
    }
}
