<?php

declare(strict_types=1);

namespace Hillmeet\Tests\Services;

use Hillmeet\Repositories\PollInviteRepository;
use Hillmeet\Repositories\PollParticipantRepository;
use Hillmeet\Repositories\PollRepository;
use Hillmeet\Repositories\UserRepository;
use Hillmeet\Repositories\VoteRepository;
use Hillmeet\Services\AvailabilityService;
use PHPUnit\Framework\TestCase;

/**
 * Integration-style tests for AvailabilityService::computeBestSlots.
 * Requires config and database; skips if config is not available.
 */
final class AvailabilityServiceTest extends TestCase
{
    private ?int $pollId = null;
    private ?int $organizerId = null;
    private UserRepository $userRepository;
    private PollRepository $pollRepository;
    private VoteRepository $voteRepository;
    private PollParticipantRepository $participantRepository;
    private PollInviteRepository $inviteRepository;
    private AvailabilityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $root = dirname(__DIR__, 2);
        if (!is_file($root . '/config/config.php')) {
            $this->markTestSkipped('config/config.php required');
        }
        require_once $root . '/config/env.php';
        require_once $root . '/vendor/autoload.php';
        $this->userRepository = new UserRepository();
        $this->pollRepository = new PollRepository();
        $this->voteRepository = new VoteRepository();
        $this->participantRepository = new PollParticipantRepository();
        $this->inviteRepository = new PollInviteRepository();
        $this->service = new AvailabilityService(
            $this->pollRepository,
            $this->voteRepository,
            $this->participantRepository,
            $this->inviteRepository,
        );
    }

    protected function tearDown(): void
    {
        if ($this->pollId !== null) {
            $this->pollRepository->deletePoll($this->pollId);
        }
        parent::tearDown();
    }

    public function testComputeBestSlotsWithoutConstraintsReturnsOptionsSortedByAvailability(): void
    {
        $this->organizerId = (int) $this->userRepository->getOrCreateUserIdByEmail('avail-org@example.com');
        $poll = $this->createPollWithOptionsAndVotes();
        $this->pollId = $poll->id;

        $slots = $this->service->computeBestSlots($this->organizerId, $poll->id, []);

        $this->assertCount(3, $slots);
        $scores = array_column($slots, 'score');
        $this->assertGreaterThanOrEqual($scores[1], $scores[0]);
        $this->assertGreaterThanOrEqual($scores[2], $scores[1]);
        foreach ($slots as $slot) {
            $this->assertArrayHasKey('option_id', $slot);
            $this->assertArrayHasKey('start_utc', $slot);
            $this->assertArrayHasKey('end_utc', $slot);
            $this->assertArrayHasKey('available_emails', $slot);
            $this->assertArrayHasKey('unavailable_emails', $slot);
            $this->assertArrayHasKey('available_count', $slot);
            $this->assertArrayHasKey('total_invited', $slot);
            $this->assertArrayHasKey('score', $slot);
        }
    }

    public function testComputeBestSlotsWithMinAttendeesDemotesOptionsBelowThreshold(): void
    {
        $this->organizerId = (int) $this->userRepository->getOrCreateUserIdByEmail('avail-min@example.com');
        $poll = $this->createPollWithOptionsAndVotes();
        $this->pollId = $poll->id;

        $slots = $this->service->computeBestSlots($this->organizerId, $poll->id, ['min_attendees' => 10]);

        $this->assertCount(3, $slots);
        foreach ($slots as $slot) {
            $this->assertLessThan(0, $slot['score'], 'Options with fewer than 10 available should have negative score');
        }
    }

    public function testComputeBestSlotsWithExcludeEmailsExcludesThoseFromCohort(): void
    {
        $this->organizerId = (int) $this->userRepository->getOrCreateUserIdByEmail('avail-excl@example.com');
        $poll = $this->createPollWithOptionsAndVotes();
        $this->pollId = $poll->id;

        $slotsNoExclude = $this->service->computeBestSlots($this->organizerId, $poll->id, []);
        $totalInvitedNoExclude = $slotsNoExclude[0]['total_invited'] ?? 0;

        $slotsExclude = $this->service->computeBestSlots($this->organizerId, $poll->id, [
            'exclude_emails' => ['alice-avail@example.com'],
        ]);
        $totalInvitedExclude = $slotsExclude[0]['total_invited'] ?? 0;

        $this->assertSame($totalInvitedNoExclude - 1, $totalInvitedExclude);
    }

    public function testComputeBestSlotsWithPreferTimesBoostsMatchingSlots(): void
    {
        $this->organizerId = (int) $this->userRepository->getOrCreateUserIdByEmail('avail-pref@example.com');
        $poll = $this->createPollWithOptionsAndVotes();
        $this->pollId = $poll->id;
        $options = $this->pollRepository->getOptions($poll->id);
        $this->assertCount(3, $options);
        $midStart = $options[1]->start_utc;
        $midEnd = $options[1]->end_utc;

        $slots = $this->service->computeBestSlots($this->organizerId, $poll->id, [
            'prefer_times' => [['start' => $midStart, 'end' => $midEnd]],
        ]);

        $this->assertCount(3, $slots);
        $topOptionId = $slots[0]['option_id'];
        $this->assertSame($options[1]->id, $topOptionId, 'Option inside prefer_times window should rank first');
    }

    public function testComputeBestSlotsWrongOrganizerReturnsEmpty(): void
    {
        $this->organizerId = (int) $this->userRepository->getOrCreateUserIdByEmail('avail-owner@example.com');
        $otherId = (int) $this->userRepository->getOrCreateUserIdByEmail('avail-other@example.com');
        $poll = $this->createPollWithOptionsAndVotes();
        $this->pollId = $poll->id;

        $slots = $this->service->computeBestSlots($otherId, $poll->id, []);

        $this->assertSame([], $slots);
    }

    /**
     * prefer_times window that no option overlaps must not boost any slot (scores stay = available_count).
     */
    public function testComputeBestSlotsWithPreferTimesOutsideAllOptionsDoesNotBoost(): void
    {
        $this->organizerId = (int) $this->userRepository->getOrCreateUserIdByEmail('avail-noboost@example.com');
        $poll = $this->createPollWithOptionsAndVotes();
        $this->pollId = $poll->id;

        $slots = $this->service->computeBestSlots($this->organizerId, $poll->id, [
            'prefer_times' => [
                ['start' => '2026-03-02T10:00:00Z', 'end' => '2026-03-02T11:00:00Z'],
            ],
        ]);

        $this->assertCount(3, $slots);
        foreach ($slots as $slot) {
            $this->assertLessThan(100.0, $slot['score'], 'No option overlaps the preferred window; score should be base only (available_count), not boosted');
            $this->assertSame($slot['available_count'], (int) $slot['score'], 'Score should equal available_count when no prefer_times overlap');
        }
    }

    /**
     * Poll with options but no participants and no invitees: cohort is empty, all slots have zero counts.
     */
    public function testComputeBestSlotsWithNoParticipantsReturnsSlotsWithZeroCohort(): void
    {
        $this->organizerId = (int) $this->userRepository->getOrCreateUserIdByEmail('avail-empty@example.com');
        $poll = $this->createPollWithOptionsOnly();
        $this->pollId = $poll->id;

        $slots = $this->service->computeBestSlots($this->organizerId, $poll->id, []);

        $this->assertCount(3, $slots);
        foreach ($slots as $slot) {
            $this->assertSame(0, $slot['total_invited']);
            $this->assertSame(0, $slot['available_count']);
            $this->assertSame([], $slot['available_emails']);
            $this->assertSame([], $slot['unavailable_emails']);
        }
    }

    private function createPollWithOptionsAndVotes(): \Hillmeet\Models\Poll
    {
        $slug = $this->pollRepository->generateSlug();
        $secretHash = password_hash('secret', PASSWORD_DEFAULT);
        $poll = $this->pollRepository->create(
            $this->organizerId,
            $slug,
            $secretHash,
            'Availability test poll',
            null,
            null,
            'UTC',
            30,
        );
        $this->pollRepository->addOption($poll->id, '2026-03-01 14:00:00', '2026-03-01 14:30:00', null, 0);
        $this->pollRepository->addOption($poll->id, '2026-03-01 15:00:00', '2026-03-01 15:30:00', null, 1);
        $this->pollRepository->addOption($poll->id, '2026-03-01 16:00:00', '2026-03-01 16:30:00', null, 2);
        $options = $this->pollRepository->getOptions($poll->id);
        $aliceId = (int) $this->userRepository->getOrCreateUserIdByEmail('alice-avail@example.com');
        $bobId = (int) $this->userRepository->getOrCreateUserIdByEmail('bob-avail@example.com');
        $carolId = (int) $this->userRepository->getOrCreateUserIdByEmail('carol-avail@example.com');
        $this->participantRepository->add($poll->id, $aliceId);
        $this->participantRepository->add($poll->id, $bobId);
        $this->participantRepository->add($poll->id, $carolId);
        $this->voteRepository->setVote($poll->id, $options[0]->id, $aliceId, 'yes');
        $this->voteRepository->setVote($poll->id, $options[0]->id, $bobId, 'maybe');
        $this->voteRepository->setVote($poll->id, $options[0]->id, $carolId, 'no');
        $this->voteRepository->setVote($poll->id, $options[1]->id, $aliceId, 'yes');
        $this->voteRepository->setVote($poll->id, $options[1]->id, $bobId, 'yes');
        $this->voteRepository->setVote($poll->id, $options[1]->id, $carolId, 'no');
        $this->voteRepository->setVote($poll->id, $options[2]->id, $aliceId, 'no');
        $this->voteRepository->setVote($poll->id, $options[2]->id, $bobId, 'maybe');
        $this->voteRepository->setVote($poll->id, $options[2]->id, $carolId, 'yes');
        return $poll;
    }

    /** Poll with 3 options only; no participants, no invitees. */
    private function createPollWithOptionsOnly(): \Hillmeet\Models\Poll
    {
        $slug = $this->pollRepository->generateSlug();
        $secretHash = password_hash('secret', PASSWORD_DEFAULT);
        $poll = $this->pollRepository->create(
            $this->organizerId,
            $slug,
            $secretHash,
            'Empty cohort test poll',
            null,
            null,
            'UTC',
            30,
        );
        $this->pollRepository->addOption($poll->id, '2026-03-01 14:00:00', '2026-03-01 14:30:00', null, 0);
        $this->pollRepository->addOption($poll->id, '2026-03-01 15:00:00', '2026-03-01 15:30:00', null, 1);
        $this->pollRepository->addOption($poll->id, '2026-03-01 16:00:00', '2026-03-01 16:30:00', null, 2);
        return $poll;
    }
}
