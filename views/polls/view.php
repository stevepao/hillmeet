<?php
$pageTitle = \Hillmeet\Support\e($poll->title);
$content = ob_start();
$pollUrlWithSecret = !empty($accessByInvite) && $inviteToken !== ''
  ? \Hillmeet\Support\url('/poll/' . $poll->slug, ['invite' => $inviteToken])
  : \Hillmeet\Support\url('/poll/' . $poll->slug . '?secret=' . urlencode($_GET['secret'] ?? ''));
$resultsExpandUrl = $pollUrlWithSecret . (strpos($pollUrlWithSecret, '?') !== false ? '&' : '?') . 'expand=results';
$voteLabels = ['yes' => 'Works', 'maybe' => 'If needed', 'no' => "Can't"];
$canEdit = !$poll->isLocked();
?>
<h1><?= \Hillmeet\Support\e($poll->title) ?></h1>
<?php if ($poll->description): ?>
  <p class="muted"><?= \Hillmeet\Support\e($poll->description) ?></p>
<?php endif; ?>
<p class="badge badge-muted"><?= \Hillmeet\Support\e($poll->timezone) ?></p>

<?php if (!empty($_SESSION['vote_error'])): ?>
  <div class="card card-2" style="margin:var(--space-4) 0;color:var(--danger);"><?= \Hillmeet\Support\e($_SESSION['vote_error']) ?></div>
  <?php unset($_SESSION['vote_error']); ?>
<?php endif; ?>

<?php if (!empty($_SESSION['invitations_sent'])): unset($_SESSION['invitations_sent']); ?>
  <p class="success-message" role="alert">Invitations sent.</p>
<?php endif; ?>

<div class="poll-options-bar">
  <div class="segmented" role="group" aria-label="View">
    <button type="button" class="view-toggle" data-view="list" aria-pressed="true">List view</button>
    <button type="button" class="view-toggle" data-view="grid">Grid view</button>
  </div>
  <?php if ($hasCalendar): ?>
    <a href="<?= \Hillmeet\Support\url('/calendar') ?>" class="btn btn-secondary btn-sm">Choose calendars</a>
    <button type="button" class="btn btn-secondary btn-sm" id="check-availability">Check my availability</button>
  <?php else: ?>
    <a href="<?= \Hillmeet\Support\url('/calendar') ?>" class="btn btn-secondary btn-sm">Connect Google Calendar</a>
  <?php endif; ?>
  <a href="<?= \Hillmeet\Support\e($resultsExpandUrl) ?>" class="btn btn-secondary btn-sm" id="toggle-results" aria-expanded="false" data-no-js="Show results">Show results</a>
</div>

<p class="helper">We only check free/busy. We never store event details.</p>

<div class="poll-view-list" id="poll-options-list">
  <?php foreach ($options as $opt):
    $vote = $userVotes[$opt->id] ?? '';
    $startLocal = (new DateTime($opt->start_utc, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone($poll->timezone))->format('D M j, g:i A');
    $endLocal = (new DateTime($opt->end_utc, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone($poll->timezone))->format('g:i A');
  ?>
    <div class="option-card" data-option-id="<?= (int)$opt->id ?>">
      <div style="font-weight:500;"><?= \Hillmeet\Support\e($startLocal) ?> – <?= \Hillmeet\Support\e($endLocal) ?></div>
      <?php if (!empty($hasCalendar)): ?>
        <p class="freebusy-badge muted" style="font-size:var(--text-sm); margin:var(--space-1) 0 0;" data-option-id="<?= (int)$opt->id ?>" aria-live="polite">
          Your calendar: <?php
          if (isset($freebusyByOption[$opt->id])) {
              echo $freebusyByOption[$opt->id] ? 'Busy ⛔' : 'Free ✅';
          } else {
              echo '—';
          }
          ?>
        </p>
      <?php endif; ?>
      <?php
        $selectedLabel = $vote === 'yes' ? 'Works' : ($vote === 'maybe' ? 'If needed' : ($vote === 'no' ? "Can't" : '—'));
      ?>
      <?php if (!$poll->isLocked()): ?>
        <div class="vote-controls" role="radiogroup" aria-label="Vote for this time slot">
          <form method="post" action="<?= \Hillmeet\Support\url('/poll/' . $poll->slug . '/vote') ?>" style="display:inline;" class="vote-form">
            <?= \Hillmeet\Support\Csrf::field() ?>
            <?php if (!empty($accessByInvite) && $inviteToken !== ''): ?>
            <input type="hidden" name="invite" value="<?= \Hillmeet\Support\e($inviteToken) ?>">
            <?php else: ?>
            <input type="hidden" name="secret" value="<?= \Hillmeet\Support\e($_GET['secret'] ?? '') ?>">
            <?php endif; ?>
            <input type="hidden" name="option_id" value="<?= (int)$opt->id ?>">
            <input type="hidden" name="back" value="<?= \Hillmeet\Support\e($pollUrlWithSecret) ?>">
            <button type="submit" name="vote" value="yes" class="vote-chip <?= $vote === 'yes' ? 'active' : '' ?>" data-vote="yes" title="Works" aria-pressed="<?= $vote === 'yes' ? 'true' : 'false' ?>">✅ Works</button>
            <button type="submit" name="vote" value="maybe" class="vote-chip <?= $vote === 'maybe' ? 'active' : '' ?>" data-vote="maybe" title="If needed" aria-pressed="<?= $vote === 'maybe' ? 'true' : 'false' ?>">🤷 If needed</button>
            <button type="submit" name="vote" value="no" class="vote-chip <?= $vote === 'no' ? 'active' : '' ?>" data-vote="no" title="Can't" aria-pressed="<?= $vote === 'no' ? 'true' : 'false' ?>">⛔ Can't</button>
          </form>
          <p class="vote-selected-label" aria-live="polite">Selected: <?= \Hillmeet\Support\e($selectedLabel) ?></p>
        </div>
      <?php else: ?>
        <p class="vote-selected-label muted" aria-live="polite">Selected: <?= \Hillmeet\Support\e($selectedLabel) ?></p>
        <span class="badge badge-muted">Locked</span>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>

<?php if ($poll->isLocked()): ?>
  <p class="muted" style="margin-top: var(--space-3);">This poll has been finalized.</p>
<?php elseif ($canEdit): ?>
  <div id="vote-inline-controls" class="vote-inline-controls" aria-live="polite">
    <p id="vote-status-message" class="vote-status-message muted" style="font-size: var(--text-sm); margin: var(--space-3) 0 var(--space-2);"></p>
    <div class="vote-inline-actions" id="vote-inline-actions" hidden>
      <button type="button" class="btn btn-secondary btn-sm" id="vote-cancel">Cancel</button>
      <button type="button" class="btn btn-primary btn-sm" id="vote-submit">Submit votes</button>
    </div>
  </div>
<?php endif; ?>

<details class="results-section" id="results-section" <?= !empty($resultsExpandOpen) ? 'open' : '' ?>>
  <summary>Results</summary>
  <div id="results-content">
    <?= !empty($resultsFragmentHtml) ? $resultsFragmentHtml : '<p class="muted">Couldn\'t load results.</p>' ?>
  </div>
</details>

<?php if ($poll->isOrganizer((int)\Hillmeet\Support\current_user()->id) && $poll->isLocked()): ?>
  <div class="finalize-panel">
    <h3>Finalize</h3>
    <p class="helper">Locking freezes the schedule so everyone sees the final time.</p>
    <?php if (!$eventCreated): ?>
      <form method="post" action="<?= \Hillmeet\Support\url('/poll/' . $poll->slug . '/create-event') ?>" style="margin-top:var(--space-3);">
        <?= \Hillmeet\Support\Csrf::field() ?>
        <input type="hidden" name="secret" value="<?= \Hillmeet\Support\e($_GET['secret'] ?? '') ?>">
        <div class="form-group">
          <label for="calendar_id">Calendar</label>
          <select name="calendar_id" id="calendar_id" class="select" style="width:auto;">
            <option value="primary">Primary</option>
          </select>
        </div>
        <label class="checkbox-label">
          <input type="checkbox" name="invite_participants" value="1"> Invite participants by email
        </label>
        <button type="submit" class="btn btn-primary" style="margin-top:var(--space-3);">Create calendar event</button>
      </form>
    <?php else: ?>
      <p class="badge badge-success">Calendar event created</p>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php if ($poll->isOrganizer((int)\Hillmeet\Support\current_user()->id)): ?>
  <div class="card" style="margin-top:var(--space-5);">
    <h3>Invitations</h3>
    <?php if (count($invites ?? []) > 0): ?>
      <ul class="invite-list" style="list-style:none;padding:0;margin:0;">
        <?php foreach ($invites as $inv): ?>
          <li style="padding:var(--space-2) 0;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;gap:var(--space-3);">
            <span><?= \Hillmeet\Support\e($inv->email) ?></span>
            <span class="muted" style="font-size:var(--text-sm);"><?= $inv->sent_at ? \Hillmeet\Support\e(date('M j, g:i A', strtotime($inv->sent_at))) : '—' ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p class="muted">No invitations sent yet.</p>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php if ($poll->isOrganizer((int)\Hillmeet\Support\current_user()->id) && !$poll->isLocked() && count($options) > 0): ?>
  <div class="finalize-panel">
    <h3>Lock this time</h3>
    <p class="helper">Locking freezes the schedule so everyone sees the final time.</p>
    <form method="post" action="<?= \Hillmeet\Support\url('/poll/' . $poll->slug . '/lock') ?>">
      <?= \Hillmeet\Support\Csrf::field() ?>
      <input type="hidden" name="secret" value="<?= \Hillmeet\Support\e($_GET['secret'] ?? '') ?>">
      <input type="hidden" name="option_id" value="<?= (int)($results['best_option_id'] ?? $options[0]->id) ?>">
      <button type="submit" class="btn btn-primary">Lock this time</button>
    </form>
  </div>
<?php endif; ?>

<script>
window.HILLMEET_POLL = {
  slug: <?= json_encode($poll->slug) ?>,
  secret: <?= json_encode($_GET['secret'] ?? '') ?>,
  invite: <?= json_encode(!empty($accessByInvite) && $inviteToken !== '' ? $inviteToken : '') ?>,
  canEdit: <?= $canEdit ? 'true' : 'false' ?>,
  voteBatchUrl: <?= json_encode(\Hillmeet\Support\url('/poll/' . $poll->slug . '/vote-batch')) ?>,
  csrfToken: <?= json_encode(\Hillmeet\Support\Csrf::token()) ?>,
  resultsUrl: <?= json_encode(!empty($accessByInvite) && $inviteToken !== '' ? \Hillmeet\Support\url('/poll/' . $poll->slug . '/results', ['invite' => $inviteToken]) : \Hillmeet\Support\url('/poll/' . $poll->slug . '/results?secret=' . urlencode($_GET['secret'] ?? ''))) ?>,
  checkAvailabilityUrl: <?= json_encode(!empty($accessByInvite) && $inviteToken !== '' ? \Hillmeet\Support\url('/poll/' . $poll->slug . '/check-availability', ['invite' => $inviteToken]) : \Hillmeet\Support\url('/poll/' . $poll->slug . '/check-availability?secret=' . urlencode($_GET['secret'] ?? ''))) ?>,
  savedVotes: <?= json_encode(array_map(function ($v) { return $v ?? ''; }, $userVotes)) ?>
};
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
?>
