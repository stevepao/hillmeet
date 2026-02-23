<?php

declare(strict_types=1);

/**
 * PollService.php
 * Purpose: Poll CRUD, options, votes, lock, invites, results, notifications.
 * Project: Hillmeet
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 */

namespace Hillmeet\Services;

use Hillmeet\Models\Poll;
use Hillmeet\Models\PollOption;
use Hillmeet\Repositories\CalendarEventRepository;
use Hillmeet\Repositories\FreebusyCacheRepository;
use Hillmeet\Repositories\GoogleCalendarSelectionRepository;
use Hillmeet\Repositories\OAuthConnectionRepository;
use Hillmeet\Repositories\PollInviteRepository;
use Hillmeet\Repositories\PollParticipantRepository;
use Hillmeet\Repositories\PollRepository;
use Hillmeet\Repositories\UserRepository;
use Hillmeet\Repositories\VoteRepository;
use Hillmeet\Support\AuditLog;
use Hillmeet\Support\RateLimit;
use function Hillmeet\Support\config;

final class PollService
{
    public function __construct(
        private PollRepository $pollRepo,
        private VoteRepository $voteRepo,
        private PollParticipantRepository $participantRepo,
        private PollInviteRepository $inviteRepo,
        private EmailService $emailService
    ) {}

    public function createPoll(int $organizerId, array $input, string $ip): array
    {
        if (!RateLimit::check('poll_create:' . $ip, (int) config('rate.poll_create'))) {
            return ['error' => 'Too many polls created. Please wait a minute.'];
        }
        $title = trim($input['title'] ?? '');
        if ($title === '') {
            return ['error' => 'Please enter a title.'];
        }
        $timezone = trim($input['timezone'] ?? 'UTC') ?: 'UTC';
        $durationMinutes = max(5, min(1440, (int) ($input['duration_minutes'] ?? 60)));
        $slug = $this->pollRepo->generateSlug();
        $secret = bin2hex(random_bytes(16));
        $secretHash = password_hash($secret, PASSWORD_DEFAULT);
        $poll = $this->pollRepo->create(
            $organizerId,
            $slug,
            $secretHash,
            $title,
            trim($input['description'] ?? '') ?: null,
            trim($input['location'] ?? '') ?: null,
            $timezone,
            $durationMinutes
        );
        AuditLog::log('poll.create', 'poll', (string) $poll->id, ['slug' => $slug], $organizerId, $ip);
        return ['poll' => $poll, 'secret' => $secret];
    }

    public function addTimeOptions(int $pollId, array $options): array
    {
        $existing = $this->pollRepo->getOptions($pollId);
        $sortOrder = count($existing);
        foreach ($options as $opt) {
            $start = $opt['start_utc'] ?? null;
            $end = $opt['end_utc'] ?? null;
            if ($start && $end) {
                $this->pollRepo->addOption($pollId, $start, $end, $opt['label'] ?? null, $sortOrder++);
            }
        }
        return [];
    }

    /** One slot per matching weekday in range: start time + poll duration. */
    public function generateTimeOptions(string $timezone, string $dateFrom, string $dateTo, array $daysOfWeek, string $startTime, int $durationMinutes): array
    {
        $tz = new \DateTimeZone($timezone);
        $from = new \DateTimeImmutable($dateFrom . ' ' . $startTime, $tz);
        $to = new \DateTimeImmutable($dateTo . ' ' . $startTime, $tz);
        $duration = new \DateInterval('PT' . $durationMinutes . 'M');
        $slots = [];
        $current = $from;
        while ($current <= $to) {
            $dow = (int) $current->format('w'); // 0=Sun, 6=Sat
            if (in_array($dow, $daysOfWeek, true)) {
                $endSlot = $current->add($duration);
                $slots[] = [
                    'start_utc' => $current->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
                    'end_utc' => $endSlot->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
                    'label' => null,
                ];
            }
            $current = $current->modify('+1 day')->setTime((int) substr($startTime, 0, 2), (int) substr($startTime, 3, 2));
        }
        return $slots;
    }

    public function vote(int $pollId, int $optionId, int $userId, string $vote, string $ip): ?string
    {
        if (!in_array($vote, ['yes', 'maybe', 'no'], true)) {
            return 'Invalid vote.';
        }
        $poll = $this->pollRepo->findById($pollId);
        if ($poll === null || $poll->isLocked()) {
            return 'Poll not found or locked.';
        }
        if (!RateLimit::check('vote:' . $userId . ':' . $pollId, (int) config('rate.vote'))) {
            return 'Too many vote changes. Wait a moment.';
        }
        $options = $this->pollRepo->getOptions($pollId);
        $optionIds = array_column($options, 'id');
        if (!in_array($optionId, $optionIds, true)) {
            return 'Invalid option.';
        }
        $this->participantRepo->add($pollId, $userId);
        $this->voteRepo->setVote($pollId, $optionId, $userId, $vote);
        return null;
    }

    /**
     * Replace all votes for this user in this poll with the submitted set.
     * Empty or invalid values clear the vote for that option.
     * Returns null on success or an error message.
     * @param array<int|string, string> $votes option_id => 'yes'|'maybe'|'no'|''
     */
    public function voteBatch(int $pollId, int $userId, array $votes, string $ip): ?string
    {
        $poll = $this->pollRepo->findById($pollId);
        if ($poll === null || $poll->isLocked()) {
            return 'Poll not found or locked.';
        }
        if (!RateLimit::check('vote:' . $userId . ':' . $pollId, (int) config('rate.vote'))) {
            return 'Too many vote changes. Wait a moment.';
        }
        $options = $this->pollRepo->getOptions($pollId);
        $optionIds = array_column($options, 'id');
        foreach (array_keys($votes) as $k) {
            $oid = (int) $k;
            if ($oid > 0 && !in_array($oid, $optionIds, true)) {
                return 'STALE_OPTIONS:One or more time options are no longer available. Please refresh and try again.';
            }
        }
        $this->participantRepo->add($pollId, $userId);
        foreach ($optionIds as $optionId) {
            $vote = isset($votes[$optionId]) ? trim((string) $votes[$optionId]) : '';
            if (in_array($vote, ['yes', 'maybe', 'no'], true)) {
                $this->voteRepo->setVote($pollId, $optionId, $userId, $vote);
            } else {
                $this->voteRepo->removeVote($pollId, $optionId, $userId);
            }
        }
        return null;
    }

    public function lockPoll(int $pollId, int $optionId, int $organizerId): ?string
    {
        $poll = $this->pollRepo->findById($pollId);
        if ($poll === null || !$poll->isOrganizer($organizerId) || $poll->isLocked()) {
            return 'Not allowed or already locked.';
        }
        $options = $this->pollRepo->getOptions($pollId);
        if (!in_array($optionId, array_column($options, 'id'), true)) {
            return 'Invalid option.';
        }
        $this->pollRepo->lockPoll($pollId, $optionId);
        AuditLog::log('poll.lock', 'poll', (string) $pollId, ['option_id' => $optionId], $organizerId);
        return null;
    }

    /**
     * After a poll is locked: email all participants and invitees with final time (and timezone callout),
     * attach .ics when organizer has email; create Google Calendar event for current user when connected.
     */
    public function afterLockNotifyAndCalendar(Poll $poll, PollOption $lockedOption, string $pollUrl, int $currentUserId): void
    {
        $organizerTz = $poll->timezone;
        $userRepo = new UserRepository();
        $organizer = $userRepo->findById($poll->organizer_id);
        $organizerName = $organizer ? ($organizer->name ?? $organizer->email ?? '') : '';
        $organizerEmail = $organizer ? $organizer->email : '';
        $icsContent = '';
        if ($organizerEmail !== '') {
            $icsContent = IcsGenerator::singleEvent($poll->title, $lockedOption->start_utc, $lockedOption->end_utc, $organizerEmail);
        }
        $emailsSent = [];
        $formatLockedTime = function (string $tzId) use ($lockedOption, $organizerTz): string {
            try {
                $tz = new \DateTimeZone($tzId);
            } catch (\Exception $e) {
                $tz = new \DateTimeZone($organizerTz);
            }
            return (new \DateTime($lockedOption->start_utc, new \DateTimeZone('UTC')))->setTimezone($tz)->format('D M j, g:i A') . ' – ' . (new \DateTime($lockedOption->end_utc, new \DateTimeZone('UTC')))->setTimezone($tz)->format('g:i A');
        };
        $pickRecipientTz = function (?object $recipientUser) use ($organizerTz): array {
            $recipientTz = ($recipientUser !== null && isset($recipientUser->timezone) && $recipientUser->timezone !== null && $recipientUser->timezone !== '') ? $recipientUser->timezone : null;
            $tzId = $recipientTz ?? $organizerTz;
            try {
                new \DateTimeZone($tzId);
            } catch (\Exception $e) {
                $tzId = $organizerTz;
            }
            $isRecipientTz = $recipientTz !== null && $tzId === $recipientTz;
            return [$tzId, $isRecipientTz];
        };
        foreach ($this->participantRepo->getResultsParticipants($poll->id) as $p) {
            $email = isset($p->email) ? trim((string) $p->email) : '';
            if ($email !== '' && !isset($emailsSent[$email])) {
                $emailsSent[$email] = true;
                $recipientUser = $userRepo->findByEmail($email);
                [$tzId, $isRecipientTz] = $pickRecipientTz($recipientUser);
                $finalTimeLocalized = $formatLockedTime($tzId);
                $timezoneCallout = $isRecipientTz ? 'Times in your timezone (' . $tzId . ').' : 'Times in organizer\'s timezone (' . $organizerTz . ').';
                $this->emailService->sendPollLocked($email, $poll->title, $finalTimeLocalized, $timezoneCallout, $organizerName, $organizerEmail, $pollUrl, $icsContent);
            }
        }
        foreach ($this->inviteRepo->listInvites($poll->id) as $inv) {
            $email = strtolower(trim((string) $inv->email));
            if ($email !== '' && !isset($emailsSent[$email])) {
                $emailsSent[$email] = true;
                $recipientUser = $userRepo->findByEmail($email);
                [$tzId, $isRecipientTz] = $pickRecipientTz($recipientUser);
                $finalTimeLocalized = $formatLockedTime($tzId);
                $timezoneCallout = $isRecipientTz ? 'Times in your timezone (' . $tzId . ').' : 'Times in organizer\'s timezone (' . $organizerTz . ').';
                $this->emailService->sendPollLocked($email, $poll->title, $finalTimeLocalized, $timezoneCallout, $organizerName, $organizerEmail, $pollUrl, $icsContent);
            }
        }
        $calendarService = new GoogleCalendarService(
            new OAuthConnectionRepository(),
            new GoogleCalendarSelectionRepository(),
            new FreebusyCacheRepository()
        );
        $eventRepo = new CalendarEventRepository();
        $hasCalendar = $calendarService->getAuthUrl('x') !== '' && (new OAuthConnectionRepository())->hasConnection($currentUserId);
        if ($hasCalendar && !$eventRepo->existsForPollAndOption($poll->id, $lockedOption->id)) {
            $attendeeEmails = array_keys($emailsSent);
            $result = $calendarService->createEvent(
                $currentUserId,
                'primary',
                $poll->title,
                $poll->description ?? '',
                $poll->location ?? '',
                $lockedOption->start_utc,
                $lockedOption->end_utc,
                $attendeeEmails
            );
            $eventId = $result['event_id'] ?? null;
            if ($eventId !== null) {
                $eventRepo->create($poll->id, $lockedOption->id, $currentUserId, 'primary', $eventId);
            }
        }
    }

    /**
     * Send invitations only to emails not already invited (normalize: trim + lowercase).
     * Returns null on success or an error message.
     */
    public function sendInvites(int $pollId, array $emails, int $organizerId, string $pollUrl, string $ip): ?string
    {
        $poll = $this->pollRepo->findById($pollId);
        if ($poll === null || !$poll->isOrganizer($organizerId)) {
            return 'Poll not found.';
        }
        if (!RateLimit::check('invite:' . $ip, (int) config('rate.invite'))) {
            return 'Too many invite sends. Wait a minute.';
        }
        $valid = [];
        foreach ($emails as $email) {
            $email = strtolower(trim($email));
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
                $valid[] = $email;
            }
        }
        $alreadyInvited = $this->inviteRepo->getInvitedEmails($pollId);
        $toSend = array_values(array_diff($valid, $alreadyInvited));
        foreach ($toSend as $email) {
            $rawToken = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $rawToken);
            $id = $this->inviteRepo->createInvite($pollId, $email, $tokenHash, $organizerId);
            $inviteUrl = \Hillmeet\Support\url('/poll/' . $poll->slug, ['invite' => $rawToken]);
            $this->emailService->sendPollInvite($email, $poll->title, $inviteUrl);
            $this->inviteRepo->markSent($id);
        }
        return null;
    }

    /** Resend one invitation by invite id. Returns null on success or error message. */
    public function resendInvite(int $pollId, int $inviteId, int $organizerId, string $ip): ?string
    {
        $poll = $this->pollRepo->findById($pollId);
        if ($poll === null || !$poll->isOrganizer($organizerId)) {
            return 'Poll not found.';
        }
        if (!RateLimit::check('invite:' . $ip, (int) config('rate.invite'))) {
            return 'Too many invite sends. Wait a minute.';
        }
        $invite = $this->inviteRepo->getByIdAndPoll($inviteId, $pollId);
        if ($invite === null) {
            return 'Invite not found.';
        }
        $email = $invite->email;
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $this->inviteRepo->createInvite($pollId, $email, $tokenHash, $organizerId);
        $inviteUrl = \Hillmeet\Support\url('/poll/' . $poll->slug, ['invite' => $rawToken]);
        $this->emailService->sendPollInvite($email, $poll->title, $inviteUrl);
        $this->inviteRepo->markSent($inviteId);
        return null;
    }

    /** Remove one invitation by invite id. Returns null on success or error message. */
    public function removeInvite(int $pollId, int $inviteId, int $organizerId): ?string
    {
        $poll = $this->pollRepo->findById($pollId);
        if ($poll === null || !$poll->isOrganizer($organizerId)) {
            return 'Poll not found.';
        }
        $invite = $this->inviteRepo->getByIdAndPoll($inviteId, $pollId);
        if ($invite === null) {
            return 'Invite not found.';
        }
        $this->inviteRepo->deleteInvite($inviteId, $pollId);
        return null;
    }

    /** @return array{totals: array, matrix: array, best_option_id: int|null, options: PollOption[]} */
    public function getResults(Poll $poll): array
    {
        $options = $this->pollRepo->getOptions($poll->id);
        $totals = $this->voteRepo->getTotalsByPoll($poll->id);
        $matrix = $this->voteRepo->getMatrix($poll->id);
        $bestOptionId = $this->voteRepo->getBestOptionId($poll->id, $options);
        return [
            'totals' => $totals,
            'matrix' => $matrix,
            'best_option_id' => $bestOptionId,
            'options' => $options,
        ];
    }

    /** Delete poll and all related data. Returns null on success or error message. */
    public function deletePoll(int $pollId, int $userId): ?string
    {
        $poll = $this->pollRepo->findById($pollId);
        if ($poll === null) {
            return 'Poll not found.';
        }
        if (!$poll->isOrganizer($userId)) {
            return 'Not allowed.';
        }
        $this->pollRepo->deletePoll($pollId);
        AuditLog::log('poll.delete', 'poll', (string) $pollId, ['slug' => $poll->slug], $userId);
        return null;
    }

    /** Delete one time option and its votes. Returns null on success or error message. */
    public function deleteOption(int $pollId, int $optionId, int $userId): ?string
    {
        $poll = $this->pollRepo->findById($pollId);
        if ($poll === null) {
            return 'Poll not found.';
        }
        if (!$poll->isOrganizer($userId)) {
            return 'Not allowed.';
        }
        $options = $this->pollRepo->getOptions($pollId);
        $validIds = array_column($options, 'id');
        if (!in_array($optionId, $validIds, true)) {
            return 'Time option not found.';
        }
        if ($poll->isLocked() && $poll->locked_option_id === $optionId) {
            return 'Cannot delete the locked option.';
        }
        $this->pollRepo->deleteOption($pollId, $optionId);
        return null;
    }
}
