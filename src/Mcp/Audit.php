<?php

declare(strict_types=1);

/**
 * Audit.php
 * MCP tool call audit: tenant_id, tool name, duration_ms, ok/error, request_id.
 */

namespace Hillmeet\Mcp;

use Hillmeet\Support\AuditLog;

final class Audit
{
    /**
     * Log an MCP tool call for audit (tenant, tool name, duration, outcome, request_id).
     */
    public static function logToolCall(
        object $tenant,
        string $toolName,
        int $durationMs,
        bool $ok,
        string|int $requestId,
        ?string $error = null,
    ): void {
        $details = [
            'tenant_id' => $tenant->tenant_id,
            'duration_ms' => $durationMs,
            'ok' => $ok,
            'request_id' => $requestId,
        ];
        if ($error !== null) {
            $details['error'] = $error;
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
