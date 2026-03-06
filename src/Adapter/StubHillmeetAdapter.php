<?php

declare(strict_types=1);

/**
 * StubHillmeetAdapter.php
 * Purpose: Stub adapter for MCP tool development and tests. createPoll returns a fake result; other methods throw.
 * Project: Hillmeet
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 */

namespace Hillmeet\Adapter;

use Hillmeet\Dto\HillmeetAvailabilityResult;
use Hillmeet\Dto\HillmeetCloseResult;
use Hillmeet\Dto\HillmeetNonrespondersResult;
use Hillmeet\Dto\HillmeetPollDetails;
use Hillmeet\Dto\HillmeetPollListResult;
use Hillmeet\Dto\HillmeetPollResult;
use Hillmeet\HillmeetAdapter as HillmeetAdapterInterface;

/**
 * Stub adapter for MCP tool development and tests.
 * createPoll returns a fake result; other methods throw.
 */
final class StubHillmeetAdapter implements HillmeetAdapterInterface
{
    public function __construct(
        private readonly string $baseUrl = 'https://meet.hillwork.net',
    ) {
    }

    public function createPoll(string $ownerEmail, array $payload): HillmeetPollResult
    {
        $slug = 'stub-' . bin2hex(random_bytes(4));
        $pollId = $slug;
        $shareUrl = rtrim($this->baseUrl, '/') . '/poll/' . $slug;
        $title = $payload['title'] ?? 'Poll';
        $timezone = isset($payload['timezone']) && \is_string($payload['timezone']) ? trim($payload['timezone']) : 'UTC';
        return new HillmeetPollResult(
            $pollId,
            $shareUrl,
            "Poll \"{$title}\" created. Share: {$shareUrl}",
            $timezone,
        );
    }

    public function findAvailability(string $ownerEmail, string $pollId, array $constraints): HillmeetAvailabilityResult
    {
        $shareUrl = rtrim($this->baseUrl, '/') . '/poll/' . $pollId;
        $bestSlots = [
            [
                'start' => '2026-02-24T14:00:00+00:00',
                'end' => '2026-02-24T14:30:00+00:00',
                'available_count' => 2,
                'total_invited' => 3,
                'available_emails' => ['a@example.com', 'b@example.com'],
                'unavailable_emails' => ['c@example.com'],
            ],
        ];
        return new HillmeetAvailabilityResult($bestSlots, 'Best slot: 2026-02-24T14:00:00+00:00–2026-02-24T14:30:00+00:00 UTC (2 of 3 available).', $shareUrl);
    }

    public function listNonresponders(string $ownerEmail, string $pollId): HillmeetNonrespondersResult
    {
        $list = [
            ['email' => 'lee@example.com'],
            ['email' => 'morgan@example.com', 'name' => 'Morgan'],
        ];
        return new HillmeetNonrespondersResult($list, '2 person(s) haven\'t responded yet: lee@example.com, morgan@example.com.');
    }

    public function closePoll(string $ownerEmail, string $pollId, ?array $finalSlot, bool $notify): HillmeetCloseResult
    {
        $slot = $finalSlot !== null
            ? ['start' => $finalSlot['start'] ?? '', 'end' => $finalSlot['end'] ?? '']
            : null;
        $summary = 'Poll closed. Final time selected: ' . ($slot ? $slot['start'] . ' – ' . $slot['end'] : 'N/A') . '.';
        return new HillmeetCloseResult(true, $slot, $summary, $notify ? true : null, $notify ? true : null);
    }

    public function getPoll(string $ownerEmail, string $pollId): HillmeetPollDetails
    {
        $options = [
            ['start' => '2026-02-24T14:00:00+00:00', 'end' => '2026-02-24T14:30:00+00:00'],
            ['start' => '2026-02-24T15:00:00+00:00', 'end' => '2026-02-24T15:30:00+00:00'],
        ];
        $participants = [
            ['email' => 'alice@example.com', 'name' => 'Alice'],
            ['email' => 'bob@example.com'],
        ];
        return new HillmeetPollDetails(
            $pollId ?: 'stub-slug',
            'Stub poll',
            null,
            null,
            'UTC',
            '2026-02-24T12:00:00+00:00',
            $options,
            $participants,
            false,
            null,
        );
    }

    public function listPolls(string $ownerEmail): HillmeetPollListResult
    {
        $polls = [
            [
                'poll_id' => 'stub-abc123',
                'title' => 'Stub poll',
                'created_at' => '2026-02-24 12:00:00',
                'timezone' => 'UTC',
                'status' => 'open',
                'share_url' => rtrim($this->baseUrl, '/') . '/poll/stub-abc123',
            ],
        ];
        return new HillmeetPollListResult($polls, "You have 1 poll. Most recent: 'Stub poll'.");
    }
}
