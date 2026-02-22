<?php
/**
 * home.php
 * Purpose: Home page view (owned and participated polls).
 * Project: Hillmeet
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 */
$pageTitle = 'Home';
$content = ob_start();

$ownedPolls = $ownedPolls ?? [];
$participatedPolls = $participatedPolls ?? [];
$debugCounts = $debugCounts ?? null;
$csrfToken = \Hillmeet\Support\Csrf::token();
?>
<h1>Hillmeet</h1>
<div class="home-actions" style="margin-bottom: var(--space-6);">
  <a href="<?= \Hillmeet\Support\url('/poll/new') ?>" class="btn btn-primary btn-lg">Create poll</a>
</div>

<?php if ($debugCounts !== null): ?>
<p class="muted" style="font-size: var(--text-sm); margin-bottom: var(--space-4);">
  <small>owned polls: <?= (int) $debugCounts['owned'] ?>, participated polls: <?= (int) $debugCounts['participated'] ?></small>
</p>
<?php endif; ?>

<section class="home-section" aria-labelledby="your-polls-heading">
  <h2 id="your-polls-heading">Your polls</h2>
  <?php if (empty($ownedPolls)): ?>
    <p class="muted">No polls yet. Create one to get started.</p>
  <?php else: ?>
    <ul class="poll-card-list" id="your-polls-list" style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: var(--space-4);">
      <?php foreach ($ownedPolls as $p): ?>
        <li class="card poll-card-owned" data-poll-slug="<?= \Hillmeet\Support\e($p->slug) ?>">
          <div style="display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: var(--space-3);">
            <div style="flex: 1; min-width: 0;">
              <span class="badge <?= $p->isLocked() ? 'badge-muted' : 'badge-success' ?>"><?= $p->isLocked() ? 'Locked' : 'Open' ?></span>
              <h3 style="margin: var(--space-2) 0 var(--space-1); font-size: var(--text-lg);">
                <a href="<?= \Hillmeet\Support\url('/poll/' . $p->slug) ?>"><?= \Hillmeet\Support\e($p->title) ?></a>
              </h3>
              <?php if ($p->description !== null && $p->description !== ''): ?>
                <p class="muted" style="font-size: var(--text-sm); margin: 0 0 var(--space-2); overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;"><?= \Hillmeet\Support\e($p->description) ?></p>
              <?php endif; ?>
              <p class="muted" style="font-size: var(--text-xs); margin: 0;">Updated <?= \Hillmeet\Support\e($p->updated_at) ?></p>
            </div>
            <div style="display: flex; gap: var(--space-2); flex-shrink: 0;">
              <a href="<?= \Hillmeet\Support\url('/poll/' . $p->slug) ?>" class="btn btn-secondary btn-sm">View</a>
              <a href="<?= \Hillmeet\Support\url('/poll/' . $p->slug . '/edit') ?>" class="btn btn-secondary btn-sm">Edit</a>
              <button type="button" class="btn btn-secondary btn-sm poll-delete-btn" data-poll-slug="<?= \Hillmeet\Support\e($p->slug) ?>" data-delete-url="<?= \Hillmeet\Support\e(\Hillmeet\Support\url('/poll/' . $p->slug . '/delete')) ?>" aria-label="Delete poll">Delete</button>
            </div>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
    <div id="confirm-delete-poll-modal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="confirm-delete-poll-title" hidden data-csrf="<?= \Hillmeet\Support\e($csrfToken) ?>">
      <div class="card" style="max-width: 28rem;">
        <h2 id="confirm-delete-poll-title">Delete poll?</h2>
        <p class="helper">Delete this poll? This removes all time options and all votes. This cannot be undone.</p>
        <div style="display: flex; gap: var(--space-2); justify-content: flex-end; margin-top: var(--space-4);">
          <button type="button" class="btn btn-secondary" id="confirm-delete-poll-cancel">Cancel</button>
          <button type="button" class="btn btn-primary" id="confirm-delete-poll-confirm" style="background: var(--danger); border-color: var(--danger);">Delete</button>
        </div>
      </div>
    </div>
  <?php endif; ?>
</section>

<script>
(function() {
  var modal = document.getElementById('confirm-delete-poll-modal');
  if (!modal) return;
  var csrfToken = modal.getAttribute('data-csrf') || '';
  var deleteUrl = null;
  var cardToRemove = null;

  function showModal() {
    modal.hidden = false;
    modal.style.display = 'flex';
  }
  function hideModal() {
    modal.hidden = true;
    modal.style.display = 'none';
    deleteUrl = null;
    cardToRemove = null;
  }

  document.querySelectorAll('.poll-delete-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      deleteUrl = btn.getAttribute('data-delete-url');
      cardToRemove = btn.closest('.poll-card-owned');
      if (deleteUrl && cardToRemove) showModal();
    });
  });

  var cancelBtn = document.getElementById('confirm-delete-poll-cancel');
  if (cancelBtn) cancelBtn.addEventListener('click', hideModal);

  var confirmBtn = document.getElementById('confirm-delete-poll-confirm');
  if (confirmBtn) {
    confirmBtn.addEventListener('click', function() {
      if (!deleteUrl || !cardToRemove) { hideModal(); return; }
      var formData = new FormData();
      formData.append('csrf_token', csrfToken);
      confirmBtn.disabled = true;
      fetch(deleteUrl, { method: 'POST', body: formData, credentials: 'same-origin' })
        .then(function(r) { return r.text().then(function(text) { return { ok: r.ok, status: r.status, text: text }; }); })
        .then(function(result) {
          confirmBtn.disabled = false;
          hideModal();
          var body = null;
          try { body = result.text ? JSON.parse(result.text) : null; } catch (e) { body = null; }
          if (result.ok && result.status === 200 && body && body.success) {
            cardToRemove.remove();
            return;
          }
          if (result.ok && result.status === 200 && !body) {
            cardToRemove.remove();
            window.location.reload();
            return;
          }
          if (body && body.error) {
            var msg = body.error;
            if (body.error_code) msg += ' (' + body.error_code + ')';
            alert(msg);
          } else {
            alert('Could not delete poll.');
          }
          window.location.reload();
        })
        .catch(function() {
          confirmBtn.disabled = false;
          hideModal();
          window.location.reload();
        });
    });
  }

  modal.addEventListener('click', function(e) {
    if (e.target === modal) hideModal();
  });
})();
</script>

<section class="home-section" aria-labelledby="participated-heading" style="margin-top: var(--space-8);">
  <h2 id="participated-heading">Polls you're in</h2>
  <?php if (empty($participatedPolls)): ?>
    <p class="muted">No invitations or participated polls yet.</p>
  <?php else: ?>
    <ul class="poll-card-list" style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: var(--space-4);">
      <?php foreach ($participatedPolls as $p): ?>
        <li class="card">
          <div style="display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: var(--space-3);">
            <div style="flex: 1; min-width: 0;">
              <span class="badge <?= $p->isLocked() ? 'badge-muted' : 'badge-success' ?>"><?= $p->isLocked() ? 'Locked' : 'Open' ?></span>
              <h3 style="margin: var(--space-2) 0 var(--space-1); font-size: var(--text-lg);">
                <a href="<?= \Hillmeet\Support\url('/poll/' . $p->slug) ?>"><?= \Hillmeet\Support\e($p->title) ?></a>
              </h3>
              <?php if ($p->description !== null && $p->description !== ''): ?>
                <p class="muted" style="font-size: var(--text-sm); margin: 0 0 var(--space-2); overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;"><?= \Hillmeet\Support\e($p->description) ?></p>
              <?php endif; ?>
              <p class="muted" style="font-size: var(--text-xs); margin: 0;">Updated <?= \Hillmeet\Support\e($p->updated_at) ?></p>
            </div>
            <div style="display: flex; gap: var(--space-2); flex-shrink: 0;">
              <a href="<?= \Hillmeet\Support\url('/poll/' . $p->slug) ?>" class="btn btn-secondary btn-sm">View</a>
              <?php if ($p->isOrganizer((int) ($_SESSION['user']->id ?? 0))): ?>
                <a href="<?= \Hillmeet\Support\url('/poll/' . $p->slug . '/edit') ?>" class="btn btn-secondary btn-sm">Edit</a>
              <?php endif; ?>
            </div>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/layouts/main.php';
?>
