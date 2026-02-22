#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * migrate.php
 * Purpose: CLI migration runner; applies SQL migrations in order.
 * Project: Hillmeet
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 */

require_once dirname(__DIR__) . '/config/env.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

$configPath = dirname(__DIR__) . '/config/config.php';
if (!is_file($configPath)) {
    fwrite(STDERR, "Copy config.example.php to config.php first.\n");
    exit(1);
}

$migrationsDir = dirname(__DIR__) . '/migrations';
$applied = \Hillmeet\Support\Database::runMigrations($migrationsDir);
foreach ($applied as $name) {
    echo "Applied: {$name}\n";
}
if ($applied === []) {
    echo "No new migrations.\n";
}
