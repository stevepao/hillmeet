<?php
$pageTitle = 'Sign in';
$content = ob_start();
$showDebug = !empty($_GET['debug']);
?>
<div class="auth-page">
  <h1>Sign in</h1>
  <p class="muted">Sign in to create and vote on polls.</p>

  <?php if (!empty($_SESSION['auth_error'])): ?>
    <div class="card card-2" style="margin-top:var(--space-4);color:var(--danger);">
      <?= \Hillmeet\Support\e($_SESSION['auth_error']) ?>
    </div>
    <?php unset($_SESSION['auth_error']); ?>
  <?php endif; ?>

  <?php if ($showDebug): ?>
  <?php
  $debugOrigin = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '');
  ?>
  <div class="card card-2" style="margin-top:var(--space-4); font-size:0.9rem;">
    <p style="margin:0 0 var(--space-2); font-weight:600;">Sign-in debug</p>
    <ul style="margin:0; padding-left:1.25rem;">
      <li><code>GOOGLE_CLIENT_ID</code> set: <?= !empty($googleClientId) ? 'yes' : 'no' ?></li>
      <li>Current origin: <code><?= \Hillmeet\Support\e($debugOrigin) ?></code> — add this to Authorized JavaScript origins in Google Cloud Console if the GSI script fails to load.</li>
      <li id="google-signin-debug">Client-side: loading… (open Console for details)</li>
    </ul>
    <p class="muted" style="margin:var(--space-2) 0 0; font-size:0.85rem;">Remove <code>?debug=1</code> from the URL to hide this.</p>
  </div>
  <?php endif; ?>

  <div class="card" style="margin-top:var(--space-5);">
    <p class="muted" style="margin:0 0 var(--space-3); font-weight:500;">Sign in with Google</p>
    <div id="google-button-container" style="min-height:2.5rem;">
      <?php if (empty($googleClientId)): ?>
        <p class="helper" style="margin:0;">Not configured. Set <code>GOOGLE_CLIENT_ID</code> in .env to enable.</p>
      <?php endif; ?>
    </div>
    <?php if (!empty($googleClientId)): ?>
    <p class="helper" style="margin:var(--space-2) 0 0;"><a href="<?= \Hillmeet\Support\e(\Hillmeet\Support\url('/auth/google')) ?>">Continue with Google</a> (use this if the button above doesn’t appear)</p>
    <?php endif; ?>
    <div class="auth-divider">or</div>
    <a href="<?= \Hillmeet\Support\url('/auth/email') ?>" class="btn btn-secondary" style="width:100%;">Use email instead</a>
  </div>
</div>

<?php if (!empty($googleClientId)): ?>
<script>
function hillmeetDebugLog(msg, data) {
  if (typeof console !== 'undefined' && console.log) console.log('[Hillmeet Google Sign-in] ' + msg, data !== undefined ? data : '');
}
function hillmeetSetDebugText(text) {
  var el = document.getElementById('google-signin-debug');
  if (el) el.textContent = text;
}
function hillmeetRenderGoogleButton(retries) {
  retries = retries || 0;
  var container = document.getElementById('google-button-container');
  if (!container) {
    hillmeetDebugLog('Container #google-button-container not found');
    hillmeetSetDebugText('Client-side: container not found');
    return;
  }
  if (typeof google === 'undefined' || !google.accounts || !google.accounts.id) {
    hillmeetDebugLog('GSI not ready yet (attempt ' + (retries + 1) + ')', {
      google: typeof google,
      accounts: typeof google !== 'undefined' && google.accounts,
      id: typeof google !== 'undefined' && google.accounts && !!google.accounts.id
    });
    if (retries === 0) hillmeetSetDebugText('Client-side: waiting for GSI…');
    if (retries < 40) setTimeout(function() { hillmeetRenderGoogleButton(retries + 1); }, 50);
    else {
      hillmeetDebugLog('Gave up after 40 retries – GSI may have failed to load (check Network, blockers, or authorized origins)');
      hillmeetSetDebugText('Client-side: GSI script blocked or not allowed. Add this page’s origin to Google Cloud Console → Credentials → your OAuth client → Authorized JavaScript origins.');
    }
    return;
  }
  hillmeetDebugLog('GSI ready, initializing and rendering button');
  try {
    google.accounts.id.initialize({
      client_id: <?= json_encode($googleClientId) ?>,
      callback: function(response) {
        fetch('<?= \Hillmeet\Support\url('/auth/google/token') ?>', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          body: JSON.stringify({ credential: response.credential })
        }).then(function(r) { return r.json(); }).then(function(d) {
          if (d.redirect) window.location = d.redirect;
        });
      }
    });
    container.innerHTML = '';
    google.accounts.id.renderButton(container, {
      type: 'standard',
      theme: 'filled_black',
      size: 'large',
      text: 'continue_with'
    });
    hillmeetDebugLog('renderButton called successfully');
    hillmeetSetDebugText('Client-side: button rendered');
  } catch (e) {
    hillmeetDebugLog('Error during init/render', e);
    hillmeetSetDebugText('Client-side error: ' + (e && e.message ? e.message : String(e)));
  }
}
</script>
<script src="https://accounts.google.com/gsi/client?onload=hillmeetRenderGoogleButton" async defer></script>
<?php endif; ?>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
?>
