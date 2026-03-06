<?php

declare(strict_types=1);

/**
 * AvailabilityService.php
 * Purpose: Compute best time slots for a poll given votes and optional constraints.
 * Project: Hillmeet
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 */

namespace Hillmeet\Services;

use Hillmeet\Models\PollOption;
use Hillmeet\Repositories\PollInviteRepository;
use Hillmeet\Repositories\PollParticipantRepository;
use Hillmeet\Repositories\PollRepository;
use Hillmeet\Repositories\VoteRepository;

/**
 * Internal availability logic. Does not know about MCP.
 *
 * @phpstan-type SlotShape array{
 *   option_id: int,
 *   start_utc: \DateTimeImmutable,
 *   end_utc: \DateTimeImmutable,
 *   available_emails: list<string>,
 *   unavailable_emails: list<string>,
 *   available_count: int,
 *   total_invited: int,
 *   score: float
 * }
 */
final class AvailabilityService
{
    /** Score for options that fail min_attendees (demoted but still returned). */
    private const MIN_ATTENDEES_FAIL_SCORE = -1000.0;

    /** Bonus added to score when option falls within a prefer_times window. */
    private const PREFER_TIMES_BOOST = 100.0;

    public function __construct(
        private readonly PollRepository $pollRepository,
        private readonly VoteRepository $voteRepository,
        private readonly PollParticipantRepository $participantRepository,
        private readonly PollInviteRepository $inviteRepository,
    ) {
    }

    /**
     * Compute best slots for a poll. Options are sorted by score descending.
     *
     * Verification points:
     * 1. Email normalization: cohort and userIdToEmail use strtolower(trim()); exclude list normalized in normalizeExcludeEmails.
     * 2. Excluded emails: removed from cohort in buildCohort() before any scoring; cohort is built once and used for all options.
     * 3. Available vs unavailable: from vote matrix (yes/maybe => available); unavailable = cohort \ available; only cohort members counted.
     * 4. DateTimeImmutable: all created with new \DateTimeZone('UTC') for option times and prefer_times windows.
     * 5. prefer_times: score boost only when optionOverlapsWindow() is true (strict overlap: optStart < winEnd && optEnd > winStart).
     * 6. min_attendees: options with available_count < min_attendees get MIN_ATTENDEES_FAIL_SCORE + small tiebreak (not dropped).
     * 7. Sort: usort by score descending ($b['score'] <=> $a['score']) before return.
     *
     * @param int   $userId      Organizer user id (must own the poll).
     * @param int   $pollId     Internal poll id.
     * @param array $constraints min_attendees (int?), prefer_times (list of {start,end} ISO8601?), exclude_emails (list?)
     * @return list<SlotShape>
     */
    public function computeBestSlots(int $userId, int $pollId, array $constraints = []): array
    {
        // --- Authorization: only the poll organizer can compute availability ---
        $poll = $this->pollRepository->findById($pollId);
        if ($poll === null || $poll->organizer_id !== $userId) {
            return [];
        }

        $options = $this->pollRepository->getOptions($pollId);
        if ($options === []) {
            return [];
        }

        // --- (1) Email normalization: exclude_emails → lowercase set; (2) excluded emails removed from cohort ---
        $excludeEmails = $this->normalizeExcludeEmails($constraints['exclude_emails'] ?? []);
        $cohort = $this->buildCohort($pollId, $excludeEmails); // cohort keys = normalized emails; excluded are NOT in cohort

        // --- (1) Email normalization: user_id → email map uses lowercase trim for all participant emails ---
        $userIdToEmail = $this->buildUserIdToEmail($pollId);

        // --- Vote data: option_id => [ user_id => 'yes'|'maybe'|'no' ] ---
        $matrix = $this->voteRepository->getMatrix($pollId);

        $minAttendees = isset($constraints['min_attendees']) && \is_int($constraints['min_attendees'])
            ? max(0, $constraints['min_attendees'])
            : null;

        // --- (4) prefer_times windows parsed as DateTimeImmutable in UTC ---
        $preferTimes = $this->normalizePreferTimes($constraints['prefer_times'] ?? []);

        $slots = [];
        foreach ($options as $option) {
            // --- (3) Available: cohort members who voted yes or maybe for this option (using internal vote matrix) ---
            $availableEmails = [];
            foreach ($matrix[$option->id] ?? [] as $uid => $vote) {
                if (($vote === 'yes' || $vote === 'maybe') && isset($userIdToEmail[$uid])) {
                    $email = $userIdToEmail[$uid];
                    if (isset($cohort[$email])) { // only count if still in cohort (excluded were already removed)
                        $availableEmails[] = $email;
                    }
                }
            }
            $availableEmails = array_values(array_unique($availableEmails));

            // --- (3) Unavailable: everyone in cohort who is not in available (no vote, or voted 'no') ---
            $unavailableEmails = array_values(array_diff(array_keys($cohort), $availableEmails));

            $availableCount = \count($availableEmails);
            $totalInvited = \count($cohort); // (2) total_invited is after exclusions

            // --- (5) prefer_times overlap check inside computeScore; (6) min_attendees penalty applied there ---
            $score = $this->computeScore($availableCount, $totalInvited, $option, $minAttendees, $preferTimes);

            // --- (4) Option times created as DateTimeImmutable in UTC ---
            $slots[] = [
                'option_id' => $option->id,
                'start_utc' => new \DateTimeImmutable($option->start_utc, new \DateTimeZone('UTC')),
                'end_utc' => new \DateTimeImmutable($option->end_utc, new \DateTimeZone('UTC')),
                'available_emails' => $availableEmails,
                'unavailable_emails' => $unavailableEmails,
                'available_count' => $availableCount,
                'total_invited' => $totalInvited,
                'score' => $score,
            ];
        }

        // --- (7) Sort by score descending (best first) ---
        usort($slots, static fn(array $a, array $b): int => (int) (($b['score'] <=> $a['score']) ?: 0));
        return $slots;
    }

    /**
     * (1) Email normalization for exclude list: lowercase + trim; invalid entries skipped.
     *
     * @param list<string> $emails
     * @return array<string, true> normalized lowercase set
     */
    private function normalizeExcludeEmails(array $emails): array
    {
        $out = [];
        foreach ($emails as $e) {
            if (\is_string($e) && $e !== '') {
                $out[strtolower(trim($e))] = true;
            }
        }
        return $out;
    }

    /**
     * (2) Cohort = participants + invitees by email, normalized (lowercase). Excluded emails are REMOVED here
     * so they are not in cohort and thus never in available_emails or unavailable_emails; total_invited excludes them.
     *
     * @param array<string, true> $excludeEmails
     * @return array<string, true>
     */
    private function buildCohort(int $pollId, array $excludeEmails): array
    {
        $emails = [];
        foreach ($this->participantRepository->getResultsParticipants($pollId) as $u) {
            $e = isset($u->email) && $u->email !== '' ? strtolower(trim($u->email)) : null;
            if ($e !== null) {
                $emails[$e] = true;
            }
        }
        foreach ($this->inviteRepository->getInvitedEmails($pollId) as $e) {
            $e = strtolower(trim($e));
            if ($e !== '') {
                $emails[$e] = true;
            }
        }
        foreach (array_keys($excludeEmails) as $ex) {
            unset($emails[$ex]);
        }
        return $emails;
    }

    /**
     * (1) Build user_id → normalized email for mapping vote matrix (user_id) to cohort (email).
     *
     * @return array<int, string> user_id => email
     */
    private function buildUserIdToEmail(int $pollId): array
    {
        $map = [];
        foreach ($this->participantRepository->getResultsParticipants($pollId) as $u) {
            $map[(int) $u->id] = isset($u->email) && $u->email !== '' ? strtolower(trim($u->email)) : '';
        }
        return $map;
    }

    /**
     * (4) Parse prefer_times windows as DateTimeImmutable in UTC; invalid or non-positive windows skipped.
     *
     * @param list<array{start?: mixed, end?: mixed}> $preferTimes
     * @return list<array{start: \DateTimeImmutable, end: \DateTimeImmutable}>
     */
    private function normalizePreferTimes(array $preferTimes): array
    {
        $out = [];
        foreach ($preferTimes as $w) {
            if (!\is_array($w) || !isset($w['start'], $w['end']) || !\is_string($w['start']) || !\is_string($w['end'])) {
                continue;
            }
            try {
                $start = new \DateTimeImmutable($w['start'], new \DateTimeZone('UTC'));
                $end = new \DateTimeImmutable($w['end'], new \DateTimeZone('UTC'));
                if ($end > $start) {
                    $out[] = ['start' => $start, 'end' => $end];
                }
            } catch (\Exception) {
                continue;
            }
        }
        return $out;
    }

    /**
     * (5) prefer_times: boost only when option overlaps window (optionOverlapsWindow).
     * (6) min_attendees: if set and available_count < min_attendees, return very negative score (option not dropped, just demoted).
     * (4) Option start/end interpreted in UTC when building overlap check.
     *
     * @param list<array{start: \DateTimeImmutable, end: \DateTimeImmutable}> $preferTimes
     */
    private function computeScore(
        int $availableCount,
        int $totalInvited,
        PollOption $option,
        ?int $minAttendees,
        array $preferTimes,
    ): float {
        if ($minAttendees !== null && $availableCount < $minAttendees) {
            // (6) Penalize: very negative score so they sort last; small tiebreak by available_count
            return self::MIN_ATTENDEES_FAIL_SCORE + ($availableCount * 0.01);
        }
        $optionStart = new \DateTimeImmutable($option->start_utc, new \DateTimeZone('UTC'));
        $optionEnd = new \DateTimeImmutable($option->end_utc, new \DateTimeZone('UTC'));
        $score = (float) $availableCount;
        foreach ($preferTimes as $w) {
            // (5) Boost only when slot overlaps the window (strict interval overlap)
            if ($this->optionOverlapsWindow($optionStart, $optionEnd, $w['start'], $w['end'])) {
                $score += self::PREFER_TIMES_BOOST;
                break;
            }
        }
        return $score;
    }

    /**
     * (5) True iff option time range overlaps preferred window (both intervals half-open style: optStart < winEnd && optEnd > winStart).
     */
    private function optionOverlapsWindow(
        \DateTimeImmutable $optStart,
        \DateTimeImmutable $optEnd,
        \DateTimeImmutable $winStart,
        \DateTimeImmutable $winEnd,
    ): bool {
        return $optStart < $winEnd && $optEnd > $winStart;
    }
}
