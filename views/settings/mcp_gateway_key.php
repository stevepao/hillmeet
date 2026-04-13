<?php
/**
 * mcp_gateway_key.php
 * Purpose: MCP Gateway API key generation (key displayed once, never stored server-side).
 * Project: Hillmeet
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 */

$pageTitle = 'MCP Gateway API Key';

// Load the 1Password Save Button web component (ESM, via jsDelivr) only when a
// key is present on this response. type="module" scripts are deferred by default
// so the element upgrades retroactively once the definition arrives.
if (!empty($apiKey)) {
    $extraScripts = '<script type="module" src="https://cdn.jsdelivr.net/npm/@1password/save-button/+esm"></script>';
}

$content = ob_start();
?>
<h1>MCP Gateway API Key</h1>

<p class="muted" style="margin-bottom:var(--space-4);">
  Generate a Bearer token for authenticating requests to the Hillmeet MCP Gateway.
  The key is displayed <strong>once</strong> and is never stored by Hillmeet — save it immediately.
</p>

<?php if (!empty($error)): ?>
  <p class="error-message" role="alert" style="margin-bottom:var(--space-4);">
    <?= \Hillmeet\Support\e($error) ?>
  </p>
<?php endif; ?>

<?php if (!empty($apiKey)): ?>
  <!-- ===== Key display (shown once) ===== -->
  <div class="card" style="margin-bottom:var(--space-5);">
    <p class="success-message" role="status" style="margin-bottom:var(--space-3);">
      API key generated. Copy or save it now — it will not be shown again after you leave this page.
    </p>

    <div class="form-group" style="margin-bottom:var(--space-3);">
      <label for="mcp-api-key-field">Your new API key</label>
      <div style="display:flex; gap:var(--space-2); align-items:center; flex-wrap:wrap;">
        <input
          id="mcp-api-key-field"
          class="input"
          type="password"
          value="<?= \Hillmeet\Support\e($apiKey) ?>"
          readonly
          autocomplete="off"
          spellcheck="false"
          style="font-family:var(--font-mono); letter-spacing:0.05em; flex:1 1 300px;"
          aria-label="MCP Gateway API key (masked)"
        >
        <button
          type="button"
          id="mcp-reveal-btn"
          class="btn btn-secondary"
          aria-controls="mcp-api-key-field"
          aria-pressed="false"
        >Reveal</button>
      </div>
    </div>

    <div style="display:flex; gap:var(--space-2); flex-wrap:wrap; align-items:center;">
      <button type="button" id="mcp-copy-btn" class="btn btn-primary">Copy to clipboard</button>

      <?php if (!empty($onePasswordValue)): ?>
        <!-- The web component is invisible until the 1Password extension activates it.
             #op-save-fallback is shown by default and hidden by JS once the button
             has rendered with non-zero size (i.e. extension IS present). -->
        <onepassword-save-button
          id="op-save-btn"
          value="<?= \Hillmeet\Support\e($onePasswordValue) ?>"
        ></onepassword-save-button>
        <span id="op-save-fallback" class="muted" style="font-size:var(--text-sm);">
          <a
            href="https://1password.com/downloads/browser-extension"
            target="_blank"
            rel="noopener noreferrer"
            style="color:var(--muted);"
          >Install the 1Password browser extension</a> to save this key.
        </span>
      <?php endif; ?>
    </div>

    <p
      id="mcp-copy-status"
      role="status"
      aria-live="polite"
      style="margin-top:var(--space-2); font-size:var(--text-sm);"
      hidden
    ></p>
  </div>

  <script>
  (function () {
    'use strict';

    // ---- Reveal / hide toggle ----
    var revealBtn = document.getElementById('mcp-reveal-btn');
    var keyField  = document.getElementById('mcp-api-key-field');
    if (revealBtn && keyField) {
      revealBtn.addEventListener('click', function () {
        var hidden = keyField.type === 'password';
        keyField.type = hidden ? 'text' : 'password';
        revealBtn.textContent = hidden ? 'Hide' : 'Reveal';
        revealBtn.setAttribute('aria-pressed', hidden ? 'true' : 'false');
      });
    }

    // ---- Copy to clipboard ----
    var copyBtn    = document.getElementById('mcp-copy-btn');
    var copyStatus = document.getElementById('mcp-copy-status');

    function showStatus(msg, isError) {
      if (!copyStatus) { return; }
      copyStatus.textContent = msg;
      copyStatus.style.color = isError
        ? 'var(--danger, #ef4444)'
        : 'var(--accent-2, #22c55e)';
      copyStatus.hidden = false;
      setTimeout(function () { copyStatus.hidden = true; }, 3500);
    }

    function fallbackCopy(text) {
      var ta = document.createElement('textarea');
      ta.value = text;
      ta.style.cssText = 'position:fixed;top:-9999px;left:-9999px;opacity:0;';
      document.body.appendChild(ta);
      ta.focus();
      ta.select();
      var ok = false;
      try { ok = document.execCommand('copy'); } catch (e) { /* ignore */ }
      document.body.removeChild(ta);
      if (ok) {
        showStatus('Copied!', false);
      } else {
        showStatus('Copy failed — please select the key and copy manually.', true);
      }
    }

    if (copyBtn && keyField) {
      copyBtn.addEventListener('click', function () {
        var key = keyField.value;
        if (navigator.clipboard && window.isSecureContext) {
          navigator.clipboard.writeText(key).then(
            function ()  { showStatus('Copied!', false); },
            function ()  { fallbackCopy(key); }
          );
        } else {
          fallbackCopy(key);
        }
      });
    }

    // ---- 1Password Save Button visibility / fallback ----
    // The <onepassword-save-button> component renders as an invisible element until
    // the 1Password browser extension activates it. We poll for a rendered size; once
    // the button has non-zero dimensions the extension is present and we hide the
    // "install extension" fallback text. If it never becomes visible we leave the
    // fallback so the user sees a helpful prompt rather than nothing.
    var opBtn      = document.getElementById('op-save-btn');
    var opFallback = document.getElementById('op-save-fallback');

    if (opBtn && opFallback) {
      // Notify 1Password (for cases where it needs an explicit nudge, e.g. SPAs).
      if (typeof window.activateOPButton === 'function') {
        window.activateOPButton();
      }

      // Poll up to ~3 s in increasing intervals.
      var opChecks = [300, 800, 1500, 3000];
      function checkOpButton() {
        var rect = opBtn.getBoundingClientRect();
        if (rect.width > 0 && rect.height > 0) {
          opFallback.hidden = true;   // extension present — button rendered
          return;
        }
        var delay = opChecks.shift();
        if (delay !== undefined) {
          setTimeout(checkOpButton, delay);
        }
        // If all checks exhausted and still not visible, fallback remains shown.
      }
      // Wait until the ESM module (deferred) has had a chance to run.
      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { setTimeout(checkOpButton, 300); });
      } else {
        setTimeout(checkOpButton, 300);
      }
    }
  }());
  </script>
<?php endif; ?>

<!-- ===== Generate form ===== -->
<div class="card">
  <h2 style="margin-top:0; margin-bottom:var(--space-3);">Generate a new key</h2>

  <div style="padding:var(--space-3); background:rgba(245,158,11,0.12); border-left:4px solid var(--warn); border-radius:var(--radius-sm); margin-bottom:var(--space-4);">
    <strong>One-time display.</strong>
    The key will appear on screen once and is <em>never</em> stored by Hillmeet.
    Save it to 1Password or another secret manager immediately after generating.
    Generating a new key does <em>not</em> revoke existing keys.
  </div>

  <form method="post" action="<?= \Hillmeet\Support\url('/settings/mcp-gateway-key') ?>">
    <?= \Hillmeet\Support\Csrf::field() ?>

    <div class="form-group">
      <label for="mcp-owner-email">Owner email</label>
      <?php if ($isAdmin): ?>
        <input
          type="email"
          id="mcp-owner-email"
          name="owner_email"
          value="<?= \Hillmeet\Support\e($ownerEmail) ?>"
          class="input"
          required
          autocomplete="email"
        >
        <p class="helper">Admin: you may specify any registered user's email.</p>
      <?php else: ?>
        <input
          type="email"
          id="mcp-owner-email"
          value="<?= \Hillmeet\Support\e($ownerEmail) ?>"
          class="input"
          readonly
          aria-readonly="true"
        >
        <input type="hidden" name="owner_email" value="<?= \Hillmeet\Support\e($ownerEmail) ?>">
        <p class="helper">The key will be scoped to your account.</p>
      <?php endif; ?>
    </div>

    <button type="submit" class="btn btn-primary">Generate new API key</button>
  </form>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
?>
