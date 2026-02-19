<?php
$pageTitle = 'Invite people';
$content = ob_start();
$pollUrl = \Hillmeet\Support\url('/poll/' . $poll->slug . '?secret=' . urlencode($secret));
?>
<h1>Invite people</h1>
<div class="steps">
  <a href="<?= \Hillmeet\Support\url('/poll/' . $poll->slug . '/edit') ?>">Details</a>
  <a href="<?= \Hillmeet\Support\url('/poll/' . $poll->slug . '/options') ?>">Add times</a>
  <span class="active">Share</span>
</div>

<div class="card" style="margin-top:var(--space-5);">
  <h2>Poll link</h2>
  <p class="helper">Share this link so people can vote. Only people with the link can access the poll.</p>
  <div style="display:flex;gap:var(--space-2);flex-wrap:wrap;align-items:center;">
    <input type="text" id="poll-url" class="input" value="<?= \Hillmeet\Support\e($pollUrl) ?>" readonly style="flex:1;min-width:0;">
    <button type="button" class="btn btn-secondary" id="copy-link">Copy link</button>
  </div>
</div>

<div class="card" style="margin-top:var(--space-4);">
  <h2>Invite by email</h2>
  <form method="post" action="<?= \Hillmeet\Support\url('/poll/' . $poll->slug . '/share') ?>">
    <?= \Hillmeet\Support\Csrf::field() ?>
    <input type="hidden" name="secret" value="<?= \Hillmeet\Support\e($secret) ?>">
    <div class="form-group">
      <label for="emails">Email addresses (one per line)</label>
      <textarea id="emails" name="emails" class="textarea" rows="4" placeholder="friend@example.com"><?= \Hillmeet\Support\e(implode("\n", array_column($invites, 'email'))) ?></textarea>
    </div>
    <button type="submit" class="btn btn-primary">Send invites</button>
  </form>
</div>

<p style="margin-top:var(--space-5);">
  <a href="<?= \Hillmeet\Support\e($pollUrl) ?>" class="btn btn-primary">Open poll</a>
</p>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
?>
