<?php
/**
 * edit.php
 * Purpose: Edit poll details (title, description, location).
 * Project: Hillmeet
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 */
$pageTitle = 'Edit poll';
$content = ob_start();
?>
<h1><?= \Hillmeet\Support\e($poll->title) ?></h1>
<div class="steps">
  <span class="done">Details</span>
  <a href="<?= \Hillmeet\Support\url('/poll/' . $poll->slug . '/options') ?>">Add times</a>
  <a href="<?= \Hillmeet\Support\url('/poll/' . $poll->slug . '/share') ?>">Share</a>
</div>
<p><a href="<?= \Hillmeet\Support\url('/poll/' . $poll->slug . '/options') ?>" class="btn btn-primary">Edit options</a></p>
<p><a href="<?= \Hillmeet\Support\url('/poll/' . $poll->slug . '/share') ?>" class="btn btn-secondary">Invite people</a></p>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
?>
