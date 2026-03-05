<?php

declare(strict_types=1);

namespace Hillmeet\Adapter;

use Hillmeet\Dto\HillmeetAvailabilityResult;
use Hillmeet\Dto\HillmeetCloseResult;
use Hillmeet\Dto\HillmeetNonrespondersResult;
use Hillmeet\Dto\HillmeetPollDetails;
use Hillmeet\Dto\HillmeetPollResult;
use Hillmeet\HillmeetAdapter as HillmeetAdapterInterface;
use Hillmeet\Repositories\PollInviteRepository;
use Hillmeet\Repositories\PollRepository;
use Hillmeet\Repositories\UserRepository;
use Hillmeet\Services\EmailService;
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
        private readonly string $baseUrl = 'https://meet.hillwork.net',
    ) {
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
        throw new \BadMethodCallException('Not implemented');
    }

    public function listNonresponders(string $ownerEmail, string $pollId): HillmeetNonrespondersResult
    {
        throw new \BadMethodCallException('Not implemented');
    }

    public function closePoll(string $ownerEmail, string $pollId, ?array $finalSlot, bool $notify): HillmeetCloseResult
    {
        throw new \BadMethodCallException('Not implemented');
    }

    public function getPoll(string $ownerEmail, string $pollId): HillmeetPollDetails
    {
        throw new \BadMethodCallException('Not implemented');
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
