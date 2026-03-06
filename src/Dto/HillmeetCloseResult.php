<?php

declare(strict_types=1);

namespace Hillmeet\Dto;

/**
 * Result of closing a poll.
 *
 * finalSlot, when present, has start and end (e.g. ISO8601).
 * When notify was true, notified and calendar_event_created indicate outcome.
 *
 * @property bool        $closed    Whether the poll was closed.
 * @property array|null   $finalSlot Optional chosen slot: ['start' => string, 'end' => string].
 * @property string       $summary   Short human-readable summary.
 * @property bool|null    $notified  When notify was true: whether lock notification emails were sent.
 * @property bool|null    $calendar_event_created When notify was true and organizer has OAuth: whether a Google Calendar event was created.
 */
final readonly class HillmeetCloseResult
{
    public function __construct(
        public bool $closed,
        /** @var array{start: string, end: string}|null */
        public ?array $finalSlot,
        public string $summary,
        public ?bool $notified = null,
        public ?bool $calendar_event_created = null,
    ) {
    }
}
