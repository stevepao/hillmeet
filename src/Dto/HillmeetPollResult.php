<?php

declare(strict_types=1);

namespace Hillmeet\Dto;

/**
 * Result of creating a poll.
 */
final readonly class HillmeetPollResult
{
    public function __construct(
        public string $pollId,
        public string $shareUrl,
        public string $summary,
        public string $timezone,
    ) {
    }
}
