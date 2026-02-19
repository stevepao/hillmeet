<?php

declare(strict_types=1);

namespace Hillmeet\Support;

use SessionHandlerInterface;
use PDO;

final class SessionHandler implements SessionHandlerInterface
{
    private PDO $pdo;
    private int $ttl;
    private string $cookieName;

    public function __construct(PDO $pdo, int $ttl, string $cookieName)
    {
        $this->pdo = $pdo;
        $this->ttl = $ttl;
        $this->cookieName = $cookieName;
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        $stmt = $this->pdo->prepare("SELECT payload FROM sessions WHERE id = ? AND updated_at > DATE_SUB(NOW(), INTERVAL ? SECOND)");
        $stmt->execute([$id, $this->ttl]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['payload'] : '';
    }

    public function write(string $id, string $data): bool
    {
        $userId = isset($_SESSION['user']->id) ? (int) $_SESSION['user']->id : null;
        $stmt = $this->pdo->prepare("INSERT INTO sessions (id, user_id, payload, updated_at) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), payload = VALUES(payload), updated_at = NOW()");
        return $stmt->execute([$id, $userId, $data]);
    }

    public function destroy(string $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM sessions WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function gc(int $max_lifetime): int|false
    {
        $stmt = $this->pdo->prepare("DELETE FROM sessions WHERE updated_at < DATE_SUB(NOW(), INTERVAL ? SECOND)");
        $stmt->execute([max($max_lifetime, $this->ttl)]);
        return $stmt->rowCount() ?: 0;
    }

    public static function configure(): void
    {
        $pdo = Database::get();
        $ttl = (int) config('app.session_ttl', 7200);
        $name = config('app.session_cookie', 'hillmeet_session');
        $handler = new self($pdo, $ttl, $name);
        session_set_save_handler($handler, true);
        session_name($name);
        session_set_cookie_params([
            'lifetime' => $ttl,
            'path' => '/',
            'domain' => '',
            'secure' => (parse_url(config('app.url', 'http://localhost'), PHP_URL_SCHEME) === 'https'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}
