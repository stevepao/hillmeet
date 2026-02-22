<?php

declare(strict_types=1);

/**
 * AuditLog.php
 * Purpose: Append-only audit log (action, entity, user, IP).
 * Project: Hillmeet
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 */

namespace Hillmeet\Support;

use PDO;

final class AuditLog
{
    public static function log(
        string $action,
        ?string $entityType = null,
        ?string $entityId = null,
        ?array $details = null,
        ?int $userId = null,
        ?string $ip = null
    ): void {
        $pdo = Database::get();
        $userId = $userId ?? (current_user()->id ?? null);
        $ip = $ip ?? ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null);
        if (is_string($ip) && strpos($ip, ',') !== false) {
            $ip = trim(explode(',', $ip)[0]);
        }
        $stmt = $pdo->prepare("INSERT INTO audit_log (user_id, action, entity_type, entity_id, details, ip) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $userId,
            $action,
            $entityType,
            $entityId,
            $details !== null ? json_encode($details) : null,
            $ip,
        ]);
    }
}
