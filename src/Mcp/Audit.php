<?php

declare(strict_types=1);

/**
 * Audit.php
 * MCP tool call audit: writes to mcp_audit_log and optionally to generic audit_log.
 */

namespace Hillmeet\Mcp;

use Hillmeet\Support\AuditLog;
use Hillmeet\Support\Database;

final class Audit
{
    /**
     * Log an MCP tool call. Writes a row to mcp_audit_log.
     * Optionally also logs to the generic audit_log for backward compatibility.
     *
     * @param object    $tenant    Tenant object with at least tenant_id and owner_user_id
     * @param string    $toolName  Tool name (e.g. hillmeet_list_nonresponders)
     * @param int       $durationMs Duration in milliseconds
     * @param bool      $ok        True if the tool completed successfully
     * @param string|int $requestId JSON-RPC request id
     * @param string|null $error   Error message when ok=false (optional)
     * @param int|null  $errorCode JSON-RPC error code when ok=false (e.g. -32010, -32020)
     */
    public static function logToolCall(
        object $tenant,
        string $toolName,
        int $durationMs,
        bool $ok,
        string|int $requestId,
        ?string $error = null,
        ?int $errorCode = null,
    ): void {
        $tenantId = $tenant->tenant_id ?? '';
        $requestIdStr = $requestId === null ? null : (string) $requestId;

        $stmt = Database::get()->prepare(
            "INSERT INTO mcp_audit_log (tenant_id, tool, request_id, ok, error_code, duration_ms) VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $tenantId,
            $toolName,
            $requestIdStr,
            $ok ? 1 : 0,
            $errorCode,
            $durationMs,
        ]);

        $details = [
            'tenant_id' => $tenantId,
            'duration_ms' => $durationMs,
            'ok' => $ok,
            'request_id' => $requestId,
        ];
        if ($error !== null) {
            $details['error'] = $error;
        }
        if ($errorCode !== null) {
            $details['error_code'] = $errorCode;
        }
        AuditLog::log(
            'mcp_tool_call',
            'tool',
            $toolName,
            $details,
            $tenant->owner_user_id ?? null,
        );
    }
}
