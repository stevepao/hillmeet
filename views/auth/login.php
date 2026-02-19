<?php
$pageTitle = 'Sign in';
$content = ob_start();
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

  <div class="card" style="margin-top:var(--space-5);">
    <div id="google-button-container">
      <?php if (empty($googleClientId)): ?>
        <p class="muted" style="margin:0 0 var(--space-2);">Sign in with Google</p>
        <p class="helper" style="margin:0;">Not configured. Set <code>GOOGLE_CLIENT_ID</code> in .env to enable.</p>
      <?php endif; ?>
    </div>
    <div class="auth-divider">or</div>
    <a href="<?= \Hillmeet\Support\url('/auth/email') ?>" class="btn btn-secondary" style="width:100%;">Use email instead</a>
  </div>
</div>

<?php if (!empty($googleClientId)): ?>
<script src="https://accounts.google.com/gsi/client" async defer></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  if (typeof google !== 'undefined' && google.accounts && google.accounts.id) {
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
    var container = document.getElementById('google-button-container');
    container.innerHTML = '';
    google.accounts.id.renderButton(container, {
      type: 'standard',
      theme: 'filled_black',
      size: 'large',
      text: 'continue_with'
    });
  }
});
</script>
<?php endif; ?>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
?>
