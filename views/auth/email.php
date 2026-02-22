<?php
/**
 * email.php
 * Purpose: Email sign-in form (request PIN).
 * Project: Hillmeet
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 */
$pageTitle = 'Sign in with email';
$content = ob_start();
$email = $_SESSION['auth_email'] ?? '';
?>
<div class="auth-page">
  <h1>Sign in with email</h1>
  <p class="helper">We'll email you a one-time PIN. No spam.</p>

  <?php if (!empty($_SESSION['auth_error'])): ?>
    <div class="card card-2" style="margin-top:var(--space-4);color:var(--danger);">
      <?= \Hillmeet\Support\e($_SESSION['auth_error']) ?>
    </div>
  <?php endif; ?>

  <div class="card" style="margin-top:var(--space-5);">
    <form method="post" action="<?= \Hillmeet\Support\url('/auth/send-pin') ?>">
      <?= \Hillmeet\Support\Csrf::field() ?>
      <div class="form-group">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" class="input" value="<?= \Hillmeet\Support\e($email) ?>" required autocomplete="email">
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;">Send PIN</button>
    </form>
  </div>
  <p style="margin-top:var(--space-4);"><a href="<?= \Hillmeet\Support\url('/auth/login') ?>">← Back to sign in</a></p>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
?>
