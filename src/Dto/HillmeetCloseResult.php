<?php

declare(strict_types=1);

namespace Hillmeet\Dto;

/**
 * Result of closing a poll.
 *
 * finalSlot, when present, has start and end (e.g. ISO8601).
 *
 * @property bool        $closed    Whether the poll was closed.
 * @property array|null   $finalSlot Optional chosen slot: ['start' => string, 'end' => string].
 * @property string       $summary   Short human-readable summary.
 */
final readonly class HillmeetCloseResult
{
    public function __construct(
        public bool $closed,
        /** @var array{start: string, end: string}|null */
        public ?array $finalSlot,
        public string $summary,
    ) {
    }
}
