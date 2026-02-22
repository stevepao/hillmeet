<?php
/**
 * new.php
 * Purpose: Create poll landing (step 0).
 * Project: Hillmeet
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 */
$pageTitle = 'Create poll';
$content = ob_start();
?>
<h1>Create poll</h1>
<p><a href="<?= \Hillmeet\Support\url('/poll/create') ?>" class="btn btn-primary">Next</a></p>
<p class="helper">You'll set the title, times, and share link.</p>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
?>
