<?php

declare(strict_types=1);

/**
 * McpKeyService.php
 * Purpose: Shared key-store operations for MCP Gateway CLI tools.
 *          Encapsulates the user → tenant → tenant_api_keys query chain so that
 *          bin/mcp-create-key.php and bin/mcp-revoke-keys.php share the same logic
 *          without duplicating SQL.
 *
 * Project: Hillmeet
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 */

namespace Hillmeet\Services;

use Hillmeet\Support\Database;
use PDO;

final class McpKeyService
{
    private readonly PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::get();
    }

    // -------------------------------------------------------------------------
    // User / tenant helpers
    // -------------------------------------------------------------------------

    /**
     * Look up a Hillmeet user by email (case-insensitive, normalised).
     * Returns a plain object with at minimum: id, email.
     */
    public function findUserByEmail(string $email): ?object
    {
        $normalised = strtolower(trim($email));
        $stmt = $this->pdo->prepare(
            "SELECT id, email FROM users WHERE email_normalized = ? LIMIT 1"
        );
        $stmt->execute([$normalised]);
        $row = $stmt->fetch(PDO::FETCH_OBJ);
        return $row ?: null;
    }

    // -------------------------------------------------------------------------
    // Key-store queries  (no key material is ever selected or returned)
    // -------------------------------------------------------------------------

    /**
     * Count non-revoked API keys across all tenants owned by the given email.
     */
    public function countActiveKeys(string $email): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*)
             FROM tenant_api_keys k
             INNER JOIN tenants t ON t.tenant_id = k.tenant_id
             INNER JOIN users   u ON u.id = t.owner_user_id
             WHERE u.email_normalized = ?
               AND k.revoked_at IS NULL"
        );
        $stmt->execute([strtolower(trim($email))]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Return metadata rows for all non-revoked keys owned by the given email.
     * key_hash is deliberately excluded from the SELECT — no key material is returned.
     *
     * @return object[]  Each row: key_id, key_prefix, label, created_at, last_used_at, tenant_id
     */
    public function listActiveKeys(string $email): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT k.key_id,
                    k.key_prefix,
                    k.label,
                    k.created_at,
                    k.last_used_at,
                    t.tenant_id
             FROM tenant_api_keys k
             INNER JOIN tenants t ON t.tenant_id = k.tenant_id
             INNER JOIN users   u ON u.id = t.owner_user_id
             WHERE u.email_normalized = ?
               AND k.revoked_at IS NULL
             ORDER BY k.created_at DESC"
        );
        $stmt->execute([strtolower(trim($email))]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    // -------------------------------------------------------------------------
    // Revocation
    // -------------------------------------------------------------------------

    /**
     * Set revoked_at = NOW() on every active key owned by the given email.
     *
     * @param bool $dryRun When true, the count is returned but no UPDATE is executed.
     * @return int         Number of keys revoked (or that would be revoked in dry-run mode).
     */
    public function revokeAllActiveKeys(string $email, bool $dryRun = false): int
    {
        $count = $this->countActiveKeys($email);

        if ($count === 0 || $dryRun) {
            return $count;
        }

        // MySQL UPDATE … INNER JOIN syntax; the WHERE clause is idempotent —
        // already-revoked rows are excluded so repeated calls are safe.
        $stmt = $this->pdo->prepare(
            "UPDATE tenant_api_keys k
             INNER JOIN tenants t ON t.tenant_id = k.tenant_id
             INNER JOIN users   u ON u.id = t.owner_user_id
             SET k.revoked_at = CURRENT_TIMESTAMP
             WHERE u.email_normalized = ?
               AND k.revoked_at IS NULL"
        );
        $stmt->execute([strtolower(trim($email))]);

        return $count;
    }
}
