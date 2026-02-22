#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * cron.php
 * Purpose: Cron job to clean expired PINs and optional session prune.
 * Project: Hillmeet
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 *
 * Run from cron (e.g. every 5–15 min). IONOS: Scheduled Tasks → php /path/to/hillmeet/bin/cron.php
 */

require_once dirname(__DIR__) . '/config/env.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

$configPath = dirname(__DIR__) . '/config/config.php';
if (!is_file($configPath)) {
    fwrite(STDERR, "Config not found.\n");
    exit(1);
}

$pdo = \Hillmeet\Support\Database::get();

// Expire old email PINs
$pdo->exec("DELETE FROM email_login_pins WHERE expires_at < NOW()");

// Optional: prune old sessions (if not using session handler cleanup)
// $pdo->exec("DELETE FROM sessions WHERE updated_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");

echo "Cron completed.\n";
