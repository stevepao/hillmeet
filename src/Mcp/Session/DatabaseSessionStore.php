<?php

declare(strict_types=1);

/**
 * DatabaseSessionStore.php
 * Purpose: MCP session store backed by MySQL so sessions persist across HTTP requests.
 * Project: Hillmeet
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 */

namespace Hillmeet\Mcp\Session;

use Hillmeet\Support\Database;
use Mcp\Server\Session\SessionStoreInterface;
use Symfony\Component\Uid\Uuid;

final class DatabaseSessionStore implements SessionStoreInterface
{
    public function __construct(
        private readonly int $ttl = 3600,
    ) {
    }

    public function exists(Uuid $id): bool
    {
        $pdo = Database::get();
        $stmt = $pdo->prepare("SELECT 1 FROM mcp_sessions WHERE id = ? AND updated_at > ?");
        $stmt->execute([$id->toRfc4122(), time() - $this->ttl]);
        return $stmt->fetchColumn() !== false;
    }

    public function read(Uuid $id): string|false
    {
        $pdo = Database::get();
        $stmt = $pdo->prepare("SELECT data, updated_at FROM mcp_sessions WHERE id = ?");
        $stmt->execute([$id->toRfc4122()]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($row === false) {
            return false;
        }
        if ((time() - (int) $row['updated_at']) > $this->ttl) {
            $this->destroy($id);
            return false;
        }
        return $row['data'];
    }

    public function write(Uuid $id, string $data): bool
    {
        $pdo = Database::get();
        $now = time();
        $stmt = $pdo->prepare("INSERT INTO mcp_sessions (id, data, updated_at) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE data = VALUES(data), updated_at = VALUES(updated_at)");
        return $stmt->execute([$id->toRfc4122(), $data, $now]);
    }

    public function destroy(Uuid $id): bool
    {
        $pdo = Database::get();
        $stmt = $pdo->prepare("DELETE FROM mcp_sessions WHERE id = ?");
        $stmt->execute([$id->toRfc4122()]);
        return true;
    }

    public function gc(): array
    {
        $pdo = Database::get();
        $cutoff = time() - $this->ttl;
        $stmt = $pdo->query("SELECT id FROM mcp_sessions WHERE updated_at < " . (int) $cutoff);
        $ids = [];
        while (($id = $stmt->fetchColumn()) !== false && \is_string($id)) {
            $ids[] = Uuid::fromString($id);
        }
        if ($ids !== []) {
            $placeholders = implode(',', array_fill(0, \count($ids), '?'));
            $stmt = $pdo->prepare("DELETE FROM mcp_sessions WHERE id IN ($placeholders)");
            $stmt->execute(array_map(static fn (Uuid $u) => $u->toRfc4122(), $ids));
        }
        return $ids;
    }
}
