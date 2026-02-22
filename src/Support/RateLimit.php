<?php

declare(strict_types=1);

/**
 * RateLimit.php
 * Purpose: In-memory rate limiting by key with configurable limits.
 * Project: Hillmeet
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 */

namespace Hillmeet\Support;

use PDO;

final class RateLimit
{
    private const TABLE = 'rate_limit';
    private const WINDOW = 60; // seconds

    public static function check(string $key, int $maxRequests): bool
    {
        $pdo = Database::get();
        self::ensureTable($pdo);
        $now = time();
        $windowStart = $now - self::WINDOW;
        $stmt = $pdo->prepare("DELETE FROM " . self::TABLE . " WHERE `key` = ? AND created_at < ?");
        $stmt->execute([$key, date('Y-m-d H:i:s', $windowStart)]);
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM " . self::TABLE . " WHERE `key` = ? AND created_at >= ?");
        $stmt->execute([$key, date('Y-m-d H:i:s', $windowStart)]);
        $count = (int) $stmt->fetchColumn();
        if ($count >= $maxRequests) {
            return false;
        }
        $stmt = $pdo->prepare("INSERT INTO " . self::TABLE . " (`key`, created_at) VALUES (?, NOW())");
        $stmt->execute([$key]);
        return true;
    }

    private static function ensureTable(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS " . self::TABLE . " (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `key` VARCHAR(128) NOT NULL,
            created_at DATETIME NOT NULL,
            KEY idx_key_created (`key`, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
}
