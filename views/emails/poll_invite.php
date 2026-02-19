<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>You're invited to vote</title>
</head>
<body style="margin:0;font-family:system-ui,-apple-system,sans-serif;background:#f1f5f9;padding:24px;">
  <div style="max-width:400px;margin:0 auto;background:#fff;border-radius:12px;padding:24px;box-shadow:0 4px 12px rgba(0,0,0,0.08);">
    <h1 style="margin:0 0 16px;font-size:1.25rem;">You're invited to vote</h1>
    <p style="margin:0 0 16px;color:#334155;"><?= \Hillmeet\Support\e($pollTitle ?? '') ?></p>
    <p style="margin:0 0 24px;">
      <a href="<?= \Hillmeet\Support\e($pollUrl ?? '') ?>" style="display:inline-block;padding:12px 20px;background:#7c5cff;color:#fff;text-decoration:none;border-radius:8px;font-weight:500;">Open poll &amp; vote</a>
    </p>
    <p style="margin:0;font-size:0.875rem;color:#94a3b8;">This link is private. Don't share it with people you didn't invite.</p>
  </div>
</body>
</html>
