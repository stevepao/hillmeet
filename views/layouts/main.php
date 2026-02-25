<?php
/**
 * main.php
 * Purpose: Main layout (header, nav, content, scripts).
 * Project: Hillmeet
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= isset($pageTitle) ? \Hillmeet\Support\e($pageTitle) . ' — ' : '' ?>Hillmeet</title>
  <link rel="stylesheet" href="<?= \Hillmeet\Support\url('/assets/css/tokens.css') ?>">
  <link rel="stylesheet" href="<?= \Hillmeet\Support\url('/assets/css/base.css') ?>">
  <link rel="stylesheet" href="<?= \Hillmeet\Support\url('/assets/css/components.css') ?>">
  <link rel="stylesheet" href="<?= \Hillmeet\Support\url('/assets/css/app.css') ?>">
  <?= $extraHead ?? '' ?>
</head>
<body>
  <header class="app-header">
    <div class="container">
      <a href="<?= \Hillmeet\Support\url('/') ?>" class="app-logo" aria-label="Hillmeet home">
        <img src="<?= \Hillmeet\Support\url('/assets/hillmeet-cropped.png') ?>" alt="" width="280" height="64" class="app-logo-img">
      </a>
      <nav class="app-nav">
        <?php if (!empty($_SESSION['user'])): ?>
          <a href="<?= \Hillmeet\Support\url('/') ?>">Home</a>
          <a href="<?= \Hillmeet\Support\url('/poll/new') ?>">Create poll</a>
          <a href="<?= \Hillmeet\Support\url('/calendar') ?>">Calendar</a>
          <a href="<?= \Hillmeet\Support\url('/auth/signout') ?>">Sign out</a>
        <?php else: ?>
          <a href="<?= \Hillmeet\Support\url('/auth/login') ?>">Sign in</a>
        <?php endif; ?>
      </nav>
    </div>
  </header>
  <main class="main">
    <div class="container">
      <?= $content ?? '' ?>
    </div>
  </main>
  <script src="<?= \Hillmeet\Support\url('/assets/js/app.js') ?>"></script>
  <script src="<?= \Hillmeet\Support\url('/assets/js/progressive.js') ?>"></script>
  <?= $extraScripts ?? '' ?>
</body>
</html>
