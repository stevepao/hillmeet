<?php

declare(strict_types=1);

namespace Hillmeet\Services;

/**
 * Internal DTO for poll details (core layer). Options use UTC DateTimeImmutable; adapter localizes for MCP.
 *
 * @property string       $pollId Slug
 * @property string       $title
 * @property string|null  $description Optional poll description
 * @property string|null  $location Optional poll location (e.g. "Zoom", "Room 401")
 * @property string       $timezone
 * @property string       $status 'open'|'closed'
 * @property string       $created_at From DB (e.g. Y-m-d H:i:s)
 * @property list<array{start_utc: \DateTimeImmutable, end_utc: \DateTimeImmutable}> $options
 * @property list<array{email: string, name?: string}> $participants Normalized emails
 */
final readonly class PollDetailsData
{
    /** @param list<array{start_utc: \DateTimeImmutable, end_utc: \DateTimeImmutable}> $options */
    /** @param list<array{email: string, name?: string}> $participants */
    public function __construct(
        public string $pollId,
        public string $title,
        public ?string $description,
        public ?string $location,
        public string $timezone,
        public string $status,
        public string $created_at,
        public array $options,
        public array $participants,
    ) {
    }
}
