<?php

declare(strict_types=1);

namespace Hillmeet\Repositories;

use Hillmeet\Support\Database;
use PDO;

final class EmailLoginPinRepository
{
    private const PIN_EXPIRY_MINUTES = 10;
    private const MAX_ATTEMPTS = 5;

    public function create(string $email, string $pinHash): void
    {
        $expires = date('Y-m-d H:i:s', time() + self::PIN_EXPIRY_MINUTES * 60);
        $stmt = Database::get()->prepare("INSERT INTO email_login_pins (email, pin_hash, expires_at) VALUES (?, ?, ?)");
        $stmt->execute([$email, $pinHash, $expires]);
    }

    public function findValid(string $email): ?object
    {
        $stmt = Database::get()->prepare("
            SELECT id, pin_hash, expires_at, attempts
            FROM email_login_pins
            WHERE email = ? AND expires_at > NOW() AND attempts < ?
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$email, self::MAX_ATTEMPTS]);
        $row = $stmt->fetch(PDO::FETCH_OBJ);
        return $row ?: null;
    }

    public function incrementAttempts(int $id): void
    {
        $stmt = Database::get()->prepare("UPDATE email_login_pins SET attempts = attempts + 1 WHERE id = ?");
        $stmt->execute([$id]);
    }

    public function invalidate(int $id): void
    {
        $stmt = Database::get()->prepare("UPDATE email_login_pins SET expires_at = NOW() WHERE id = ?");
        $stmt->execute([$id]);
    }

    public function cleanup(): void
    {
        $stmt = Database::get()->prepare("DELETE FROM email_login_pins WHERE expires_at < NOW()");
        $stmt->execute();
    }
}
