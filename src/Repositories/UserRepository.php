<?php

declare(strict_types=1);

namespace Hillmeet\Repositories;

use Hillmeet\Models\User;
use Hillmeet\Support\Database;
use PDO;

final class UserRepository
{
    private static ?bool $hasEmailNormalized = null;

    private function hasEmailNormalizedColumn(): bool
    {
        if (self::$hasEmailNormalized !== null) {
            return self::$hasEmailNormalized;
        }
        $stmt = Database::get()->query("SHOW COLUMNS FROM users LIKE 'email_normalized'");
        self::$hasEmailNormalized = $stmt->rowCount() > 0;
        return self::$hasEmailNormalized;
    }

    public function findById(int $id): ?User
    {
        $stmt = Database::get()->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_OBJ);
        return $row ? User::fromRow($row) : null;
    }

    /** Find by email (case-insensitive). Uses email_normalized when present. */
    public function findByEmail(string $email): ?User
    {
        $normalized = self::normalizeEmail($email);
        if ($normalized === '') {
            return null;
        }
        if ($this->hasEmailNormalizedColumn()) {
            $stmt = Database::get()->prepare("SELECT * FROM users WHERE email_normalized = ?");
        } else {
            $stmt = Database::get()->prepare("SELECT * FROM users WHERE LOWER(email) = ?");
        }
        $stmt->execute([$normalized]);
        $row = $stmt->fetch(PDO::FETCH_OBJ);
        return $row ? User::fromRow($row) : null;
    }

    public static function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    public function findByGoogleId(string $googleId): ?User
    {
        $stmt = Database::get()->prepare("SELECT * FROM users WHERE google_id = ?");
        $stmt->execute([$googleId]);
        $row = $stmt->fetch(PDO::FETCH_OBJ);
        return $row ? User::fromRow($row) : null;
    }

    public function createFromGoogle(string $email, string $name, string $googleId, ?string $avatarUrl = null): User
    {
        $normalized = self::normalizeEmail($email);
        $pdo = Database::get();
        if ($this->hasEmailNormalizedColumn()) {
            $stmt = $pdo->prepare("INSERT INTO users (email, email_normalized, name, google_id, avatar_url) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$normalized, $normalized, $name ?: $normalized, $googleId, $avatarUrl]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO users (email, name, google_id, avatar_url) VALUES (?, ?, ?, ?)");
            $stmt->execute([$normalized, $name ?: $normalized, $googleId, $avatarUrl]);
        }
        return $this->findById((int) $pdo->lastInsertId());
    }

    public function createFromEmail(string $email, string $name = ''): User
    {
        $normalized = self::normalizeEmail($email);
        $pdo = Database::get();
        if ($this->hasEmailNormalizedColumn()) {
            $stmt = $pdo->prepare("INSERT INTO users (email, email_normalized, name) VALUES (?, ?, ?)");
            $stmt->execute([$normalized, $normalized, $name ?: $normalized]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO users (email, name) VALUES (?, ?)");
            $stmt->execute([$normalized, $name ?: $normalized]);
        }
        return $this->findById((int) $pdo->lastInsertId());
    }

    public function getOrCreateByEmail(string $email, string $name = ''): User
    {
        $user = $this->findByEmail($email);
        if ($user !== null) {
            return $user;
        }
        return $this->createFromEmail($email, $name);
    }

    /** Set the user's timezone (IANA name, e.g. America/New_York). No-op if invalid. */
    public function setTimezone(int $userId, string $timezone): void
    {
        $timezone = trim($timezone);
        if ($timezone === '') {
            return;
        }
        try {
            new \DateTimeZone($timezone);
        } catch (\Exception $e) {
            return;
        }
        $stmt = Database::get()->prepare("UPDATE users SET timezone = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$timezone, $userId]);
    }

    /** Update existing user with Google id and normalized email (attach Google to existing email account). */
    public function attachGoogleId(int $userId, string $normalizedEmail, string $googleId, string $name, ?string $avatarUrl = null): void
    {
        if ($this->hasEmailNormalizedColumn()) {
            $stmt = Database::get()->prepare("UPDATE users SET email = ?, email_normalized = ?, google_id = ?, name = ?, avatar_url = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$normalizedEmail, $normalizedEmail, $googleId, $name, $avatarUrl, $userId]);
        } else {
            $stmt = Database::get()->prepare("UPDATE users SET email = ?, google_id = ?, name = ?, avatar_url = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$normalizedEmail, $googleId, $name, $avatarUrl, $userId]);
        }
    }
}
