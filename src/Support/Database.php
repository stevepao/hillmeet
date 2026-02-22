<?php

declare(strict_types=1);

/**
 * Database.php
 * Purpose: PDO singleton and connection from config.
 * Project: Hillmeet
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 */

namespace Hillmeet\Support;

use PDO;
use PDOException;

final class Database
{
    private static ?PDO $pdo = null;

    public static function get(): PDO
    {
        if (self::$pdo === null) {
            $db = config('db');
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                $db['host'],
                $db['name'],
                $db['charset']
            );
            self::$pdo = new PDO($dsn, $db['user'], $db['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        }
        return self::$pdo;
    }

    public static function runMigrations(string $migrationsDir): array
    {
        $pdo = self::get();
        $pdo->exec("CREATE TABLE IF NOT EXISTS _migrations (name VARCHAR(255) PRIMARY KEY, applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)");
        $applied = [];
        $files = glob($migrationsDir . '/*.sql');
        sort($files);
        foreach ($files as $file) {
            $name = basename($file);
            $stmt = $pdo->query("SELECT 1 FROM _migrations WHERE name = " . $pdo->quote($name));
            if ($stmt->fetch()) {
                continue;
            }
            $sql = file_get_contents($file);
            $pdo->exec($sql);
            $pdo->prepare("INSERT INTO _migrations (name) VALUES (?)")->execute([$name]);
            $applied[] = $name;
        }
        return $applied;
    }
}
