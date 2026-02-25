<?php
/**
 * login.php
 * Purpose: Sign-in page (Google and email PIN).
 * Project: Hillmeet
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 */
$pageTitle = 'Sign in';
$content = ob_start();
$googleClientId = $googleClientId ?? '';
$isLocal = (function_exists('env') ? env('APP_ENV', '') : '') === 'local';
?>
<div class="auth-page">
  <section class="auth-tagline-wrap" aria-label="About Hillmeet">
    <p class="auth-tagline">Hillmeet makes it effortless to find a time that works for everyone and send meeting invitations automatically.</p>
  </section>
  <h1>Sign in</h1>
  <p class="muted">Sign in to create and vote on polls. See our <a href="<?= \Hillmeet\Support\url('/privacy') ?>">Privacy Policy</a> and <a href="<?= \Hillmeet\Support\url('/terms') ?>">Terms of Service</a>.</p>

  <?php if (!empty($_SESSION['auth_error'])): ?>
    <div class="card card-2" style="margin-top:var(--space-4);color:var(--danger);">
      <?= \Hillmeet\Support\e($_SESSION['auth_error']) ?>
    </div>
    <?php unset($_SESSION['auth_error']); ?>
  <?php endif; ?>

  <?php if ($isLocal): ?>
  <div class="card card-2" style="margin-top:var(--space-4); font-size:0.85rem;">
    <p style="margin:0 0 var(--space-2); font-weight:600;">Diagnostics (APP_ENV=local)</p>
    <ul style="margin:0; padding-left:1.25rem;">
      <li>Google sign-in: <?= ($googleClientId !== '' && \Hillmeet\Support\config('google.client_secret', '') !== '') ? 'configured' : 'missing GOOGLE_CLIENT_ID or GOOGLE_CLIENT_SECRET' ?></li>
    </ul>
  </div>
  <?php endif; ?>

  <div class="card" style="margin-top:var(--space-5);">
    <p class="muted" style="margin:0 0 var(--space-3); font-weight:500;">Sign in with Google</p>
    <?php if ($googleClientId === '' || \Hillmeet\Support\config('google.client_secret', '') === ''): ?>
      <p class="helper" style="margin:0;">Not configured. Set <code>GOOGLE_CLIENT_ID</code> and <code>GOOGLE_CLIENT_SECRET</code> in .env and add your redirect URI in Google Cloud Console.</p>
    <?php else: ?>
      <a href="<?= \Hillmeet\Support\e(\Hillmeet\Support\url('/auth/google')) ?>" class="btn btn-primary" style="width:100%; display:inline-flex; align-items:center; justify-content:center; gap:0.5rem;">
        <svg width="18" height="18" viewBox="0 0 18 18" xmlns="http://www.w3.org/2000/svg"><path fill="#4285F4" d="M17.64 9.2c0-.637-.057-1.251-.164-1.84H9v3.481h4.844c-.209 1.125-.843 2.078-1.796 2.717v2.258h2.908c1.702-1.567 2.684-3.874 2.684-6.615z"/><path fill="#34A853" d="M9 18c2.43 0 4.467-.806 6.168-2.18l-2.908-2.258c-.806.54-1.837.86-3.26.86-2.513 0-4.646-1.697-5.41-4.04H.957v2.332C2.438 15.983 5.482 18 9 18z"/><path fill="#FBBC05" d="M3.59 10.382c-.18-.54-.282-1.117-.282-1.695 0-.578.102-1.155.282-1.694V4.965H.957C.347 6.315 0 7.69 0 9.087c0 1.398.348 2.773.957 4.123l2.632-2.046z"/><path fill="#EA4335" d="M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 0 5.482 0 2.438 2.017.957 4.965L3.59 7.307C4.354 4.964 6.487 3.267 9 3.58z"/></svg>
        Continue with Google
      </a>
    <?php endif; ?>
    <div class="auth-divider">or</div>
    <a href="<?= \Hillmeet\Support\url('/auth/email') ?>" class="btn btn-secondary" style="width:100%;">Use email instead</a>
  </div>
  <p class="auth-footer">© 2026 Hillwork, LLC. All rights reserved.</p>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
?>
