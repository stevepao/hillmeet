<?php
/**
 * verify.php
 * Purpose: PIN verification form.
 * Project: Hillmeet
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 */
$pageTitle = 'Verify PIN';
$canonicalUrl = \Hillmeet\Support\url('/auth/verify');
$content = ob_start();
$email = $_GET['email'] ?? $_SESSION['pin_sent_to'] ?? '';
?>
<div class="auth-page">
  <h1>Check your email</h1>
  <p class="helper">PIN expires in 10 minutes.</p>

  <?php if (!empty($_SESSION['auth_error'])): ?>
    <div class="card card-2" style="margin-top:var(--space-4);color:var(--danger);">
      <?= \Hillmeet\Support\e($_SESSION['auth_error']) ?>
    </div>
  <?php endif; ?>

  <div class="card" style="margin-top:var(--space-5);">
    <form method="post" action="<?= \Hillmeet\Support\url('/auth/verify-pin') ?>">
      <?= \Hillmeet\Support\Csrf::field() ?>
      <input type="hidden" name="email" value="<?= \Hillmeet\Support\e($email) ?>">
      <div class="form-group">
        <label for="pin">PIN</label>
        <input type="text" id="pin" name="pin" class="input" inputmode="numeric" pattern="[0-9]*" maxlength="6" placeholder="000000" required autocomplete="one-time-code">
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;">Verify & sign in</button>
    </form>
    <p style="margin-top:var(--space-3);font-size:var(--text-sm);">
      <a href="<?= \Hillmeet\Support\url('/auth/email') ?>">Change email</a> · <a href="<?= \Hillmeet\Support\url('/auth/email') ?>">Resend PIN</a>
    </p>
  </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
?>
