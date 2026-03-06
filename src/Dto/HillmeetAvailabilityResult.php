<?php

declare(strict_types=1);

namespace Hillmeet\Dto;

/**
 * Result of finding availability for a poll.
 *
 * Each entry in bestSlots has: start (ISO8601), end (ISO8601), available_count,
 * total_invited, available_emails[], unavailable_emails[].
 *
 * @property list<array{start: string, end: string, available_count: int, total_invited: int, available_emails: list<string>, unavailable_emails: list<string>}> $bestSlots
 * @property string $summary  Short human-readable summary.
 * @property string $shareUrl Full URL to the poll.
 *
 * Project: Hillmeet
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 */
final readonly class HillmeetAvailabilityResult
{
    public function __construct(
        /** @var list<array{start: string, end: string, available_count: int, total_invited: int, available_emails: list<string>, unavailable_emails: list<string>}> */
        public array $bestSlots,
        public string $summary,
        public string $shareUrl,
    ) {
    }
}
