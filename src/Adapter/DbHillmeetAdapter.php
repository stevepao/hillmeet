<?php

declare(strict_types=1);

namespace Hillmeet\Adapter;

use Hillmeet\Dto\HillmeetAvailabilityResult;
use Hillmeet\Dto\HillmeetCloseResult;
use Hillmeet\Dto\HillmeetNonrespondersResult;
use Hillmeet\Dto\HillmeetPollDetails;
use Hillmeet\Dto\HillmeetPollListResult;
use Hillmeet\Dto\HillmeetPollResult;
use Hillmeet\Exception\HillmeetConflict;
use Hillmeet\Exception\HillmeetNotFound;
use Hillmeet\Exception\HillmeetValidationError;
use Hillmeet\HillmeetAdapter as HillmeetAdapterInterface;
use Hillmeet\Repositories\CalendarEventRepository;
use Hillmeet\Repositories\PollInviteRepository;
use Hillmeet\Repositories\PollRepository;
use Hillmeet\Repositories\UserRepository;
use Hillmeet\Services\AvailabilityService;
use Hillmeet\Services\EmailService;
use Hillmeet\Services\NonresponderService;
use Hillmeet\Services\PollDetailsService;
use Hillmeet\Services\PollService;
use Hillmeet\Support\Database;

/**
 * DB-backed implementation of HillmeetAdapter.
 * Resolves ownerEmail to user_id, creates polls, options, and invites; supports idempotency.
 */
final class DbHillmeetAdapter implements HillmeetAdapterInterface
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly PollRepository $pollRepository,
        private readonly PollInviteRepository $pollInviteRepository,
        private readonly EmailService $emailService,
        private readonly AvailabilityService $availabilityService,
        private readonly NonresponderService $nonresponderService,
        private readonly PollDetailsService $pollDetailsService,
        private readonly string $baseUrl = 'https://meet.hillwork.net',
        private readonly ?PollService $pollService = null,
        private readonly ?CalendarEventRepository $calendarEventRepository = null,
    ) {
    }

    /**
     * Accept either a short slug (e.g. "8tjrm6pnq43p") or a full poll URL;
     * returns the slug for lookup.
     */
    private function normalizePollId(string $pollId): string
    {
        $pollId = trim($pollId);
        if ($pollId === '') {
            return $pollId;
        }
        if (str_contains($pollId, '://')) {
            $path = parse_url($pollId, PHP_URL_PATH);
            if ($path !== null && $path !== '') {
                $segments = array_filter(explode('/', $path));
                $last = end($segments);
                if ($last !== false && $last !== '') {
                    return $last;
                }
            }
        }
        return $pollId;
    }

    public function createPoll(string $ownerEmail, array $payload): HillmeetPollResult
    {
        $ownerEmail = UserRepository::normalizeEmail($ownerEmail);
        $userId = $this->userRepository->getOrCreateUserIdByEmail($ownerEmail);

        $tenantId = $payload['_tenant_id'] ?? null;
        $idempotencyKey = isset($payload['idempotency_key']) && \is_string($payload['idempotency_key'])
            ? trim($payload['idempotency_key'])
            : null;
        if ($idempotencyKey === '') {
            $idempotencyKey = null;
        }

        if ($tenantId !== null && $idempotencyKey !== null) {
            $existingPollId = $this->findIdempotentPollId((string) $tenantId, $idempotencyKey);
            if ($existingPollId !== null) {
                $poll = $this->pollRepository->findById($existingPollId);
                if ($poll !== null) {
                    $optionsCount = \count($this->pollRepository->getOptions($poll->id));
                    $invites = $this->pollInviteRepository->listInvites($poll->id);
                    return $this->buildResult($poll, $optionsCount, \count($invites));
                }
            }
        }

        $title = isset($payload['title']) && \is_string($payload['title']) ? trim($payload['title']) : 'Untitled';
        $description = isset($payload['description']) && \is_string($payload['description']) ? trim($payload['description']) : null;
        if ($description === '') {
            $description = null;
        }
        $timezone = $this->resolvePollTimezone($payload, $userId);
        $durationMinutes = isset($payload['duration_minutes']) ? (int) $payload['duration_minutes'] : 60;
        if ($durationMinutes < 1) {
            $durationMinutes = 60;
        }

        $slug = $this->pollRepository->generateSlug();
        $secret = bin2hex(random_bytes(16));
        $secretHash = password_hash($secret, PASSWORD_DEFAULT);

        $poll = $this->pollRepository->create(
            $userId,
            $slug,
            $secretHash,
            $title,
            $description,
            null,
            $timezone,
            $durationMinutes,
        );

        $options = $payload['options'] ?? [];
        $sortOrder = 0;
        foreach ($options as $opt) {
            if (!\is_array($opt) || !isset($opt['start'], $opt['end']) || !\is_string($opt['start']) || !\is_string($opt['end'])) {
                continue;
            }
            $startUtc = $this->parseUtcDatetime($opt['start']);
            $endUtc = $this->parseUtcDatetime($opt['end']);
            if ($startUtc === null || $endUtc === null) {
                continue;
            }
            $this->pollRepository->addOption(
                $poll->id,
                $startUtc->format('Y-m-d H:i:s'),
                $endUtc->format('Y-m-d H:i:s'),
                null,
                $sortOrder++,
            );
        }
        $optionsAdded = $sortOrder;

        $participants = $payload['participants'] ?? [];
        $seenEmails = [];
        foreach ($participants as $p) {
            if (!\is_array($p) || !isset($p['email']) || !\is_string($p['email'])) {
                continue;
            }
            $email = UserRepository::normalizeEmail($p['email']);
            if ($email === '' || !$this->isValidEmail($email)) {
                continue;
            }
            if (isset($seenEmails[$email])) {
                continue;
            }
            $seenEmails[$email] = true;
            $rawToken = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $rawToken);
            $inviteId = $this->pollInviteRepository->createInvite($poll->id, $email, $tokenHash, $userId);
            $inviteUrl = rtrim($this->baseUrl, '/') . '/poll/' . $poll->slug . '?invite=' . $rawToken;
            if ($this->emailService->sendPollInvite($email, $poll->title, $inviteUrl)) {
                $this->pollInviteRepository->markSent($inviteId);
            }
        }

        if ($tenantId !== null && $idempotencyKey !== null) {
            $this->storeIdempotency((string) $tenantId, $idempotencyKey, $poll->id);
        }

        return $this->buildResult($poll, $optionsAdded, \count($seenEmails));
    }

    public function findAvailability(string $ownerEmail, string $pollId, array $constraints): HillmeetAvailabilityResult
    {
        $pollId = $this->normalizePollId($pollId);
        $ownerEmail = UserRepository::normalizeEmail($ownerEmail);
        $user = $this->userRepository->findByEmail($ownerEmail);
        if ($user === null) {
            return new HillmeetAvailabilityResult([], 'Owner not found.', rtrim($this->baseUrl, '/') . '/poll/' . $pollId);
        }
        $poll = $this->pollRepository->findBySlug($pollId);
        if ($poll === null || $poll->organizer_id !== $user->id) {
            return new HillmeetAvailabilityResult([], 'Poll not found or access denied.', rtrim($this->baseUrl, '/') . '/poll/' . $pollId);
        }
        $normalized = $this->normalizeAvailabilityConstraints($constraints);
        $slots = $this->availabilityService->computeBestSlots($user->id, $poll->id, $normalized);
        $shareUrl = rtrim($this->baseUrl, '/') . '/poll/' . $poll->slug;
        $bestSlots = [];
        foreach ($slots as $s) {
            $bestSlots[] = [
                'start' => $this->formatInPollTimezone($s['start_utc'], $poll->timezone),
                'end' => $this->formatInPollTimezone($s['end_utc'], $poll->timezone),
                'available_count' => $s['available_count'],
                'total_invited' => $s['total_invited'],
                'available_emails' => $s['available_emails'],
                'unavailable_emails' => $s['unavailable_emails'],
            ];
        }
        $summary = $this->buildAvailabilitySummary($bestSlots, $poll->title);
        return new HillmeetAvailabilityResult($bestSlots, $summary, $shareUrl);
    }

    /**
     * Format a UTC DateTimeImmutable in the poll's timezone as ISO8601 (with offset).
     * Falls back to UTC if the poll timezone is invalid.
     */
    private function formatInPollTimezone(\DateTimeImmutable $utc, string $pollTimezone): string
    {
        $tz = 'UTC';
        if ($pollTimezone !== '') {
            try {
                new \DateTimeZone($pollTimezone);
                $tz = $pollTimezone;
            } catch (\Exception) {
                // fallback to UTC
            }
        }
        return $utc->setTimezone(new \DateTimeZone($tz))->format('c');
    }

    /**
     * @param list<array{start: string, end: string, available_count: int, total_invited: int, available_emails: list<string>, unavailable_emails: list<string>}> $bestSlots
     */
    private function buildAvailabilitySummary(array $bestSlots, string $pollTitle): string
    {
        if ($bestSlots === []) {
            return sprintf('No time slots meet the criteria for "%s".', $pollTitle);
        }
        $first = $bestSlots[0];
        $line = sprintf(
            'Best slot: %s–%s (%d of %d available).',
            $first['start'],
            $first['end'],
            $first['available_count'],
            $first['total_invited'],
        );
        if (isset($bestSlots[1])) {
            $second = $bestSlots[1];
            $line .= sprintf(' Runner-up: %s–%s (%d of %d).', $second['start'], $second['end'], $second['available_count'], $second['total_invited']);
        }
        return $line;
    }

    /** @return array{min_attendees?: int, prefer_times?: list<array{start: string, end: string}>, exclude_emails?: list<string>} */
    private function normalizeAvailabilityConstraints(array $constraints): array
    {
        $out = [];
        if (isset($constraints['min_attendees']) && (is_int($constraints['min_attendees']) || (is_numeric($constraints['min_attendees']) && (int) $constraints['min_attendees'] == $constraints['min_attendees']))) {
            $out['min_attendees'] = max(0, (int) $constraints['min_attendees']);
        }
        if (isset($constraints['prefer_times']) && \is_array($constraints['prefer_times'])) {
            $windows = [];
            foreach ($constraints['prefer_times'] as $w) {
                if (\is_array($w) && isset($w['start'], $w['end']) && \is_string($w['start']) && \is_string($w['end'])) {
                    $windows[] = ['start' => trim($w['start']), 'end' => trim($w['end'])];
                }
            }
            if ($windows !== []) {
                $out['prefer_times'] = $windows;
            }
        }
        if (isset($constraints['exclude_emails']) && \is_array($constraints['exclude_emails'])) {
            $emails = [];
            foreach ($constraints['exclude_emails'] as $e) {
                if (\is_string($e) && $e !== '') {
                    $emails[] = strtolower(trim($e));
                }
            }
            if ($emails !== []) {
                $out['exclude_emails'] = $emails;
            }
        }
        return $out;
    }

    public function listNonresponders(string $ownerEmail, string $pollId): HillmeetNonrespondersResult
    {
        $pollId = $this->normalizePollId($pollId);
        $ownerEmail = UserRepository::normalizeEmail($ownerEmail);
        $user = $this->userRepository->findByEmail($ownerEmail);
        if ($user === null) {
            throw new HillmeetNotFound('Owner not found.');
        }
        $poll = $this->pollRepository->findBySlug($pollId);
        if ($poll === null || $poll->organizer_id !== $user->id) {
            throw new HillmeetNotFound('Poll not found or access denied.');
        }
        $list = $this->nonresponderService->findNonrespondersForPoll($user->id, $poll->id);
        $summary = $this->buildNonrespondersSummary($list);
        return new HillmeetNonrespondersResult($list, $summary);
    }

    /**
     * @param list<array{email: string, name?: string}> $nonresponders
     */
    private function buildNonrespondersSummary(array $nonresponders): string
    {
        $n = \count($nonresponders);
        if ($n === 0) {
            return 'Everyone has responded.';
        }
        $emails = array_column($nonresponders, 'email');
        return sprintf('%d person(s) haven\'t responded yet: %s.', $n, implode(', ', $emails));
    }

    public function listPolls(string $ownerEmail): HillmeetPollListResult
    {
        $ownerEmail = UserRepository::normalizeEmail($ownerEmail);
        $user = $this->userRepository->findByEmail($ownerEmail);
        if ($user === null) {
            return new HillmeetPollListResult([], 'Owner not found.');
        }
        $rows = $this->pollRepository->findPollsOwnedByUser($user->id);
        $base = rtrim($this->baseUrl, '/');
        $polls = [];
        foreach ($rows as $row) {
            $row['share_url'] = $base . '/poll/' . $row['poll_id'];
            $polls[] = $row;
        }
        $summary = $this->buildListPollsSummary($polls);
        return new HillmeetPollListResult($polls, $summary);
    }

    /**
     * @param list<array{poll_id: string, title: string, created_at: string, timezone: string, status: string, share_url: string}> $polls
     */
    private function buildListPollsSummary(array $polls): string
    {
        $n = \count($polls);
        if ($n === 0) {
            return 'You have no polls.';
        }
        $mostRecent = $polls[0]['title'] ?? '';
        $word = $n === 1 ? 'poll' : 'polls';
        if ($mostRecent !== '') {
            return sprintf("You have %d %s. Most recent: '%s'.", $n, $word, $mostRecent);
        }
        return sprintf('You have %d %s.', $n, $word);
    }


    public function closePoll(string $ownerEmail, string $pollId, ?array $finalSlot, bool $notify): HillmeetCloseResult
    {
        $pollId = $this->normalizePollId($pollId);
        $ownerEmail = UserRepository::normalizeEmail($ownerEmail);
        $user = $this->userRepository->findByEmail($ownerEmail);
        if ($user === null) {
            throw new HillmeetNotFound('Owner not found.');
        }
        $poll = $this->pollRepository->findBySlug($pollId);
        if ($poll === null || $poll->organizer_id !== $user->id) {
            throw new HillmeetNotFound('Poll not found or access denied.');
        }

        $options = $this->pollRepository->getOptions($poll->id);
        $lockedOptionId = $poll->locked_option_id;
        $alreadyLocked = $poll->isLocked();

        if ($alreadyLocked) {
            $lockedOption = null;
            foreach ($options as $o) {
                if ($o->id === $lockedOptionId) {
                    $lockedOption = $o;
                    break;
                }
            }
            if ($finalSlot === null || $finalSlot === []) {
                $summary = $this->buildCloseSummary($poll, $lockedOption);
                $finalSlotFormatted = $lockedOption !== null
                    ? ['start' => $this->formatInPollTimezone(new \DateTimeImmutable($lockedOption->start_utc, new \DateTimeZone('UTC')), $poll->timezone), 'end' => $this->formatInPollTimezone(new \DateTimeImmutable($lockedOption->end_utc, new \DateTimeZone('UTC')), $poll->timezone)]
                    : null;
                return new HillmeetCloseResult(true, $finalSlotFormatted, $summary);
            }
            $matched = $this->findOptionByFinalSlot($options, $finalSlot);
            if ($matched !== null && $matched->id === $lockedOptionId) {
                $summary = $this->buildCloseSummary($poll, $matched);
                $finalSlotFormatted = ['start' => $this->formatInPollTimezone(new \DateTimeImmutable($matched->start_utc, new \DateTimeZone('UTC')), $poll->timezone), 'end' => $this->formatInPollTimezone(new \DateTimeImmutable($matched->end_utc, new \DateTimeZone('UTC')), $poll->timezone)];
                return new HillmeetCloseResult(true, $finalSlotFormatted, $summary);
            }
            throw new HillmeetConflict('Poll is already closed with a different final time.');
        }

        if ($finalSlot === null || $finalSlot === []) {
            $this->pollRepository->setPollLocked($poll->id, null);
            return new HillmeetCloseResult(true, null, 'Poll closed with no final time selected.');
        }

        $matched = $this->findOptionByFinalSlot($options, $finalSlot);
        if ($matched === null) {
            throw new HillmeetValidationError('No poll option matches the given final_slot start/end.');
        }

        $this->pollRepository->setPollLocked($poll->id, $matched->id);
        $finalSlotFormatted = ['start' => $this->formatInPollTimezone(new \DateTimeImmutable($matched->start_utc, new \DateTimeZone('UTC')), $poll->timezone), 'end' => $this->formatInPollTimezone(new \DateTimeImmutable($matched->end_utc, new \DateTimeZone('UTC')), $poll->timezone)];
        $summary = $this->buildCloseSummary($poll, $matched);
        $notified = null;
        $calendarEventCreated = null;
        if ($notify && $this->pollService !== null) {
            $pollUrl = rtrim($this->baseUrl, '/') . '/poll/' . $poll->slug;
            $result = $this->pollService->afterLockNotifyAndCalendar($poll, $matched, $pollUrl, $user->id);
            $notified = $result['notified'];
            if ($this->calendarEventRepository !== null) {
                $calendarEventCreated = $this->calendarEventRepository->existsForPollAndOption($poll->id, $matched->id);
            }
            if ($notified) {
                $summary .= ' Participants notified by email.';
                if ($calendarEventCreated) {
                    $summary .= ' Google Calendar event created.';
                }
            } else {
                $summary .= ' Notification emails were not sent (e.g. calendar creation failed or not configured).';
            }
        }
        return new HillmeetCloseResult(true, $finalSlotFormatted, $summary, $notified, $calendarEventCreated);
    }

    /**
     * @param list<\Hillmeet\Models\PollOption> $options
     * @param array{start: string, end: string} $finalSlot
     */
    private function findOptionByFinalSlot(array $options, array $finalSlot): ?\Hillmeet\Models\PollOption
    {
        $startStr = isset($finalSlot['start']) && \is_string($finalSlot['start']) ? trim($finalSlot['start']) : '';
        $endStr = isset($finalSlot['end']) && \is_string($finalSlot['end']) ? trim($finalSlot['end']) : '';
        if ($startStr === '' || $endStr === '') {
            return null;
        }
        try {
            $startUtc = (new \DateTimeImmutable($startStr, new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
            $endUtc = (new \DateTimeImmutable($endStr, new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
        foreach ($options as $opt) {
            if ($opt->start_utc === $startUtc && $opt->end_utc === $endUtc) {
                return $opt;
            }
        }
        return null;
    }

    private function buildCloseSummary(\Hillmeet\Models\Poll $poll, ?\Hillmeet\Models\PollOption $lockedOption): string
    {
        if ($lockedOption === null) {
            return 'Poll closed with no final time selected.';
        }
        $startFormatted = $this->formatInPollTimezone(new \DateTimeImmutable($lockedOption->start_utc, new \DateTimeZone('UTC')), $poll->timezone);
        $endFormatted = $this->formatInPollTimezone(new \DateTimeImmutable($lockedOption->end_utc, new \DateTimeZone('UTC')), $poll->timezone);
        return sprintf('Poll closed. Final time selected: %s – %s.', $startFormatted, $endFormatted);
    }

    public function getPoll(string $ownerEmail, string $pollId): HillmeetPollDetails
    {
        $pollId = $this->normalizePollId($pollId);
        $ownerEmail = UserRepository::normalizeEmail($ownerEmail);
        $user = $this->userRepository->findByEmail($ownerEmail);
        if ($user === null) {
            throw new HillmeetNotFound('Owner not found.');
        }
        $data = $this->pollDetailsService->getPollDetailsForOwner($user->id, $pollId);
        $options = [];
        foreach ($data->options as $opt) {
            $options[] = [
                'start' => $this->formatInPollTimezone($opt['start_utc'], $data->timezone),
                'end' => $this->formatInPollTimezone($opt['end_utc'], $data->timezone),
            ];
        }
        $createdAtIso = (new \DateTimeImmutable($data->created_at, new \DateTimeZone('UTC')))->format('c');
        return new HillmeetPollDetails(
            $data->pollId,
            $data->title,
            $data->timezone,
            $createdAtIso,
            $options,
            $data->participants,
            $data->status === 'closed',
        );
    }

    private function parseUtcDatetime(string $value): ?\DateTimeImmutable
    {
        try {
            $dt = new \DateTimeImmutable($value, new \DateTimeZone('UTC'));
            return $dt;
        } catch (\Exception) {
            return null;
        }
    }

    private function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Resolve poll timezone: explicit payload value, else organizer's saved timezone, else UTC.
     */
    private function resolvePollTimezone(array $payload, int $organizerUserId): string
    {
        $explicit = isset($payload['timezone']) && \is_string($payload['timezone']) ? trim($payload['timezone']) : '';
        if ($explicit !== '') {
            return $explicit;
        }
        $user = $this->userRepository->findById($organizerUserId);
        if ($user !== null && $user->timezone !== null && $user->timezone !== '') {
            return $user->timezone;
        }
        return 'UTC';
    }

    private function findIdempotentPollId(string $tenantId, string $idempotencyKey): ?int
    {
        $stmt = Database::get()->prepare(
            "SELECT poll_id FROM tenant_poll_idempotency WHERE tenant_id = ? AND idempotency_key = ? LIMIT 1"
        );
        $stmt->execute([$tenantId, $idempotencyKey]);
        $row = $stmt->fetchColumn();
        return $row !== false ? (int) $row : null;
    }

    private function storeIdempotency(string $tenantId, string $idempotencyKey, int $pollId): void
    {
        $stmt = Database::get()->prepare(
            "INSERT IGNORE INTO tenant_poll_idempotency (tenant_id, idempotency_key, poll_id) VALUES (?, ?, ?)"
        );
        $stmt->execute([$tenantId, $idempotencyKey, $pollId]);
    }

    private function buildResult(\Hillmeet\Models\Poll $poll, ?int $optionsCount = null, ?int $participantsCount = null): HillmeetPollResult
    {
        $shareUrl = rtrim($this->baseUrl, '/') . '/poll/' . $poll->slug;
        $nOpt = $optionsCount ?? 0;
        $nPart = $participantsCount ?? 0;
        $summary = sprintf(
            'Created poll "%s" with %d option(s) and %d participant(s).',
            $poll->title,
            $nOpt,
            $nPart,
        );
        return new HillmeetPollResult(
            $poll->slug,
            $shareUrl,
            $summary,
            $poll->timezone,
        );
    }
}
