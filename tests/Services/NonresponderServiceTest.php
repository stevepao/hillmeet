<?php

declare(strict_types=1);

namespace Hillmeet\Tests\Services;

use Hillmeet\Repositories\PollInviteRepository;
use Hillmeet\Repositories\PollParticipantRepository;
use Hillmeet\Repositories\PollRepository;
use Hillmeet\Repositories\UserRepository;
use Hillmeet\Repositories\VoteRepository;
use Hillmeet\Services\NonresponderService;
use PHPUnit\Framework\TestCase;

/**
 * Integration-style tests for NonresponderService::findNonrespondersForPoll.
 * Requires config and database; skips if config is not available.
 */
final class NonresponderServiceTest extends TestCase
{
    private ?int $pollId = null;
    private ?int $organizerId = null;
    private UserRepository $userRepository;
    private PollRepository $pollRepository;
    private PollInviteRepository $inviteRepository;
    private PollParticipantRepository $participantRepository;
    private VoteRepository $voteRepository;
    private NonresponderService $service;

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
        $this->inviteRepository = new PollInviteRepository();
        $this->participantRepository = new PollParticipantRepository();
        $this->voteRepository = new VoteRepository();
        $this->service = new NonresponderService(
            $this->pollRepository,
            $this->inviteRepository,
            $this->participantRepository,
            $this->voteRepository,
        );
    }

    protected function tearDown(): void
    {
        if ($this->pollId !== null) {
            $this->pollRepository->deletePoll($this->pollId);
        }
        parent::tearDown();
    }

    /**
     * Poll with 3 invited (participants), 2 have voted → non-responders returns the 1 who has not.
     */
    public function testFindNonrespondersReturnsInvitedMinusResponded(): void
    {
        $this->organizerId = (int) $this->userRepository->getOrCreateUserIdByEmail('nonresp-org@example.com');
        $poll = $this->createPollWithThreeInvitedTwoVoted();
        $this->pollId = $poll->id;

        $list = $this->service->findNonrespondersForPoll($this->organizerId, $poll->id);

        $this->assertCount(1, $list);
        $this->assertSame('carol-nonresp@example.com', $list[0]['email']);
    }

    /**
     * Wrong userId (not organizer) gets empty list (enforced as "no access").
     */
    public function testFindNonrespondersWrongOrganizerReturnsEmpty(): void
    {
        $this->organizerId = (int) $this->userRepository->getOrCreateUserIdByEmail('nonresp-owner@example.com');
        $otherId = (int) $this->userRepository->getOrCreateUserIdByEmail('nonresp-other@example.com');
        $poll = $this->createPollWithThreeInvitedTwoVoted();
        $this->pollId = $poll->id;

        $list = $this->service->findNonrespondersForPoll($otherId, $poll->id);

        $this->assertSame([], $list);
    }

    /**
     * Everyone responded → empty non-responders list.
     */
    public function testFindNonrespondersWhenAllRespondedReturnsEmpty(): void
    {
        $this->organizerId = (int) $this->userRepository->getOrCreateUserIdByEmail('nonresp-all@example.com');
        $poll = $this->createPollWithThreeInvitedAllVoted();
        $this->pollId = $poll->id;

        $list = $this->service->findNonrespondersForPoll($this->organizerId, $poll->id);

        $this->assertSame([], $list);
    }

    /**
     * Emails are normalized (lowercase); duplicates not listed twice.
     */
    public function testFindNonrespondersNormalizesEmailsAndDeduplicates(): void
    {
        $this->organizerId = (int) $this->userRepository->getOrCreateUserIdByEmail('nonresp-norm@example.com');
        $poll = $this->createPollWithDuplicateInviteEmailOneVoted();
        $this->pollId = $poll->id;

        $list = $this->service->findNonrespondersForPoll($this->organizerId, $poll->id);

        $emails = array_column($list, 'email');
        $this->assertCount(1, $emails);
        $this->assertSame('only-invite@example.com', $emails[0]);
    }

    private function createPollWithThreeInvitedTwoVoted(): \Hillmeet\Models\Poll
    {
        $slug = $this->pollRepository->generateSlug();
        $poll = $this->pollRepository->create(
            $this->organizerId,
            $slug,
            password_hash('secret', PASSWORD_DEFAULT),
            'Nonresponder test',
            null,
            null,
            'UTC',
            30,
        );
        $this->pollRepository->addOption($poll->id, '2026-03-01 14:00:00', '2026-03-01 14:30:00', null, 0);
        $options = $this->pollRepository->getOptions($poll->id);
        $aliceId = (int) $this->userRepository->getOrCreateUserIdByEmail('alice-nonresp@example.com');
        $bobId = (int) $this->userRepository->getOrCreateUserIdByEmail('bob-nonresp@example.com');
        $carolId = (int) $this->userRepository->getOrCreateUserIdByEmail('carol-nonresp@example.com');
        $this->participantRepository->add($poll->id, $aliceId);
        $this->participantRepository->add($poll->id, $bobId);
        $this->participantRepository->add($poll->id, $carolId);
        $this->voteRepository->setVote($poll->id, $options[0]->id, $aliceId, 'yes');
        $this->voteRepository->setVote($poll->id, $options[0]->id, $bobId, 'no');
        return $poll;
    }

    private function createPollWithThreeInvitedAllVoted(): \Hillmeet\Models\Poll
    {
        $slug = $this->pollRepository->generateSlug();
        $poll = $this->pollRepository->create(
            $this->organizerId,
            $slug,
            password_hash('secret', PASSWORD_DEFAULT),
            'All responded test',
            null,
            null,
            'UTC',
            30,
        );
        $this->pollRepository->addOption($poll->id, '2026-03-01 14:00:00', '2026-03-01 14:30:00', null, 0);
        $options = $this->pollRepository->getOptions($poll->id);
        $a = (int) $this->userRepository->getOrCreateUserIdByEmail('nr-all-a@example.com');
        $b = (int) $this->userRepository->getOrCreateUserIdByEmail('nr-all-b@example.com');
        $this->participantRepository->add($poll->id, $a);
        $this->participantRepository->add($poll->id, $b);
        $this->voteRepository->setVote($poll->id, $options[0]->id, $a, 'yes');
        $this->voteRepository->setVote($poll->id, $options[0]->id, $b, 'maybe');
        return $poll;
    }

    private function createPollWithDuplicateInviteEmailOneVoted(): \Hillmeet\Models\Poll
    {
        $slug = $this->pollRepository->generateSlug();
        $poll = $this->pollRepository->create(
            $this->organizerId,
            $slug,
            password_hash('secret', PASSWORD_DEFAULT),
            'Dedup test',
            null,
            null,
            'UTC',
            30,
        );
        $this->pollRepository->addOption($poll->id, '2026-03-01 14:00:00', '2026-03-01 14:30:00', null, 0);
        $options = $this->pollRepository->getOptions($poll->id);
        $this->inviteRepository->createInvite($poll->id, 'Dup@Example.com', hash('sha256', 't1'), $this->organizerId);
        $this->inviteRepository->createInvite($poll->id, 'dup@example.com', hash('sha256', 't2'), $this->organizerId);
        $userId = (int) $this->userRepository->getOrCreateUserIdByEmail('dup@example.com');
        $this->participantRepository->add($poll->id, $userId);
        $this->voteRepository->setVote($poll->id, $options[0]->id, $userId, 'yes');
        $this->inviteRepository->createInvite($poll->id, 'only-invite@example.com', hash('sha256', 't3'), $this->organizerId);
        return $poll;
    }
}
