<?php

declare(strict_types=1);

namespace Hillmeet\Dto;

/**
 * Result of listing polls owned by the current user.
 *
 * Each poll has: poll_id (slug), title, created_at, timezone, status (open|closed), share_url.
 *
 * @property list<array{poll_id: string, title: string, created_at: string, timezone: string, status: string, share_url: string}> $polls
 * @property string $summary Short human-readable summary (e.g. "You have N polls. Most recent: 'X'.")
 */
final readonly class HillmeetPollListResult
{
    /** @param list<array{poll_id: string, title: string, created_at: string, timezone: string, status: string, share_url: string}> $polls */
    public function __construct(
        public array $polls,
        public string $summary,
    ) {
    }
}
