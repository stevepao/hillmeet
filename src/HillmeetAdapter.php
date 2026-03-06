<?php

declare(strict_types=1);

namespace Hillmeet;

use Hillmeet\Dto\HillmeetAvailabilityResult;
use Hillmeet\Dto\HillmeetCloseResult;
use Hillmeet\Dto\HillmeetNonrespondersResult;
use Hillmeet\Dto\HillmeetPollDetails;
use Hillmeet\Dto\HillmeetPollListResult;
use Hillmeet\Dto\HillmeetPollResult;

/**
 * Stable facade between MCP tools and Hillmeet business logic.
 *
 * All methods use ownerEmail as the logical owner identity. The adapter is responsible
 * for normalizing the email (e.g. lowercase/trim) and resolving it to the internal
 * user_id before calling repositories and services. MCP tools and callers never
 * see or use numeric user IDs.
 *
 * Only operations required for v1 are exposed; additional operations (e.g. deletePoll,
 * addTimeOption) will be added when new MCP tools need them.
 *
 * Project: Hillmeet
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 */
interface HillmeetAdapter
{
    /**
     * Create a new poll for the given owner.
     *
     * @param string $ownerEmail Logical owner identity (will be normalized and mapped to internal user_id).
     * @param array  $payload    Poll creation payload (title, options, invitees, etc. as defined by caller).
     */
    public function createPoll(string $ownerEmail, array $payload): HillmeetPollResult;

    /**
     * Find availability for a poll given optional constraints.
     *
     * @param string $ownerEmail Logical owner identity (mapped to internal user_id).
     * @param string $pollId    Poll identifier (e.g. slug or UUID).
     * @param array  $constraints Optional filters (e.g. date range, min participants).
     */
    public function findAvailability(string $ownerEmail, string $pollId, array $constraints): HillmeetAvailabilityResult;

    /**
     * List participants who have not yet responded to the poll.
     *
     * @param string $ownerEmail Logical owner identity (mapped to internal user_id).
     * @param string $pollId    Poll identifier.
     */
    public function listNonresponders(string $ownerEmail, string $pollId): HillmeetNonrespondersResult;

    /**
     * Close the poll, optionally with a final chosen slot and notification.
     *
     * @param string   $ownerEmail Logical owner identity (mapped to internal user_id).
     * @param string   $pollId     Poll identifier.
     * @param array|null $finalSlot Final slot if one was chosen (e.g. ['start' => ISO8601, 'end' => ISO8601]).
     * @param bool     $notify     Whether to notify participants.
     */
    public function closePoll(string $ownerEmail, string $pollId, ?array $finalSlot, bool $notify): HillmeetCloseResult;

    /**
     * Get full poll details for the given poll.
     *
     * @param string $ownerEmail Logical owner identity (mapped to internal user_id).
     * @param string $pollId    Poll identifier.
     */
    public function getPoll(string $ownerEmail, string $pollId): HillmeetPollDetails;

    /**
     * List polls owned by the given owner (organizer).
     *
     * @param string $ownerEmail Logical owner identity (mapped to internal user_id).
     */
    public function listPolls(string $ownerEmail): HillmeetPollListResult;
}
