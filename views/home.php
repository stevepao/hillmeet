<?php
$pageTitle = 'Home';
$content = ob_start();
?>
<h1>Hillmeet</h1>
<div class="home-actions">
  <a href="<?= \Hillmeet\Support\url('/poll/new') ?>" class="btn btn-primary btn-lg">Create poll</a>
</div>

<?php if (!empty($recentPolls)): ?>
  <h2>Your recent polls</h2>
  <ul class="poll-list">
    <?php foreach ($recentPolls as $p): ?>
      <li>
        <a href="<?= \Hillmeet\Support\url('/poll/' . $p->slug . '/edit') ?>"><?= \Hillmeet\Support\e($p->title) ?></a>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/layouts/main.php';
?>
