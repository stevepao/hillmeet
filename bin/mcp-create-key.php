#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * mcp-create-key.php
 * Purpose: Create a tenant API key, store hash, and verify resolveTenantFromApiKey().
 * Usage: php bin/mcp-create-key.php [owner_email]
 *        If owner_email is omitted, uses the first user in the database.
 *
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

use Hillmeet\Mcp\Auth;
use Hillmeet\Support\Database;

$pdo = Database::get();
$ownerUserId = null;

if (!empty($argv[1])) {
    $emailNormalized = strtolower(trim((string) $argv[1]));
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email_normalized = ? LIMIT 1");
    $stmt->execute([$emailNormalized]);
    $row = $stmt->fetch(PDO::FETCH_OBJ);
    if ($row === false) {
        fwrite(STDERR, "No user found with that email.\n");
        exit(1);
    }
    $ownerUserId = (int) $row->id;
} else {
    $row = $pdo->query("SELECT id FROM users LIMIT 1")->fetch(PDO::FETCH_OBJ);
    if ($row === false) {
        fwrite(STDERR, "No users in database. Create a user first or pass owner email.\n");
        exit(1);
    }
    $ownerUserId = (int) $row->id;
}

$prefix = bin2hex(random_bytes(Auth::KEY_PREFIX_LENGTH / 2));
$secret = bin2hex(random_bytes(16));
$fullKey = $prefix . $secret;

$tenantId = $pdo->query("SELECT tenant_id FROM tenants WHERE owner_user_id = " . (int) $ownerUserId . " LIMIT 1")->fetchColumn();
if ($tenantId === false) {
    $tenantId = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff));
    $pdo->prepare("INSERT INTO tenants (tenant_id, owner_user_id, name) VALUES (?, ?, ?)")
        ->execute([$tenantId, $ownerUserId, 'Test tenant']);
}

$keyPrefix = substr($fullKey, 0, Auth::KEY_PREFIX_LENGTH);
$keyHash = password_hash($fullKey, PASSWORD_DEFAULT);
$label = 'test-' . date('Y-m-d-H-i-s');
$stmt = $pdo->prepare("INSERT INTO tenant_api_keys (tenant_id, key_prefix, key_hash, label) VALUES (?, ?, ?, ?)");
$stmt->execute([$tenantId, $keyPrefix, $keyHash, $label]);

$tenant = Auth::resolveTenantFromApiKey($fullKey);
if ($tenant === null) {
    fwrite(STDERR, "FAIL: resolveTenantFromApiKey returned null\n");
    exit(1);
}
if ($tenant->tenant_id !== $tenantId || (int) $tenant->owner_user_id !== $ownerUserId) {
    fwrite(STDERR, "FAIL: tenant mismatch (got tenant_id={$tenant->tenant_id}, owner_user_id={$tenant->owner_user_id})\n");
    exit(1);
}

echo "OK tenant_id=" . $tenant->tenant_id . " owner_user_id=" . $tenant->owner_user_id . "\n";
echo "API key (use once, then store securely):\n" . $fullKey . "\n";
