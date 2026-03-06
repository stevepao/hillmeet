<?php

declare(strict_types=1);

namespace Hillmeet\Dto;

/**
 * Result of listing non-responders for a poll.
 *
 * Each entry in nonresponders has: email (canonical, normalized), name? (optional display name).
 *
 * @property list<array{email: string, name?: string}> $nonresponders
 * @property string $summary Short human-readable summary.
 *
 * Project: Hillmeet
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 */
final readonly class HillmeetNonrespondersResult
{
    public function __construct(
        /** @var list<array{email: string, name?: string}> */
        public array $nonresponders,
        public string $summary,
    ) {
    }
}
