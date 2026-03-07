<?php

declare(strict_types=1);

/**
 * PollContext.php
 * Purpose: Canonical result of poll resolution + access check. Used by controllers and adapters.
 * Project: Hillmeet
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 */

namespace Hillmeet\Support;

use Hillmeet\Models\Poll;

final readonly class PollContext
{
    public function __construct(
        public Poll $poll,
        public int $pollId,
        public string $pollSlug,
        public string $timezone,
        public bool $closed,
        public ?string $shareUrl,
        public AccessMode $accessMode,
        public bool $isOrganizer,
        public string $actorEmail,
        /** @var object{id: int, poll_id: int, email: string}|null */
        public ?object $invite,
        public bool $canVote,
        public bool $canViewResults,
        public bool $canLock,
        public bool $canClose,
    ) {
    }

    public function poll(): Poll
    {
        return $this->poll;
    }
}
