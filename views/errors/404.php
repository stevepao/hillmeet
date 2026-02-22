<?php
/**
 * 404.php
 * Purpose: Not found error page.
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
  <title>Not found — Hillmeet</title>
  <link rel="stylesheet" href="<?= \Hillmeet\Support\url('/assets/css/tokens.css') ?>">
  <link rel="stylesheet" href="<?= \Hillmeet\Support\url('/assets/css/base.css') ?>">
  <link rel="stylesheet" href="<?= \Hillmeet\Support\url('/assets/css/components.css') ?>">
  <link rel="stylesheet" href="<?= \Hillmeet\Support\url('/assets/css/app.css') ?>">
</head>
<body>
  <div class="error-page">
    <h1>404</h1>
    <p><?= isset($pageMessage) ? \Hillmeet\Support\e($pageMessage) : 'This page or poll could not be found.' ?></p>
    <a href="<?= \Hillmeet\Support\url('/') ?>" class="btn btn-primary">Go home</a>
  </div>
</body>
</html>
