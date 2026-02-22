<?php
/**
 * create_step1.php
 * Purpose: Create poll form (title, timezone, duration).
 * Project: Hillmeet
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 */
$pageTitle = 'Create poll';
$content = ob_start();
$input = $_SESSION['poll_input'] ?? [];
$error = $_SESSION['poll_error'] ?? null;
unset($_SESSION['poll_error'], $_SESSION['poll_input']);
$timezones = timezone_identifiers_list();
?>
<h1>Create poll</h1>
<div class="steps">
  <span class="active">Details</span>
  <span>Add times</span>
  <span>Share</span>
</div>

<?php if ($error): ?>
  <div class="card card-2" style="margin-bottom:var(--space-4);color:var(--danger);"><?= \Hillmeet\Support\e($error) ?></div>
<?php endif; ?>

<form method="post" action="<?= \Hillmeet\Support\url('/poll/create') ?>">
  <?= \Hillmeet\Support\Csrf::field() ?>
  <div class="form-group">
    <label for="title">Poll title</label>
    <input type="text" id="title" name="title" class="input" value="<?= \Hillmeet\Support\e($input['title'] ?? '') ?>" required placeholder="e.g. Team standup">
    <p class="helper">Give it a name your friends will recognize.</p>
  </div>
  <div class="form-group">
    <label for="description">Description (optional)</label>
    <textarea id="description" name="description" class="textarea" rows="2"><?= \Hillmeet\Support\e($input['description'] ?? '') ?></textarea>
  </div>
  <div class="form-group">
    <label for="location">Location (optional)</label>
    <input type="text" id="location" name="location" class="input" value="<?= \Hillmeet\Support\e($input['location'] ?? '') ?>">
  </div>
  <div class="form-group">
    <label for="timezone">Timezone</label>
    <select id="timezone" name="timezone" class="select">
      <?php foreach ($timezones as $tz): ?>
        <option value="<?= \Hillmeet\Support\e($tz) ?>" <?= ($input['timezone'] ?? 'UTC') === $tz ? 'selected' : '' ?>><?= \Hillmeet\Support\e($tz) ?></option>
      <?php endforeach; ?>
    </select>
    <p class="helper">Times are stored in UTC and shown in each person's local timezone. Defaults to your current timezone.</p>
  </div>
  <div class="form-group">
    <label for="duration_minutes">Event duration (minutes)</label>
    <input type="number" id="duration_minutes" name="duration_minutes" class="input" value="<?= \Hillmeet\Support\e($input['duration_minutes'] ?? '60') ?>" min="5" max="1440" step="5" style="width:6rem;">
    <p class="helper">Each time slot will be this long. You'll only choose start times when adding options.</p>
  </div>
  <button type="submit" class="btn btn-primary">Save & continue</button>
</form>
<script>
(function() {
  var select = document.getElementById('timezone');
  if (!select || select.value !== 'UTC') return;
  try {
    var browserTz = Intl.DateTimeFormat().resolvedOptions().timeZone;
    if (browserTz && select.querySelector('option[value="' + browserTz + '"]')) select.value = browserTz;
  } catch (e) {}
})();
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
?>
