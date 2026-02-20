<?php
$pageTitle = 'Sign in';
$content = ob_start();
$googleClientId = $googleClientId ?? '';
$isLocal = (function_exists('env') ? env('APP_ENV', '') : '') === 'local';
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

  <?php if ($isLocal): ?>
  <div class="card card-2" style="margin-top:var(--space-4); font-size:0.85rem;">
    <p style="margin:0 0 var(--space-2); font-weight:600;">Diagnostics (APP_ENV=local)</p>
    <ul style="margin:0; padding-left:1.25rem;">
      <li>GOOGLE_CLIENT_ID: <?= $googleClientId !== '' ? 'set' : 'missing' ?></li>
      <li>GIS script: <span id="gis-load-status">checking…</span></li>
    </ul>
  </div>
  <?php endif; ?>

  <div class="card" style="margin-top:var(--space-5);">
    <p class="muted" style="margin:0 0 var(--space-3); font-weight:500;">Sign in with Google</p>
    <?php if ($googleClientId === ''): ?>
      <p class="helper" style="margin:0;">Not configured. Set <code>GOOGLE_CLIENT_ID</code> in .env to enable Google sign-in.</p>
    <?php else: ?>
      <div id="g_id_onload"
           data-client_id="<?= \Hillmeet\Support\e($googleClientId) ?>"
           data-callback="hillmeetHandleCredential"
           data-auto_prompt="false">
      </div>
      <div class="g_id_signin"
           data-type="standard"
           data-size="large"
           data-theme="filled_black"
           data-text="continue_with"
           data-shape="rectangular">
      </div>
      <p id="gis-fallback-msg" class="helper" style="margin:var(--space-2) 0 0; display:none; color:var(--danger);">Google sign-in button could not load. Add this site’s URL to Authorized JavaScript origins in Google Cloud Console.</p>
      <p id="gis-error-msg" class="helper" style="margin:var(--space-2) 0 0; display:none; color:var(--danger);"></p>
      <p class="helper" style="margin:var(--space-2) 0 0;"><a href="<?= \Hillmeet\Support\e(\Hillmeet\Support\url('/auth/google')) ?>">Sign in with Google in a new page</a> (if the button didn’t work)</p>
    <?php endif; ?>
    <div class="auth-divider">or</div>
    <a href="<?= \Hillmeet\Support\url('/auth/email') ?>" class="btn btn-secondary" style="width:100%;">Use email instead</a>
  </div>
</div>

<?php if ($googleClientId !== ''): ?>
<script>
function hillmeetHandleCredential(response) {
  if (!response || !response.credential) return;
  var tokenUrl = '<?= \Hillmeet\Support\e(\Hillmeet\Support\url('/auth/google/token')) ?>';
  var errEl = document.getElementById('gis-error-msg');
  function showErr(msg) {
    if (errEl) { errEl.textContent = msg || 'Sign-in failed. Try the link below.'; errEl.style.display = 'block'; }
  }
  fetch(tokenUrl, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    body: JSON.stringify({ credential: response.credential })
  }).then(function(r) {
    var ct = r.headers.get('Content-Type') || '';
    var isJson = ct.indexOf('application/json') !== -1;
    if (r.ok && isJson) {
      return r.json().then(function(d) {
        if (d && d.redirect) {
          window.location = d.redirect;
          return;
        }
        showErr(d && d.error ? d.error : 'Unexpected response.');
      });
    }
    if (r.ok) return;
    if (isJson) {
      return r.json().then(function(d) { showErr(d && d.error ? d.error : 'Sign-in failed.'); });
    }
    showErr('Sign-in failed (' + r.status + '). Try the link below.');
  }).catch(function() {
    showErr('Network error. Try the link below.');
  });
}
</script>
<script src="https://accounts.google.com/gsi/client" async></script>
<script>
(function() {
  var statusEl = document.getElementById('gis-load-status');
  var fallbackEl = document.getElementById('gis-fallback-msg');
  function check() {
    if (window.google && window.google.accounts && window.google.accounts.id) {
      if (statusEl) statusEl.textContent = 'loaded';
      return;
    }
    if (statusEl) statusEl.textContent = 'failed to load';
    if (fallbackEl) fallbackEl.style.display = 'block';
  }
  setTimeout(check, 2500);
})();
</script>
<?php endif; ?>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
?>
