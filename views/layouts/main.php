<?php
/**
 * main.php
 * Purpose: App shell — light Tailwind theme, minimal header, bottom navigation (mobile-first).
 */
$navRaw = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$navPath = '/' . trim((string) $navRaw, '/');
if ($navPath !== '/') {
    $navPath = rtrim($navPath, '/') ?: '/';
}
$loggedIn = !empty($_SESSION['user']);
$navHome = ($navPath === '/' || $navPath === '');
$navCreate = str_starts_with($navPath, '/poll/new') || str_starts_with($navPath, '/poll/create');
$navMe = str_starts_with($navPath, '/me') || str_starts_with($navPath, '/calendar') || str_starts_with($navPath, '/settings');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="theme-color" content="#f8fafc">
  <meta name="color-scheme" content="light">
  <link rel="manifest" href="<?= \Hillmeet\Support\url('/manifest.json') ?>">
  <link rel="apple-touch-icon" href="<?= \Hillmeet\Support\url('/icons/apple-touch-icon.png') ?>">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-title" content="Hillmeet">
  <meta name="apple-mobile-web-app-status-bar-style" content="default">
  <title><?= isset($pageTitle) ? \Hillmeet\Support\e($pageTitle) . ' — ' : '' ?>Hillmeet</title>
  <?php if (!empty($canonicalUrl)): ?>
  <link rel="canonical" href="<?= \Hillmeet\Support\e($canonicalUrl) ?>">
  <?php endif; ?>
  <link rel="stylesheet" href="<?= \Hillmeet\Support\url('/assets/css/tokens.css') ?>">
  <link rel="stylesheet" href="<?= \Hillmeet\Support\url('/assets/css/base.css') ?>">
  <link rel="stylesheet" href="<?= \Hillmeet\Support\url('/assets/css/components.css') ?>">
  <link rel="stylesheet" href="<?= \Hillmeet\Support\url('/assets/css/app.css') ?>">
  <link rel="stylesheet" href="<?= \Hillmeet\Support\url('/assets/css/tailwind.css') ?>">
  <?= $extraHead ?? '' ?>
</head>
<body class="min-h-dvh bg-slate-50 text-zinc-900 antialiased pb-[calc(var(--bottom-nav-height)+var(--safe-bottom))]">

  <header class="sticky top-0 z-40 border-b border-zinc-200/80 bg-white/90 backdrop-blur-md supports-[backdrop-filter]:bg-white/75">
    <div class="mx-auto flex max-w-lg items-center justify-between gap-3 px-4 py-3">
      <a href="<?= \Hillmeet\Support\url('/') ?>" class="text-[17px] font-semibold tracking-tight text-zinc-900 hover:text-teal-800 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-500/35 focus-visible:ring-offset-2 rounded-md">
        Hillmeet
      </a>
      <?php if ($loggedIn): ?>
        <?php $em = (string) ($_SESSION['user']->email ?? ''); ?>
        <span class="hidden sm:inline-block max-w-[50%] truncate text-xs text-zinc-500 text-right" title="<?= \Hillmeet\Support\e($em) ?>"><?= \Hillmeet\Support\e($em) ?></span>
      <?php endif; ?>
    </div>
  </header>

  <main class="main mx-auto max-w-lg px-4 py-6">
    <?= $content ?? '' ?>
  </main>

  <nav class="fixed bottom-0 left-0 right-0 z-50 border-t border-zinc-200/90 bg-white/95 backdrop-blur-md pb-[var(--safe-bottom)] shadow-[0_-4px_24px_-8px_rgba(15,23,42,0.08)]" aria-label="Primary">
    <div class="mx-auto flex max-w-lg justify-around safe-area-pb">
      <?php if ($loggedIn): ?>
        <a href="<?= \Hillmeet\Support\url('/') ?>" class="flex flex-1 flex-col items-center gap-1 py-3 text-[11px] font-medium <?= $navHome ? 'text-teal-700' : 'text-zinc-500 hover:text-zinc-800' ?> transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-teal-500/35">
          <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
          Home
        </a>
        <a href="<?= \Hillmeet\Support\url('/poll/new') ?>" class="flex flex-1 flex-col items-center gap-1 py-3 text-[11px] font-medium <?= $navCreate ? 'text-teal-700' : 'text-zinc-500 hover:text-zinc-800' ?> transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-teal-500/35">
          <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
          Create poll
        </a>
        <a href="<?= \Hillmeet\Support\url('/me') ?>" class="flex flex-1 flex-col items-center gap-1 py-3 text-[11px] font-medium <?= $navMe ? 'text-teal-700' : 'text-zinc-500 hover:text-zinc-800' ?> transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-teal-500/35">
          <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
          Me
        </a>
      <?php else: ?>
        <a href="<?= \Hillmeet\Support\url('/') ?>" class="flex flex-1 flex-col items-center gap-1 py-3 text-[11px] font-medium <?= $navHome ? 'text-teal-700' : 'text-zinc-500 hover:text-zinc-800' ?> transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-teal-500/35">
          <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
          Home
        </a>
        <a href="<?= \Hillmeet\Support\url('/auth/email') ?>" class="flex flex-1 flex-col items-center gap-1 py-3 text-[11px] font-medium text-zinc-500 hover:text-zinc-800 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-teal-500/35">
          <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
          Sign in
        </a>
      <?php endif; ?>
    </div>
  </nav>

  <script src="<?= \Hillmeet\Support\url('/assets/js/app.js') ?>"></script>
  <script src="<?= \Hillmeet\Support\url('/assets/js/progressive.js') ?>"></script>
  <script>
  if ("serviceWorker" in navigator) {
    navigator.serviceWorker.register("<?= \Hillmeet\Support\url('/service-worker.js') ?>");
  }
  </script>
  <?= $extraScripts ?? '' ?>
</body>
</html>
