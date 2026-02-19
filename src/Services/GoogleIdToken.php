<?php

declare(strict_types=1);

namespace Hillmeet\Services;

final class GoogleIdToken
{
    private const JWKS_URL = 'https://www.googleapis.com/oauth2/v3/certs';

    /** @return array<string, mixed>|null Decoded payload or null if invalid */
    public static function verify(string $idToken, string $clientId): ?array
    {
        try {
            $parts = explode('.', $idToken);
            if (count($parts) !== 3) {
                return null;
            }
            $header = json_decode(self::base64UrlDecode($parts[0]), true);
            $payload = json_decode(self::base64UrlDecode($parts[1]), true);
            if (!is_array($header) || !is_array($payload)) {
                return null;
            }
            $alg = $header['alg'] ?? '';
            if ($alg !== 'RS256') {
                return null;
            }
            $kid = $header['kid'] ?? null;
            if ($kid === null) {
                return null;
            }
            $keys = self::getJwks();
            if (!isset($keys[$kid])) {
                return null;
            }
            $sig = self::base64UrlDecode($parts[2]);
            if ($sig === false) {
                return null;
            }
            $signedInput = $parts[0] . '.' . $parts[1];
            if (!openssl_verify($signedInput, $sig, $keys[$kid], OPENSSL_ALGO_SHA256)) {
                return null;
            }
            if (isset($payload['exp']) && (int) $payload['exp'] < time()) {
                return null;
            }
            $aud = $payload['aud'] ?? $payload['azp'] ?? '';
            if ($aud !== $clientId) {
                return null;
            }
            $iss = $payload['iss'] ?? '';
            if ($iss !== 'https://accounts.google.com' && $iss !== 'accounts.google.com') {
                return null;
            }
            return $payload;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** @return array<string, string> kid => PEM */
    private static function getJwks(): array
    {
        $cacheFile = sys_get_temp_dir() . '/hillmeet_google_jwks.json';
        $cacheTime = 3600;
        if (is_file($cacheFile) && filemtime($cacheFile) > time() - $cacheTime) {
            $json = file_get_contents($cacheFile);
        } else {
            $json = @file_get_contents(self::JWKS_URL);
            if ($json !== false) {
                file_put_contents($cacheFile, $json);
            } else {
                $json = is_file($cacheFile) ? file_get_contents($cacheFile) : '{"keys":[]}';
            }
        }
        $data = json_decode($json, true);
        $out = [];
        foreach ($data['keys'] ?? [] as $key) {
            if (($key['kty'] ?? '') !== 'RSA' || empty($key['kid']) || empty($key['n']) || empty($key['e'])) {
                continue;
            }
            $n = self::base64UrlDecode($key['n']);
            $e = self::base64UrlDecode($key['e']);
            if ($n === false || $e === false) {
                continue;
            }
            $pem = self::jwkToPem($n, $e);
            if ($pem !== null) {
                $out[$key['kid']] = $pem;
            }
        }
        return $out;
    }

    private static function base64UrlDecode(string $s): string|false
    {
        $s = strtr($s, '-_', '+/');
        $pad = strlen($s) % 4;
        if ($pad) {
            $s .= str_repeat('=', 4 - $pad);
        }
        return base64_decode($s, true);
    }

    private static function derInt(string $raw): string
    {
        if ($raw !== '' && (ord($raw[0]) & 0x80)) {
            $raw = "\x00" . $raw;
        }
        return "\x02" . self::derLength(strlen($raw)) . $raw;
    }

    private static function jwkToPem(string $n, string $e): ?string
    {
        $eInt = self::derInt($e);
        $nInt = self::derInt($n);
        $seq = "\x30" . self::derLength(strlen($eInt . $nInt)) . $eInt . $nInt;
        $bitString = "\x03" . self::derLength(strlen($seq) + 1) . "\x00" . $seq;
        $oid = "\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01"; // rsaEncryption
        $algoPart = "\x30" . self::derLength(strlen($oid) + 2) . $oid . "\x05\x00";
        $der = "\x30" . self::derLength(strlen($algoPart) + strlen($bitString)) . $algoPart . $bitString;
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----";
    }

    private static function derLength(int $n): string
    {
        if ($n < 128) {
            return chr($n);
        }
        $bytes = '';
        while ($n > 0) {
            $bytes = chr($n & 0xff) . $bytes;
            $n >>= 8;
        }
        return chr(0x80 | strlen($bytes)) . $bytes;
    }
}
