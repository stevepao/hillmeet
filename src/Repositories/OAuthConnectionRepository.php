<?php

declare(strict_types=1);

namespace Hillmeet\Repositories;

use Hillmeet\Support\Database;
use Hillmeet\Support\Encryption;
use PDO;

final class OAuthConnectionRepository
{
    public function getRefreshToken(int $userId, string $provider = 'google'): ?string
    {
        $stmt = Database::get()->prepare("SELECT refresh_token_encrypted FROM oauth_connections WHERE user_id = ? AND provider = ?");
        $stmt->execute([$userId, $provider]);
        $enc = $stmt->fetchColumn();
        if ($enc === false || $enc === null) {
            return null;
        }
        try {
            return Encryption::decrypt($enc);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function upsert(int $userId, string $provider, string $refreshToken, ?string $accessToken = null, ?string $accessTokenExpiresAt = null): void
    {
        $encRefresh = Encryption::encrypt($refreshToken);
        $encAccess = $accessToken !== null ? Encryption::encrypt($accessToken) : null;
        $stmt = Database::get()->prepare("
            INSERT INTO oauth_connections (user_id, provider, refresh_token_encrypted, access_token_encrypted, access_token_expires_at)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            refresh_token_encrypted = VALUES(refresh_token_encrypted),
            access_token_encrypted = VALUES(access_token_encrypted),
            access_token_expires_at = VALUES(access_token_expires_at),
            updated_at = NOW()
        ");
        $stmt->execute([$userId, $provider, $encRefresh, $encAccess, $accessTokenExpiresAt]);
    }

    public function hasConnection(int $userId, string $provider = 'google'): bool
    {
        $stmt = Database::get()->prepare("SELECT 1 FROM oauth_connections WHERE user_id = ? AND provider = ?");
        $stmt->execute([$userId, $provider]);
        return $stmt->fetchColumn() !== false;
    }

    public function deleteForUser(int $userId, string $provider = 'google'): void
    {
        $stmt = Database::get()->prepare("DELETE FROM oauth_connections WHERE user_id = ? AND provider = ?");
        $stmt->execute([$userId, $provider]);
    }
}
