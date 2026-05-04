<?php
/**
 * account.php
 * Purpose: Me / account hub — Calendar & advanced settings.
 */
$pageTitle = 'Account';
$user = \Hillmeet\Support\current_user();
$content = ob_start();
?>
<div class="max-w-lg mx-auto">
  <h1 class="text-xl font-semibold text-zinc-900 tracking-tight mb-1">Account</h1>
  <?php if ($user !== null): ?>
    <p class="text-sm text-zinc-500 mb-8"><?= \Hillmeet\Support\e((string) ($user->email ?? '')) ?></p>
  <?php endif; ?>

  <section class="space-y-4 mb-8" aria-labelledby="account-services-heading">
    <h2 id="account-services-heading" class="sr-only">Connections</h2>
    <a href="<?= \Hillmeet\Support\url('/calendar') ?>" class="block rounded-xl border border-zinc-200/90 bg-white p-5 shadow-sm shadow-zinc-900/[0.03] hover:border-teal-200 hover:shadow-md transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-500/40 focus-visible:ring-offset-2">
      <span class="text-[15px] font-medium text-zinc-900">Google Calendar</span>
      <span class="block text-sm text-zinc-500 mt-1 leading-snug">Connect calendars for free/busy when voting on polls.</span>
    </a>
  </section>

  <details class="group rounded-xl border border-zinc-200/90 bg-zinc-50/80 overflow-hidden">
    <summary class="cursor-pointer list-none px-5 py-4 text-sm font-medium text-zinc-700 hover:bg-zinc-100/80 transition-colors flex items-center justify-between gap-2 [&::-webkit-details-marker]:hidden">
      <span>Advanced</span>
      <span class="text-zinc-400 text-xs font-normal group-open:rotate-180 transition-transform">▼</span>
    </summary>
    <div class="px-5 pb-5 pt-0 border-t border-zinc-200/60 bg-white">
      <p class="text-xs text-zinc-500 mt-4 mb-3 leading-relaxed">Integrations for technical setups — rotate keys carefully.</p>
      <a href="<?= \Hillmeet\Support\url('/settings/mcp-gateway-key') ?>" class="inline-flex text-sm font-medium text-teal-700 hover:text-teal-800 underline underline-offset-2 decoration-teal-600/30 hover:decoration-teal-700">
        MCP Gateway API keys →
      </a>
    </div>
  </details>

  <p class="mt-10 text-center">
    <a href="<?= \Hillmeet\Support\url('/auth/signout') ?>" class="text-sm text-zinc-500 hover:text-zinc-800 underline underline-offset-2">Sign out</a>
  </p>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
