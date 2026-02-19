<?php
$pageTitle = 'Calendar settings';
$content = ob_start();
?>
<h1>Calendar settings</h1>

<?php if (!$connected): ?>
  <div class="card">
    <p>Connect your Google Calendar to check free/busy when voting.</p>
    <a href="<?= \Hillmeet\Support\e($authUrl) ?>" class="btn btn-primary">Connect Google Calendar</a>
  </div>
<?php else: ?>
  <p class="muted">Connected. Choose which calendars to use for availability.</p>
  <form method="post" action="<?= \Hillmeet\Support\url('/calendar/save') ?>">
    <?= \Hillmeet\Support\Csrf::field() ?>
    <div class="form-group">
      <label class="checkbox-label">
        <input type="checkbox" name="tentative_as_busy" value="1" <?= ($calendars[0]['tentative_as_busy'] ?? true) ? 'checked' : '' ?>> Treat tentative events as busy
      </label>
    </div>
    <ul class="calendar-list">
      <?php foreach ($calendars as $cal): ?>
        <li>
          <label class="checkbox-label">
            <input type="checkbox" name="calendars[<?= \Hillmeet\Support\e($cal['id']) ?>][selected]" value="1" <?= $cal['selected'] ? 'checked' : '' ?>>
            <input type="hidden" name="calendars[<?= \Hillmeet\Support\e($cal['id']) ?>][id]" value="<?= \Hillmeet\Support\e($cal['id']) ?>">
            <input type="hidden" name="calendars[<?= \Hillmeet\Support\e($cal['id']) ?>][summary]" value="<?= \Hillmeet\Support\e($cal['summary']) ?>">
            <?= \Hillmeet\Support\e($cal['summary']) ?>
          </label>
        </li>
      <?php endforeach; ?>
    </ul>
    <button type="submit" class="btn btn-primary">Save</button>
  </form>
  <p class="helper" style="margin-top:var(--space-4);">Free/busy cache TTL: <?= (int)$cacheTtl ?> seconds.</p>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
?>
