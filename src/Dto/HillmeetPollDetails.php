<?php

declare(strict_types=1);

namespace Hillmeet\Dto;

/**
 * Full poll details.
 *
 * options[]: each entry has start, end (ISO8601 in poll timezone).
 * participants[]: each entry has email (canonical, normalized), name? (optional).
 *
 * @property string   $pollId      Poll identifier (slug).
 * @property string   $title       Poll title.
 * @property string|null $description Optional poll description.
 * @property string|null $location Optional poll location (e.g. "Zoom", "Room 401").
 * @property string   $timezone    Poll timezone (e.g. America/Los_Angeles).
 * @property string   $created_at  Poll creation time (ISO8601 UTC).
 * @property list<array{start: string, end: string}> $options     Time options in poll timezone (ISO8601).
 * @property list<array{email: string, name?: string}> $participants
 * @property bool     $closed      Whether the poll is closed.
 * @property string|null $shareUrl Full shareable URL (with secret) when available for the owner; null otherwise.
 *
 * Project: Hillmeet
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 */
final readonly class HillmeetPollDetails
{
    public function __construct(
        public string $pollId,
        public string $title,
        public ?string $description,
        public ?string $location,
        public string $timezone,
        public string $created_at,
        /** @var list<array{start: string, end: string}> */
        public array $options,
        /** @var list<array{email: string, name?: string}> */
        public array $participants,
        public bool $closed,
        public ?string $shareUrl = null,
    ) {
    }
}
