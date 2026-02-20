<?php
$pageTitle = 'Home';
$content = ob_start();

$ownedPolls = $ownedPolls ?? [];
$participatedPolls = $participatedPolls ?? [];
$debugCounts = $debugCounts ?? null;
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
    <ul class="poll-card-list" style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: var(--space-4);">
      <?php foreach ($ownedPolls as $p): ?>
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
              <a href="<?= \Hillmeet\Support\url('/poll/' . $p->slug . '/edit') ?>" class="btn btn-secondary btn-sm">Edit</a>
            </div>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>

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
