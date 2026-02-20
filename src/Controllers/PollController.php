<?php

declare(strict_types=1);

namespace Hillmeet\Controllers;

use Hillmeet\Models\Poll;
use Hillmeet\Repositories\CalendarEventRepository;
use Hillmeet\Repositories\GoogleCalendarSelectionRepository;
use Hillmeet\Repositories\OAuthConnectionRepository;
use Hillmeet\Repositories\PollInviteRepository;
use Hillmeet\Repositories\PollParticipantRepository;
use Hillmeet\Repositories\PollRepository;
use Hillmeet\Repositories\UserRepository;
use Hillmeet\Repositories\VoteRepository;
use Hillmeet\Services\GoogleCalendarService;
use Hillmeet\Services\PollService;
use Hillmeet\Support\Csrf;
use function Hillmeet\Support\current_user;
use function Hillmeet\Support\e;
use function Hillmeet\Support\url;

final class PollController
{
    private PollRepository $pollRepo;
    private PollService $pollService;

    private function auth(): void
    {
        \Hillmeet\Middleware\RequireAuth::check();
    }

    public function __construct()
    {
        $this->pollRepo = new PollRepository();
        $this->pollService = new PollService(
            $this->pollRepo,
            new VoteRepository(),
            new PollParticipantRepository(),
            new PollInviteRepository(),
            new \Hillmeet\Services\EmailService()
        );
    }

    public function newPoll(): void
    {
        $this->auth();
        require dirname(__DIR__, 2) . '/views/polls/new.php';
    }

    public function createStep1(): void
    {
        $this->auth();
        require dirname(__DIR__, 2) . '/views/polls/create_step1.php';
    }

    public function createPost(): void
    {
        $this->auth();
        $user = current_user();
        $result = $this->pollService->createPoll((int) $user->id, $_POST, $_SERVER['REMOTE_ADDR'] ?? '');
        if (isset($result['error'])) {
            $_SESSION['poll_error'] = $result['error'];
            $_SESSION['poll_input'] = $_POST;
            header('Location: ' . url('/poll/create'));
            exit;
        }
        $_SESSION['new_poll_secret'] = $result['secret'];
        header('Location: ' . url('/poll/' . $result['poll']->slug . '/options'));
        exit;
    }

    public function edit(string $slug): void
    {
        $this->auth();
        $poll = $this->pollRepo->findBySlug($slug);
        if ($poll === null || !$poll->isOrganizer((int) current_user()->id)) {
            http_response_code(404);
            require dirname(__DIR__, 2) . '/views/errors/404.php';
            exit;
        }
        $options = $this->pollRepo->getOptions($poll->id);
        require dirname(__DIR__, 2) . '/views/polls/edit.php';
    }

    public function options(string $slug): void
    {
        $this->auth();
        $poll = $this->pollRepo->findBySlug($slug);
        if ($poll === null || !$poll->isOrganizer((int) current_user()->id)) {
            http_response_code(404);
            require dirname(__DIR__, 2) . '/views/errors/404.php';
            exit;
        }
        $options = $this->pollRepo->getOptions($poll->id);
        $timezones = timezone_identifiers_list();
        require dirname(__DIR__, 2) . '/views/polls/options.php';
    }

    public function optionsPost(string $slug): void
    {
        $this->auth();
        $poll = $this->pollRepo->findBySlug($slug);
        if ($poll === null || !$poll->isOrganizer((int) current_user()->id)) {
            http_response_code(404);
            exit;
        }
        $toAdd = $_POST['options'] ?? [];
        if (is_string($toAdd)) {
            $toAdd = json_decode($toAdd, true) ?: [];
        }
        $tz = new \DateTimeZone($poll->timezone);
        $utc = new \DateTimeZone('UTC');
        $normalized = [];
        foreach ($toAdd as $o) {
            $start = isset($o['start']) ? new \DateTime($o['start'], $tz) : null;
            $end = isset($o['end']) ? new \DateTime($o['end'], $tz) : null;
            if ($start && $end) {
                $normalized[] = [
                    'start_utc' => $start->setTimezone($utc)->format('Y-m-d H:i:s'),
                    'end_utc' => $end->setTimezone($utc)->format('Y-m-d H:i:s'),
                    'label' => $o['label'] ?? null,
                ];
            }
        }
        if (count($normalized) === 0) {
            $_SESSION['poll_error'] = 'Select at least one time option.';
            header('Location: ' . url('/poll/' . $slug . '/options'));
            exit;
        }
        $this->pollService->addTimeOptions($poll->id, $normalized);
        header('Location: ' . url('/poll/' . $slug . '/share'));
        exit;
    }

    public function share(string $slug): void
    {
        $this->auth();
        $poll = $this->pollRepo->findBySlug($slug);
        if ($poll === null || !$poll->isOrganizer((int) current_user()->id)) {
            http_response_code(404);
            require dirname(__DIR__, 2) . '/views/errors/404.php';
            exit;
        }
        $secret = $_SESSION['new_poll_secret'] ?? '';
        $inviteRepo = new PollInviteRepository();
        $invites = $inviteRepo->getByPoll($poll->id);
        require dirname(__DIR__, 2) . '/views/polls/share.php';
    }

    public function sharePost(string $slug): void
    {
        $this->auth();
        $poll = $this->pollRepo->findBySlug($slug);
        if ($poll === null || !$poll->isOrganizer((int) current_user()->id)) {
            http_response_code(404);
            exit;
        }
        $secret = $_POST['secret'] ?? $_SESSION['new_poll_secret'] ?? '';
        $pollUrl = url('/poll/' . $slug . '?secret=' . urlencode($secret));
        $emails = array_filter(array_map('trim', explode("\n", $_POST['emails'] ?? '')));
        $this->pollService->sendInvites($poll->id, $emails, (int) current_user()->id, $pollUrl, $_SERVER['REMOTE_ADDR'] ?? '');
        $_SESSION['invitations_sent'] = true;
        header('Location: ' . url('/poll/' . $slug . '?secret=' . urlencode($secret)));
        exit;
    }

    public function view(string $slug): void
    {
        $this->auth();
        $secret = $_GET['secret'] ?? '';
        $inviteToken = $_GET['invite'] ?? '';
        $poll = null;
        $accessByInvite = false;

        if ($secret !== '') {
            $poll = $this->pollRepo->findBySlugAndVerifySecret($slug, $secret);
            $accessByInvite = false;
        } elseif ($inviteToken !== '') {
            $inviteRepo = new PollInviteRepository();
            $tokenHash = hash('sha256', $inviteToken);
            $invite = $inviteRepo->findByPollSlugAndTokenHash($slug, $tokenHash);
            if ($invite === null) {
                http_response_code(404);
                require dirname(__DIR__, 2) . '/views/errors/404.php';
                exit;
            }
            $userId = (int) current_user()->id;
            $inviteRepo->markAccepted((int) $invite->id, $userId);
            (new PollParticipantRepository())->add((int) $invite->poll_id, $userId);
            $poll = $this->pollRepo->findById((int) $invite->poll_id);
            if ($poll === null || $poll->slug !== $slug) {
                http_response_code(404);
                require dirname(__DIR__, 2) . '/views/errors/404.php';
                exit;
            }
            $accessByInvite = true;
        } else {
            $userId = (int) current_user()->id;
            $candidate = $this->pollRepo->findBySlug($slug);
            if ($candidate !== null) {
                $isOrganizer = $candidate->isOrganizer($userId);
                $participantRepo = new PollParticipantRepository();
                $voteRepo = new VoteRepository();
                $isParticipant = $participantRepo->isParticipant($candidate->id, $userId) || $voteRepo->hasVoteInPoll($candidate->id, $userId);
                if ($isOrganizer || $isParticipant) {
                    $poll = $candidate;
                    $accessByInvite = false;
                }
            }
        }

        if ($poll === null) {
            http_response_code(404);
            require dirname(__DIR__, 2) . '/views/errors/404.php';
            exit;
        }

        $options = $this->pollRepo->getOptions($poll->id);
        $voteRepo = new VoteRepository();
        $userVotes = [];
        $userId = (int) current_user()->id;
        foreach ($options as $opt) {
            $userVotes[$opt->id] = $voteRepo->getVote($opt->id, $userId);
        }
        $participantRepo = new PollParticipantRepository();
        $participantRepo->add($poll->id, $userId);
        $results = $this->pollService->getResults($poll);
        $calendarService = new GoogleCalendarService(
            new OAuthConnectionRepository(),
            new GoogleCalendarSelectionRepository(),
            new \Hillmeet\Repositories\FreebusyCacheRepository()
        );
        $hasCalendar = $calendarService->getAuthUrl('x') !== '' && (new OAuthConnectionRepository())->hasConnection($userId);
        $freebusyByOption = [];
        if ($hasCalendar) {
            $freebusyCache = new \Hillmeet\Repositories\FreebusyCacheRepository();
            $ttl = (int) \Hillmeet\Support\config('freebusy_cache_ttl', 600);
            $optionIds = array_column($options, 'id');
            $freebusyByOption = $freebusyCache->getForPoll($userId, $poll->id, $optionIds, $ttl);
        }
        $eventRepo = new CalendarEventRepository();
        $eventCreated = $poll->locked_option_id && $eventRepo->existsForPollAndOption($poll->id, $poll->locked_option_id);
        $invites = $poll->isOrganizer($userId)
            ? (new PollInviteRepository())->listInvites($poll->id)
            : [];
        $resultsExpandOpen = isset($_GET['expand']) && $_GET['expand'] === 'results';
        $participants = $participantRepo->getResultsParticipants($poll->id);
        $myVotes = [];
        foreach ($results['matrix'] ?? [] as $optId => $userVotes) {
            $myVotes[$optId] = $userVotes[$userId] ?? null;
        }
        $resultsDebug = null;
        $resultsError = null;
        if (\env('APP_ENV', '') === 'local' || \env('APP_DEBUG', '') === 'true') {
            $voteRepo = new VoteRepository();
            $ppIds = $participantRepo->getParticipantIds($poll->id);
            $voterIds = $voteRepo->getDistinctVoterIds($poll->id);
            $resultsDebug = [
                'poll_id' => $poll->id,
                'user_id' => $userId,
                'options_count' => count($options),
                'votes_count' => array_sum(array_map('count', $results['matrix'] ?? [])),
                'participants_count' => count($ppIds),
                'voters_count' => count($voterIds),
                'mismatch' => array_values(array_diff($voterIds, $ppIds)),
                'participants' => $participants,
                'voters' => $voteRepo->getVotersWithUsers($poll->id),
            ];
        }
        ob_start();
        try {
            require dirname(__DIR__, 2) . '/views/polls/results_fragment.php';
            $resultsFragmentHtml = ob_get_clean();
        } catch (Throwable $e) {
            ob_end_clean();
            $resultsFragmentHtml = '<p class="muted">Couldn\'t load results.</p>';
            if (\env('APP_ENV', '') === 'local' || \env('APP_DEBUG', '') === 'true') {
                $resultsFragmentHtml .= ' <span style="font-size:var(--text-xs);">' . \Hillmeet\Support\e($e->getMessage()) . '</span>';
                error_log('[Hillmeet results fragment] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            }
        }
        require dirname(__DIR__, 2) . '/views/polls/view.php';
    }

    public function vote(string $slug): void
    {
        $this->auth();
        $secret = $_POST['secret'] ?? $_GET['secret'] ?? '';
        $inviteToken = $_POST['invite'] ?? $_GET['invite'] ?? '';
        $poll = null;
        $backPath = '';

        if ($secret !== '') {
            $poll = $this->pollRepo->findBySlugAndVerifySecret($slug, $secret);
            $backPath = $poll ? url('/poll/' . $slug . '?secret=' . urlencode($secret)) : '';
        } elseif ($inviteToken !== '') {
            $inviteRepo = new PollInviteRepository();
            $tokenHash = hash('sha256', $inviteToken);
            $invite = $inviteRepo->findByPollSlugAndTokenHash($slug, $tokenHash);
            if ($invite !== null) {
                $poll = $this->pollRepo->findById((int) $invite->poll_id);
                $backPath = $poll && $poll->slug === $slug ? url('/poll/' . $slug . '?invite=' . urlencode($inviteToken)) : '';
            }
        } else {
            $userId = (int) current_user()->id;
            $candidate = $this->pollRepo->findBySlug($slug);
            if ($candidate !== null) {
                $participantRepo = new PollParticipantRepository();
                $voteRepo = new VoteRepository();
                $isParticipant = $participantRepo->isParticipant($candidate->id, $userId) || $voteRepo->hasVoteInPoll($candidate->id, $userId);
                if ($candidate->isOrganizer($userId) || $isParticipant) {
                    $poll = $candidate;
                    $backPath = url('/poll/' . $slug);
                }
            }
        }

        if ($poll === null) {
            $bySlug = $this->pollRepo->findBySlug($slug);
            if ($bySlug === null) {
                http_response_code(404);
                require dirname(__DIR__, 2) . '/views/errors/404.php';
                exit;
            }
            http_response_code(403);
            $errorMessage = 'This poll link is missing or invalid. Use the link from your invitation or from the organizer.';
            require dirname(__DIR__, 2) . '/views/errors/403.php';
            exit;
        }
        if ($poll->isLocked()) {
            $_SESSION['vote_error'] = 'This poll has been finalized.';
            header('Location: ' . ($_POST['back'] ?? url('/poll/' . $slug)));
            exit;
        }
        $optionId = (int) ($_POST['option_id'] ?? 0);
        $vote = $_POST['vote'] ?? '';
        $err = $this->pollService->vote($poll->id, $optionId, (int) current_user()->id, $vote, $_SERVER['REMOTE_ADDR'] ?? '');
        if ($err !== null) {
            $_SESSION['vote_error'] = $err;
            if ((\env('APP_ENV', '') === 'local')) {
                error_log('[Hillmeet vote] ' . $err . ' poll_id=' . $poll->id . ' option_id=' . $optionId . ' vote=' . $vote);
            }
        } else {
            $_SESSION['vote_saved'] = true;
        }
        $back = $_POST['back'] ?? $backPath;
        if ($back === '') {
            $back = $secret !== '' ? url('/poll/' . $slug . '?secret=' . urlencode($secret)) : url('/poll/' . $slug . '?invite=' . urlencode($inviteToken));
        }
        header('Location: ' . $back);
        exit;
    }

    public function voteBatch(string $slug): void
    {
        $this->auth();
        header('Content-Type: application/json; charset=utf-8');
        $secret = $_POST['secret'] ?? '';
        $inviteToken = $_POST['invite'] ?? '';
        $poll = null;
        if ($secret !== '') {
            $poll = $this->pollRepo->findBySlugAndVerifySecret($slug, $secret);
        } elseif ($inviteToken !== '') {
            $inviteRepo = new PollInviteRepository();
            $tokenHash = hash('sha256', $inviteToken);
            $invite = $inviteRepo->findByPollSlugAndTokenHash($slug, $tokenHash);
            if ($invite !== null) {
                $poll = $this->pollRepo->findById((int) $invite->poll_id);
                if ($poll === null || $poll->slug !== $slug) {
                    $poll = null;
                }
            }
        } else {
            $userId = (int) current_user()->id;
            $candidate = $this->pollRepo->findBySlug($slug);
            if ($candidate !== null) {
                $participantRepo = new PollParticipantRepository();
                $voteRepo = new VoteRepository();
                $isParticipant = $participantRepo->isParticipant($candidate->id, $userId) || $voteRepo->hasVoteInPoll($candidate->id, $userId);
                if ($candidate->isOrganizer($userId) || $isParticipant) {
                    $poll = $candidate;
                }
            }
        }
        if ($poll === null) {
            $bySlug = $this->pollRepo->findBySlug($slug);
            if ($bySlug === null) {
                http_response_code(404);
                echo json_encode(['error' => 'Poll not found.']);
                exit;
            }
            http_response_code(403);
            echo json_encode(['error' => 'This poll link is missing or invalid. Use the link from your invitation or from the organizer.']);
            exit;
        }
        if ($poll->isLocked()) {
            http_response_code(403);
            echo json_encode(['error' => 'This poll has been finalized.']);
            exit;
        }
        $votes = $_POST['votes'] ?? [];
        if (!is_array($votes)) {
            $votes = [];
        }
        $err = $this->pollService->voteBatch($poll->id, (int) current_user()->id, $votes, $_SERVER['REMOTE_ADDR'] ?? '');
        if ($err !== null) {
            if (\env('APP_ENV', '') === 'local') {
                error_log('[Hillmeet vote-batch] error: ' . $err . ' poll_id=' . $poll->id . ' votes_count=' . count($votes));
            }
            http_response_code(400);
            echo json_encode(['error' => $err]);
            exit;
        }
        $savedVotes = [];
        foreach ($votes as $optId => $v) {
            if (in_array($v, ['yes', 'maybe', 'no'], true)) {
                $savedVotes[(int) $optId] = $v;
            }
        }
        if (\env('APP_ENV', '') === 'local') {
            error_log('[Hillmeet vote-batch] persisted count=' . count($savedVotes) . ' poll_id=' . $poll->id);
        }
        echo json_encode(['success' => true, 'savedVotes' => $savedVotes]);
        exit;
    }

    public function resultsFragment(string $slug): void
    {
        $this->auth();
        $secret = $_GET['secret'] ?? '';
        $inviteToken = $_GET['invite'] ?? '';
        $poll = null;
        if ($secret !== '') {
            $poll = $this->pollRepo->findBySlugAndVerifySecret($slug, $secret);
        } elseif ($inviteToken !== '') {
            $inviteRepo = new PollInviteRepository();
            $tokenHash = hash('sha256', $inviteToken);
            $invite = $inviteRepo->findByPollSlugAndTokenHash($slug, $tokenHash);
            if ($invite !== null) {
                $poll = $this->pollRepo->findById((int) $invite->poll_id);
                if ($poll === null || $poll->slug !== $slug) {
                    $poll = null;
                }
            }
        } else {
            $userId = (int) current_user()->id;
            $candidate = $this->pollRepo->findBySlug($slug);
            if ($candidate !== null) {
                $participantRepo = new PollParticipantRepository();
                $voteRepo = new VoteRepository();
                $isParticipant = $participantRepo->isParticipant($candidate->id, $userId) || $voteRepo->hasVoteInPoll($candidate->id, $userId);
                if ($candidate->isOrganizer($userId) || $isParticipant) {
                    $poll = $candidate;
                }
            }
        }
        if ($poll === null) {
            http_response_code(403);
            header('Content-Type: text/html; charset=utf-8');
            echo '<p class="muted">You don\'t have permission to view results.</p>';
            exit;
        }
        $results = $this->pollService->getResults($poll);
        $options = $results['options'];
        $participantRepo = new PollParticipantRepository();
        $participants = $participantRepo->getResultsParticipants($poll->id);
        $currentUserId = (int) current_user()->id;
        $myVotes = [];
        foreach ($results['matrix'] ?? [] as $optId => $userVotes) {
            $myVotes[$optId] = $userVotes[$currentUserId] ?? null;
        }
        $resultsDebug = null;
        if (\env('APP_ENV', '') === 'local' || \env('APP_DEBUG', '') === 'true') {
            $voteRepo = new VoteRepository();
            $ppIds = $participantRepo->getParticipantIds($poll->id);
            $voterIds = $voteRepo->getDistinctVoterIds($poll->id);
            $votesCount = 0;
            foreach ($results['matrix'] ?? [] as $optVotes) {
                $votesCount += count($optVotes);
            }
            $resultsDebug = [
                'poll_id' => $poll->id,
                'user_id' => $currentUserId,
                'options_count' => count($options),
                'votes_count' => $votesCount,
                'participants_count' => count($ppIds),
                'voters_count' => count($voterIds),
                'mismatch' => array_values(array_diff($voterIds, $ppIds)),
                'participants' => $participants,
                'voters' => $voteRepo->getVotersWithUsers($poll->id),
            ];
        }
        require dirname(__DIR__, 2) . '/views/polls/results_fragment.php';
    }

    public function lock(string $slug): void
    {
        $this->auth();
        $secret = $_POST['secret'] ?? '';
        $poll = $secret ? $this->pollRepo->findBySlugAndVerifySecret($slug, $secret) : null;
        if ($poll === null || !$poll->isOrganizer((int) current_user()->id)) {
            http_response_code(403);
            exit;
        }
        $optionId = (int) ($_POST['option_id'] ?? 0);
        $this->pollService->lockPoll($poll->id, $optionId, (int) current_user()->id);
        header('Location: ' . url('/poll/' . $slug . '?secret=' . urlencode($secret)));
        exit;
    }

    public function createEvent(string $slug): void
    {
        $this->auth();
        $secret = $_POST['secret'] ?? '';
        $poll = $secret ? $this->pollRepo->findBySlugAndVerifySecret($slug, $secret) : null;
        if ($poll === null || !$poll->isOrganizer((int) current_user()->id) || !$poll->isLocked() || $poll->locked_option_id === null) {
            http_response_code(403);
            exit;
        }
        $options = $this->pollRepo->getOptions($poll->id);
        $lockedOption = null;
        foreach ($options as $o) {
            if ($o->id === $poll->locked_option_id) {
                $lockedOption = $o;
                break;
            }
        }
        if ($lockedOption === null) {
            http_response_code(400);
            exit;
        }
        $calendarId = $_POST['calendar_id'] ?? 'primary';
        $inviteParticipants = !empty($_POST['invite_participants']);
        $participantRepo = new PollParticipantRepository();
        $userRepo = new UserRepository();
        $emails = [];
        if ($inviteParticipants) {
            foreach ($participantRepo->getParticipantIds($poll->id) as $uid) {
                $u = $userRepo->findById($uid);
                if ($u !== null) {
                    $emails[] = $u->email;
                }
            }
        }
        $calendarService = new GoogleCalendarService(
            new OAuthConnectionRepository(),
            new GoogleCalendarSelectionRepository(),
            new \Hillmeet\Repositories\FreebusyCacheRepository()
        );
        $eventId = $calendarService->createEvent(
            (int) current_user()->id,
            $calendarId,
            $poll->title,
            $poll->description ?? '',
            $poll->location ?? '',
            $lockedOption->start_utc,
            $lockedOption->end_utc,
            $emails
        );
        if ($eventId !== null) {
            (new CalendarEventRepository())->create($poll->id, $lockedOption->id, (int) current_user()->id, $calendarId, $eventId);
        }
        header('Location: ' . url('/poll/' . $slug . '?secret=' . urlencode($secret)));
        exit;
    }

    public function checkAvailability(string $slug): void
    {
        $this->auth();
        header('Content-Type: application/json; charset=utf-8');
        $secret = $_GET['secret'] ?? $_POST['secret'] ?? '';
        $inviteToken = $_GET['invite'] ?? $_POST['invite'] ?? '';
        $poll = null;
        if ($secret !== '') {
            $poll = $this->pollRepo->findBySlugAndVerifySecret($slug, $secret);
        } elseif ($inviteToken !== '') {
            $inviteRepo = new PollInviteRepository();
            $tokenHash = hash('sha256', $inviteToken);
            $invite = $inviteRepo->findByPollSlugAndTokenHash($slug, $tokenHash);
            if ($invite !== null) {
                $poll = $this->pollRepo->findById((int) $invite->poll_id);
                if ($poll === null || $poll->slug !== $slug) {
                    $poll = null;
                }
            }
        } else {
            $userId = (int) current_user()->id;
            $candidate = $this->pollRepo->findBySlug($slug);
            if ($candidate !== null) {
                $participantRepo = new PollParticipantRepository();
                $voteRepo = new VoteRepository();
                $isParticipant = $participantRepo->isParticipant($candidate->id, $userId) || $voteRepo->hasVoteInPoll($candidate->id, $userId);
                if ($candidate->isOrganizer($userId) || $isParticipant) {
                    $poll = $candidate;
                }
            }
        }
        if ($poll === null) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error_code' => 'not_found', 'error_message' => 'Poll not found.', 'action_hint' => 'Use the correct poll link.']);
            exit;
        }
        $userId = (int) current_user()->id;
        $oauthRepo = new OAuthConnectionRepository();
        $selectionRepo = new GoogleCalendarSelectionRepository();
        $freebusyCache = new \Hillmeet\Repositories\FreebusyCacheRepository();
        $options = $this->pollRepo->getOptions($poll->id);

        $sendError = function (string $code, string $message, string $hint, int $httpStatus = 400, array $extra = []): void {
            http_response_code($httpStatus);
            $payload = array_merge(['ok' => false, 'error_code' => $code, 'error_message' => $message, 'action_hint' => $hint], $extra);
            echo json_encode($payload);
            exit;
        };

        if (!$oauthRepo->hasConnection($userId)) {
            $sendError('not_connected', 'Connect Google Calendar to check availability.', 'Go to Calendar and connect your Google account.', 403);
        }
        $selectedCalendarIds = $selectionRepo->getSelectedCalendarIds($userId);
        if ($selectedCalendarIds === []) {
            $sendError('no_calendars', 'No calendars selected.', 'Select at least one calendar in Calendar settings.', 400);
        }
        if (count($options) === 0) {
            $sendError('no_slots', 'No times to check.', 'Add time options to this poll first.', 400);
        }

        $calendarService = new GoogleCalendarService($oauthRepo, $selectionRepo, $freebusyCache);
        $out = $calendarService->getFreebusyForPoll($userId, $poll->id, $options);

        $timeMin = $options[0]->start_utc ?? null;
        $timeMax = $options !== [] ? end($options)->end_utc : null;
        $isLocal = \env('APP_ENV', '') === 'local' || \env('APP_DEBUG', '') === 'true';

        if (isset($out['error'])) {
            $code = $out['error'];
            $messages = [
                'not_connected' => ['Calendar not connected.', 'Connect Google Calendar in Calendar settings.', 403],
                'no_calendars' => ['No calendars selected.', 'Select at least one calendar.', 400],
                'token_refresh_failed' => ['Calendar authorization expired.', 'Please reconnect your Google account.', 401],
                'insufficient_permissions' => ['Permission missing.', 'Reconnect and allow Calendar access.', 403],
                'rate_limited' => ['Too many checks.', 'Wait a moment and try again.', 429],
                'api_error' => ['Calendar API error.', 'Try again or reconnect calendar.', 502],
            ];
            $tuple = $messages[$code] ?? ['Something went wrong.', 'Try again or reconnect calendar.', 502];
            $httpStatus = $tuple[2];
            $desc = $out['error_description'] ?? $tuple[0];
            error_log(sprintf(
                '[Hillmeet check-availability] error user_id=%d poll_id=%d code=%s desc=%s calendars=%d slots=%d api_status=%s',
                $userId,
                $poll->id,
                $code,
                $desc,
                count($selectedCalendarIds),
                count($options),
                (string) ($out['api_status'] ?? '')
            ));
            $payload = ['ok' => false, 'error_code' => $code, 'error_message' => $desc, 'action_hint' => $tuple[1]];
            if ($isLocal && isset($out['api_status'])) {
                $payload['debug'] = ['api_status' => $out['api_status'], 'api_error_body' => $out['api_error_body'] ?? null];
            }
            if ($isLocal && isset($out['error_description']) && $out['error_description'] !== '') {
                $payload['debug'] = ($payload['debug'] ?? []) + ['error_description' => $out['error_description']];
            }
            http_response_code($httpStatus);
            echo json_encode($payload);
            exit;
        }

        if ($isLocal) {
            error_log(sprintf(
                '[Hillmeet check-availability] ok user_id=%d poll_id=%d calendars=%d slots=%d time_min=%s time_max=%s cache_writes=%d',
                $userId,
                $poll->id,
                count($selectedCalendarIds),
                count($options),
                $timeMin ?? '',
                $timeMax ?? '',
                count($out['busy'])
            ));
        }
        $wantsJson = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest')
            || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);
        if (!$wantsJson) {
            $back = $inviteToken !== '' ? url('/poll/' . $slug . '?invite=' . urlencode($inviteToken)) : url('/poll/' . $slug . '?secret=' . urlencode($secret));
            header('Location: ' . $back);
            exit;
        }
        echo json_encode(['ok' => true, 'busy' => $out['busy'], 'checked_at' => $out['checked_at']]);
        exit;
    }
}
