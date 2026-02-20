<?php
$pageTitle = 'Invite people';
$content = ob_start();
$shareUrlQuery = $secret !== '' ? ['secret' => $secret] : [];
$pollUrl = \Hillmeet\Support\url('/poll/' . $poll->slug, $shareUrlQuery);
$shareFormAction = \Hillmeet\Support\url('/poll/' . $poll->slug . '/share', $secret !== '' ? ['secret' => $secret] : []);
?>
<h1>Invite people</h1>
<div class="steps">
  <a href="<?= \Hillmeet\Support\url('/poll/' . $poll->slug . '/edit') ?>">Details</a>
  <a href="<?= \Hillmeet\Support\url('/poll/' . $poll->slug . '/options') ?>">Add times</a>
  <span class="active">Share</span>
</div>

<?php if (!empty($_SESSION['invite_error'])): ?>
  <div class="card card-2" style="margin-top:var(--space-4);color:var(--danger);"><?= \Hillmeet\Support\e($_SESSION['invite_error']) ?></div>
  <?php unset($_SESSION['invite_error']); ?>
<?php endif; ?>

<?php if (!empty($_SESSION['invitations_sent'])): unset($_SESSION['invitations_sent']); ?>
  <p class="success-message" role="alert" style="margin-top:var(--space-4);">Invitations sent.</p>
<?php endif; ?>

<div class="card" style="margin-top:var(--space-5);">
  <h2>Poll link</h2>
  <p class="helper">Share this link so people can vote. Only people with the link can access the poll.</p>
  <div style="display:flex;gap:var(--space-2);flex-wrap:wrap;align-items:center;">
    <input type="text" id="poll-url" class="input" value="<?= \Hillmeet\Support\e($pollUrl) ?>" readonly style="flex:1;min-width:0;">
    <button type="button" class="btn btn-secondary" id="copy-link">Copy link</button>
  </div>
</div>

<div class="card" style="margin-top:var(--space-4);">
  <h2>Invite new people</h2>
  <p class="helper">We'll send each person the private link. Existing invitees won't be re-sent automatically.</p>
  <form method="post" action="<?= \Hillmeet\Support\e($shareFormAction) ?>">
    <?= \Hillmeet\Support\Csrf::field() ?>
    <input type="hidden" name="secret" value="<?= \Hillmeet\Support\e($secret) ?>">
    <div class="form-group">
      <label for="emails">Email addresses (one per line)</label>
      <textarea id="emails" name="emails" class="textarea" rows="4" placeholder="friend@example.com"></textarea>
    </div>
    <button type="submit" class="btn btn-primary">Send invitations</button>
  </form>
</div>

<div class="card" style="margin-top:var(--space-4);">
  <h2>Already invited</h2>
  <?php if (empty($invites)): ?>
    <p class="muted">No invitations yet. Add emails above to send invitations.</p>
  <?php else: ?>
    <ul class="invite-list" style="list-style:none;margin:0;padding:0;">
      <?php foreach ($invites as $inv): ?>
        <?php
          $status = !empty($inv->accepted_at) ? 'Accepted' : 'Sent';
          $sentLabel = $inv->sent_at ? (new DateTime($inv->sent_at))->format('M j, Y g:i A') : '—';
        ?>
        <li class="invite-row" style="display:flex;flex-wrap:wrap;align-items:center;gap:var(--space-2);padding:var(--space-2) 0;border-bottom:1px solid var(--border);">
          <span style="flex:1;min-width:0;"><?= \Hillmeet\Support\e($inv->email) ?></span>
          <span class="badge <?= $status === 'Accepted' ? 'badge-success' : 'badge-muted' ?>"><?= \Hillmeet\Support\e($status) ?></span>
          <span class="muted" style="font-size:var(--text-sm);"><?= \Hillmeet\Support\e($sentLabel) ?></span>
          <form method="post" action="<?= \Hillmeet\Support\e(\Hillmeet\Support\url('/poll/' . $poll->slug . '/invite-resend', $shareUrlQuery)) ?>" style="display:inline;">
            <?= \Hillmeet\Support\Csrf::field() ?>
            <input type="hidden" name="secret" value="<?= \Hillmeet\Support\e($secret) ?>">
            <input type="hidden" name="invite_id" value="<?= (int)$inv->id ?>">
            <button type="submit" class="btn btn-secondary btn-sm">Resend</button>
          </form>
          <form method="post" action="<?= \Hillmeet\Support\e(\Hillmeet\Support\url('/poll/' . $poll->slug . '/invite-remove', $shareUrlQuery)) ?>" style="display:inline;" onsubmit="return confirm('Remove this invitation?');">
            <?= \Hillmeet\Support\Csrf::field() ?>
            <input type="hidden" name="secret" value="<?= \Hillmeet\Support\e($secret) ?>">
            <input type="hidden" name="invite_id" value="<?= (int)$inv->id ?>">
            <button type="submit" class="btn btn-secondary btn-sm" style="color:var(--danger);">Remove</button>
          </form>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>

<p style="margin-top:var(--space-5);">
  <a href="<?= \Hillmeet\Support\e($pollUrl) ?>" class="btn btn-primary">Open poll</a>
</p>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
?>
