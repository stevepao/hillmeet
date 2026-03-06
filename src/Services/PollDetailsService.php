<?php

declare(strict_types=1);

namespace Hillmeet\Services;

use Hillmeet\Exception\HillmeetNotFound;
use Hillmeet\Repositories\PollInviteRepository;
use Hillmeet\Repositories\PollRepository;
use Hillmeet\Repositories\UserRepository;

/**
 * Core business logic: full poll details for an owner. Does not know about MCP or adapters.
 * Resolves poll by slug; enforces ownership; returns options in UTC and participants with normalized emails.
 */
final class PollDetailsService
{
    public function __construct(
        private readonly PollRepository $pollRepository,
        private readonly PollInviteRepository $pollInviteRepository,
        private readonly UserRepository $userRepository,
    ) {
    }

    /**
     * Get poll details for a poll owned by the given user. Throws HillmeetNotFound if not found or not owned.
     *
     * @param int    $userId  Organizer user id (ownership check).
     * @param string $pollId  Poll slug (or identifier that resolves to a poll).
     */
    public function getPollDetailsForOwner(int $userId, string $pollId): PollDetailsData
    {
        $pollId = trim($pollId);
        if ($pollId === '') {
            throw new HillmeetNotFound('Poll not found.');
        }
        $poll = $this->pollRepository->findBySlug($pollId);
        if ($poll === null || $poll->organizer_id !== $userId) {
            throw new HillmeetNotFound('Poll not found or access denied.');
        }
        $options = $this->pollRepository->getOptions($poll->id);
        $optionDtos = [];
        $utc = new \DateTimeZone('UTC');
        foreach ($options as $opt) {
            $optionDtos[] = [
                'start_utc' => new \DateTimeImmutable($opt->start_utc, $utc),
                'end_utc' => new \DateTimeImmutable($opt->end_utc, $utc),
            ];
        }
        $invites = $this->pollInviteRepository->listInvites($poll->id);
        $participants = [];
        foreach ($invites as $inv) {
            $email = isset($inv->email) ? strtolower(trim((string) $inv->email)) : '';
            if ($email === '') {
                continue;
            }
            $user = $this->userRepository->findByEmail($email);
            $name = $user !== null && $user->name !== '' ? trim($user->name) : null;
            $participants[] = $name !== null ? ['email' => $email, 'name' => $name] : ['email' => $email];
        }
        usort($participants, static fn(array $a, array $b): int => strcasecmp($a['email'], $b['email']));
        $status = $poll->isLocked() ? 'closed' : 'open';
        return new PollDetailsData(
            $poll->slug,
            $poll->title,
            $poll->description,
            $poll->location,
            $poll->timezone,
            $status,
            $poll->created_at,
            $optionDtos,
            $participants,
        );
    }
}
