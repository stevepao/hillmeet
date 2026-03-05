<?php

declare(strict_types=1);

/**
 * Auth.php
 * MCP API-key auth: Bearer token → tenant (owner_user_id). Keys stored as hash only.
 */

namespace Hillmeet\Mcp;

use Hillmeet\Support\Database;
use PDO;

final class Auth
{
    /** Length of key_prefix in tenant_api_keys (first N chars of the full key). */
    public const KEY_PREFIX_LENGTH = 16;

    /**
     * Resolve tenant from API key (Bearer token).
     * Updates last_used_at on success. Returns null if key missing, revoked, or invalid.
     *
     * @return object|null Tenant row with tenant_id, owner_user_id, name, created_at
     */
    public static function resolveTenantFromApiKey(string $apiKey): ?object
    {
        $apiKey = trim($apiKey);
        if ($apiKey === '' || strlen($apiKey) <= self::KEY_PREFIX_LENGTH) {
            return null;
        }
        $prefix = substr($apiKey, 0, self::KEY_PREFIX_LENGTH);
        $pdo = Database::get();
        $stmt = $pdo->prepare(
            "SELECT k.key_id, k.tenant_id, k.key_hash, t.tenant_id AS t_tenant_id, t.owner_user_id, t.name, t.created_at
             FROM tenant_api_keys k
             INNER JOIN tenants t ON t.tenant_id = k.tenant_id
             WHERE k.key_prefix = ? AND k.revoked_at IS NULL"
        );
        $stmt->execute([$prefix]);
        $row = $stmt->fetch(PDO::FETCH_OBJ);
        if ($row === false || !password_verify($apiKey, $row->key_hash)) {
            return null;
        }
        $pdo->prepare("UPDATE tenant_api_keys SET last_used_at = CURRENT_TIMESTAMP WHERE key_id = ?")
            ->execute([$row->key_id]);
        return (object) [
            'tenant_id' => $row->tenant_id,
            'owner_user_id' => (int) $row->owner_user_id,
            'name' => $row->name,
            'created_at' => $row->created_at,
        ];
    }

    /**
     * Extract Bearer token from Authorization header.
     */
    public static function getBearerKey(): ?string
    {
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if ($auth === '' || !preg_match('/^Bearer\s+(.+)$/i', trim($auth), $m)) {
            return null;
        }
        return trim($m[1]);
    }

    /**
     * Require valid API key; return tenant or send 401 with JSON-RPC error -32001 and exit.
     *
     * @return object Tenant row (tenant_id, owner_user_id, name, created_at)
     */
    public static function requireApiKey(): object
    {
        $key = self::getBearerKey();
        if ($key === null) {
            self::sendUnauthorized();
        }
        $tenant = self::resolveTenantFromApiKey($key);
        if ($tenant === null) {
            self::sendUnauthorized();
        }
        return $tenant;
    }

    /**
     * Send 401 with JSON-RPC error code -32001 (Unauthorized) and exit.
     */
    public static function sendUnauthorized(): never
    {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'jsonrpc' => '2.0',
            'error' => [
                'code' => -32001,
                'message' => 'Unauthorized',
            ],
        ], JSON_THROW_ON_ERROR);
        exit;
    }
}
