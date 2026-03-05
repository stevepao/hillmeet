<?php

declare(strict_types=1);

namespace Hillmeet\Dto;

/**
 * Full poll details.
 *
 * options[]: each entry has start, end (ISO8601 UTC).
 * participants[]: each entry has email (canonical, normalized), name? (optional).
 *
 * @property string   $pollId      Poll identifier.
 * @property string   $title       Poll title.
 * @property string   $timezone    Poll timezone (e.g. America/Los_Angeles).
 * @property list<array{start: string, end: string}> $options     Time options in ISO8601 UTC.
 * @property list<array{email: string, name?: string}> $participants
 * @property bool     $closed      Whether the poll is closed.
 */
final readonly class HillmeetPollDetails
{
    public function __construct(
        public string $pollId,
        public string $title,
        public string $timezone,
        /** @var list<array{start: string, end: string}> */
        public array $options,
        /** @var list<array{email: string, name?: string}> */
        public array $participants,
        public bool $closed,
    ) {
    }
}
