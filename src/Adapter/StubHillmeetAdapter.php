<?php

declare(strict_types=1);

namespace Hillmeet\Adapter;

use Hillmeet\Dto\HillmeetAvailabilityResult;
use Hillmeet\Dto\HillmeetCloseResult;
use Hillmeet\Dto\HillmeetNonrespondersResult;
use Hillmeet\Dto\HillmeetPollDetails;
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
}
