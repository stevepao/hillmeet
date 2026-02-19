<?php

declare(strict_types=1);

namespace Hillmeet\Support;

use RuntimeException;

final class Encryption
{
    private const CIPHER = 'aes-256-gcm';
    private const IV_LEN = 12;
    private const TAG_LEN = 16;

    public static function encrypt(string $plaintext): string
    {
        $key = self::getKey();
        $iv = random_bytes(self::IV_LEN);
        $tag = '';
        $encrypted = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LEN
        );
        if ($encrypted === false) {
            throw new RuntimeException('Encryption failed');
        }
        return base64_encode($iv . $tag . $encrypted);
    }

    public static function decrypt(string $ciphertext): string
    {
        $key = self::getKey();
        $raw = base64_decode($ciphertext, true);
        if ($raw === false || strlen($raw) < self::IV_LEN + self::TAG_LEN) {
            throw new RuntimeException('Invalid ciphertext');
        }
        $iv = substr($raw, 0, self::IV_LEN);
        $tag = substr($raw, self::IV_LEN, self::TAG_LEN);
        $encrypted = substr($raw, self::IV_LEN + self::TAG_LEN);
        $decrypted = openssl_decrypt(
            $encrypted,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );
        if ($decrypted === false) {
            throw new RuntimeException('Decryption failed');
        }
        return $decrypted;
    }

    private static function getKey(): string
    {
        $hex = config('app.encryption_key', '');
        if (strlen($hex) !== 64 || !ctype_xdigit($hex)) {
            throw new RuntimeException('ENCRYPTION_KEY must be 32-byte hex (64 chars)');
        }
        return hex2bin($hex);
    }
}
