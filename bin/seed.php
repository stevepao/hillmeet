#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/env.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

$configPath = dirname(__DIR__) . '/config/config.php';
if (!is_file($configPath)) {
    fwrite(STDERR, "Copy config.example.php to config.php first.\n");
    exit(1);
}

// Optional: seed a test user or sample poll for development.
// This file is a placeholder; add seeds as needed.
echo "No seeds defined. Add development data in bin/seed.php if needed.\n";
