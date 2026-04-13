#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * mcp-revoke-keys.php
 * Purpose: Revoke ALL active MCP Gateway API keys for a given owner email.
 * Usage:   php8.4-cli bin/mcp-revoke-keys.php <owner_email> [--dry-run] [--confirm[=N]]
 *
 *   --dry-run      Print what would be revoked without writing anything.
 *   --confirm      Always prompt for confirmation before revoking.
 *   --confirm=N    Prompt only when the number of keys to revoke exceeds N.
 *
 * Exit codes:
 *   0  Success (including "nothing to do").
 *   1  Usage / validation error.
 *   2  Runtime / database error.
 *
 * Project: Hillmeet
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 */

// ---- CLI-only guard --------------------------------------------------------
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit(1);
}

// ---- Argument parsing ------------------------------------------------------
$ownerEmail      = '';
$dryRun          = false;
$confirmEnabled  = false;
$confirmThreshold = PHP_INT_MAX; // only used when --confirm=N is supplied

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--dry-run') {
        $dryRun = true;
    } elseif ($arg === '--confirm') {
        // --confirm alone: prompt whenever there is at least one key to revoke.
        $confirmEnabled   = true;
        $confirmThreshold = 0;
    } elseif (preg_match('/^--confirm=(\d+)$/', $arg, $m)) {
        // --confirm=N: prompt only when count > N.
        $confirmEnabled   = true;
        $confirmThreshold = (int) $m[1];
    } elseif ($arg[0] !== '-') {
        $ownerEmail = trim($arg);
    }
}

if ($ownerEmail === '') {
    fwrite(STDERR, "Usage: php8.4-cli bin/mcp-revoke-keys.php <owner_email> [--dry-run] [--confirm[=N]]\n");
    fwrite(STDERR, "\n");
    fwrite(STDERR, "  --dry-run      Show what would be revoked without making changes.\n");
    fwrite(STDERR, "  --confirm      Always prompt before revoking.\n");
    fwrite(STDERR, "  --confirm=N    Prompt when the key count exceeds N.\n");
    exit(1);
}

// ---- Bootstrap -------------------------------------------------------------
require_once dirname(__DIR__) . '/config/env.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

$configPath = dirname(__DIR__) . '/config/config.php';
if (!is_file($configPath)) {
    fwrite(STDERR, "Error: config.php not found. Copy config.example.php to config.php first.\n");
    exit(2);
}

use Hillmeet\Services\McpKeyService;
use Hillmeet\Support\AuditLog;

// ---- Validate email --------------------------------------------------------
if (!filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Error: '{$ownerEmail}' is not a valid email address.\n");
    exit(1);
}

// ---- Initialise service ----------------------------------------------------
try {
    $service = new McpKeyService();
} catch (Throwable $e) {
    fwrite(STDERR, "Error: Could not connect to the database: " . $e->getMessage() . "\n");
    exit(2);
}

// ---- Look up owner user ----------------------------------------------------
$user = $service->findUserByEmail($ownerEmail);
if ($user === null) {
    fwrite(STDERR, "Error: No user found with email '{$ownerEmail}'.\n");
    exit(1);
}

// ---- Check active key count ------------------------------------------------
$activeCount = $service->countActiveKeys($ownerEmail);

if ($activeCount === 0) {
    echo "No active API keys found for {$ownerEmail}.\n";
    exit(0);
}

// ---- Dry-run ---------------------------------------------------------------
if ($dryRun) {
    $keys = $service->listActiveKeys($ownerEmail);
    echo "[dry-run] Would revoke {$activeCount} active API key(s) for {$ownerEmail}:\n";
    foreach ($keys as $k) {
        $lastUsed = $k->last_used_at ?? 'never';
        printf(
            "  key_id=%-6d  prefix=%-16s  label=%-30s  created=%s  last_used=%s\n",
            $k->key_id,
            $k->key_prefix,
            $k->label !== '' ? $k->label : '(none)',
            $k->created_at,
            $lastUsed
        );
    }
    exit(0);
}

// ---- Interactive confirmation ----------------------------------------------
if ($confirmEnabled && $activeCount > $confirmThreshold) {
    fwrite(
        STDOUT,
        "About to revoke {$activeCount} active API key(s) for {$ownerEmail}.\n" .
        "This cannot be undone. Type 'yes' to confirm: "
    );
    $answer = trim((string) fgets(STDIN));
    if (strtolower($answer) !== 'yes') {
        echo "Aborted — no keys were revoked.\n";
        exit(0);
    }
}

// ---- Revoke ----------------------------------------------------------------
try {
    $revoked = $service->revokeAllActiveKeys($ownerEmail);
} catch (Throwable $e) {
    fwrite(STDERR, "Error: Revocation failed: " . $e->getMessage() . "\n");
    exit(2);
}

echo "Revoked {$revoked} API key(s) for {$ownerEmail}.\n";

// ---- Audit log -------------------------------------------------------------
// Determine the Unix user running this script (best effort; no sensitive data).
$actorUnixUser = 'unknown';
if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
    $pw = posix_getpwuid(posix_geteuid());
    $actorUnixUser = is_array($pw) ? ($pw['name'] ?? 'unknown') : 'unknown';
} elseif (getenv('USER') !== false) {
    $actorUnixUser = (string) getenv('USER');
} elseif (getenv('USERNAME') !== false) {
    $actorUnixUser = (string) getenv('USERNAME');
}

try {
    AuditLog::log(
        'mcp_api_keys_revoked',
        'user',
        (string) $user->id,
        [
            'owner_email'      => $ownerEmail,
            'keys_revoked'     => $revoked,
            'actor_unix_user'  => $actorUnixUser,
        ],
        (int) $user->id,
        null   // no IP in CLI context
    );
} catch (Throwable $e) {
    // Audit failure is non-fatal; revocation already succeeded.
    fwrite(STDERR, "Warning: Audit log write failed: " . $e->getMessage() . "\n");
}

exit(0);
