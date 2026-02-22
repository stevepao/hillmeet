<?php
/**
 * pin.php
 * Purpose: Email template for one-time sign-in PIN.
 * Project: Hillmeet
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 */
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Your sign-in PIN</title>
</head>
<body style="margin:0;font-family:system-ui,-apple-system,sans-serif;background:#f1f5f9;padding:24px;">
  <div style="max-width:400px;margin:0 auto;background:#fff;border-radius:12px;padding:24px;box-shadow:0 4px 12px rgba(0,0,0,0.08);">
    <h1 style="margin:0 0 16px;font-size:1.25rem;">Hillmeet sign-in</h1>
    <p style="margin:0 0 16px;color:#64748b;">Your one-time PIN is:</p>
    <p style="margin:0 0 24px;font-size:1.5rem;font-weight:600;letter-spacing:4px;font-family:ui-monospace,monospace;"><?= \Hillmeet\Support\e($pin ?? '') ?></p>
    <p style="margin:0;font-size:0.875rem;color:#94a3b8;">PIN expires in <?= (int)($expiry_minutes ?? 10) ?> minutes.</p>
  </div>
</body>
</html>
