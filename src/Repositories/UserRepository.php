<?php

declare(strict_types=1);

namespace Hillmeet\Repositories;

use Hillmeet\Models\User;
use Hillmeet\Support\Database;
use PDO;

final class UserRepository
{
    public function findById(int $id): ?User
    {
        $stmt = Database::get()->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_OBJ);
        return $row ? User::fromRow($row) : null;
    }

    public function findByEmail(string $email): ?User
    {
        $stmt = Database::get()->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $row = $stmt->fetch(PDO::FETCH_OBJ);
        return $row ? User::fromRow($row) : null;
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
        $pdo = Database::get();
        $stmt = $pdo->prepare("INSERT INTO users (email, name, google_id, avatar_url) VALUES (?, ?, ?, ?)");
        $stmt->execute([$email, $name, $googleId, $avatarUrl]);
        return $this->findById((int) $pdo->lastInsertId());
    }

    public function createFromEmail(string $email, string $name = ''): User
    {
        $pdo = Database::get();
        $stmt = $pdo->prepare("INSERT INTO users (email, name) VALUES (?, ?)");
        $stmt->execute([$email, $name ?: $email]);
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
}
