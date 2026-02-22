<?php
/**
 * poll_locked.php
 * Purpose: Email template for poll locked / final time notification.
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
  <title>Meeting time finalized</title>
</head>
<body style="margin:0;font-family:system-ui,-apple-system,sans-serif;background:#f1f5f9;padding:24px;">
  <div style="max-width:400px;margin:0 auto;background:#fff;border-radius:12px;padding:24px;box-shadow:0 4px 12px rgba(0,0,0,0.08);">
    <h1 style="margin:0 0 16px;font-size:1.25rem;">Meeting time finalized</h1>
    <p style="margin:0 0 8px;color:#334155;"><strong><?= \Hillmeet\Support\e($pollTitle ?? '') ?></strong></p>
    <p style="margin:0 0 4px;color:#334155;">Final time: <?= \Hillmeet\Support\e($finalTimeLocalized ?? '') ?></p>
    <?php if (!empty($timezoneCallout)): ?>
    <p style="margin:0 0 16px;color:#64748b;font-size:0.875rem;"><?= \Hillmeet\Support\e($timezoneCallout) ?></p>
    <?php endif; ?>
    <p style="margin:0 0 16px;color:#64748b;font-size:0.875rem;">Organizer: <?= \Hillmeet\Support\e($organizerName ?? '') ?> (<?= \Hillmeet\Support\e($organizerEmail ?? '') ?>)</p>
    <?php if (!empty($pollUrl)): ?>
    <p style="margin:0 0 24px;">
      <a href="<?= \Hillmeet\Support\e($pollUrl) ?>" style="display:inline-block;padding:12px 20px;background:#7c5cff;color:#fff;text-decoration:none;border-radius:8px;font-weight:500;">View poll</a>
    </p>
    <?php endif; ?>
    <?php if (!empty($hasIcs)): ?>
    <p style="margin:0;font-size:0.875rem;color:#94a3b8;">A calendar file is attached. Add it to your calendar to save the event.</p>
    <?php endif; ?>
  </div>
</body>
</html>
