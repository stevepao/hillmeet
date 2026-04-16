<?php

declare(strict_types=1);

/**
 * SettingsController.php
 * Purpose: Settings pages — MCP Gateway API key generation (key shown once, never stored).
 * Project: Hillmeet
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 */

namespace Hillmeet\Controllers;

use Hillmeet\Support\AuditLog;
use Hillmeet\Support\RateLimit;
use function Hillmeet\Support\config;
use function Hillmeet\Support\current_user;

final class SettingsController
{
    /**
     * Expected key format: 48 lowercase hex chars.
     * = KEY_PREFIX_LENGTH(16) hex chars + bin2hex(random_bytes(16))(32) hex chars.
     */
    private const KEY_PATTERN = '/^[0-9a-f]{48}$/';

    /**
     * GET /settings/mcp-gateway-key  — show form.
     * POST /settings/mcp-gateway-key — generate key, display once, never store.
     */
    public function mcpGatewayKey(): void
    {
        \Hillmeet\Middleware\RequireAuth::check();

        $user        = current_user();
        $isAdmin     = (bool) ($user->is_admin ?? false);
        $ownerEmail  = (string) $user->email;
        $apiKey      = null;
        $error       = null;
        $onePasswordValue = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // CSRF already validated by CsrfMiddleware in index.php.

            // Rate-limit: 3 generations per 60-second window per user.
            if (!RateLimit::check('mcp_key_gen_' . (int) $user->id, 3)) {
                $error = 'Too many requests. Please wait a moment and try again.';
            } else {
                // Admins may specify any registered email; regular users are locked to their own.
                if ($isAdmin && isset($_POST['owner_email']) && trim((string) $_POST['owner_email']) !== '') {
                    $ownerEmail = trim((string) $_POST['owner_email']);
                }

                if (!filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) {
                    $error = 'Invalid email address.';
                } else {
                    $result = $this->generateKey($ownerEmail);

                    if ($result['success']) {
                        $apiKey = $result['key'];

                        // Build the 1Password Save Button payload (base64-encoded JSON).
                        $appUrl = config('app.url', '');
                        $onePasswordValue = base64_encode(
                            (string) json_encode(
                                self::buildOnePasswordPayload($apiKey, $ownerEmail, $appUrl),
                                JSON_UNESCAPED_SLASHES
                            )
                        );

                        // Prevent the key-bearing response from being cached anywhere.
                        header('Cache-Control: no-store');
                        header('Pragma: no-cache');

                        // Audit log — owner_email recorded, key is NEVER logged.
                        AuditLog::log(
                            'mcp_api_key_created',
                            'user',
                            (string) $user->id,
                            ['owner_email' => $ownerEmail, 'actor_user_id' => (int) $user->id]
                        );
                    } else {
                        $error = $result['error'];
                    }
                }
            }
        }

        require dirname(__DIR__, 2) . '/views/settings/mcp_gateway_key.php';
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Build the 1Password Save Button payload for a credential item.
     *
     * Extracted as a public static method so it can be unit-tested independently
     * of the HTTP request lifecycle and auth middleware.
     *
     * @return array{title: string, fields: list<array<string, string>>}
     */
    public static function buildOnePasswordPayload(string $apiKey, string $ownerEmail, string $appUrl): array
    {
        return [
            'title'  => 'Hillmeet MCP Gateway API Key',
            'fields' => [
                ['id' => 'credential', 'type' => 'password', 'value' => $apiKey],
                ['id' => 'url',        'value' => rtrim($appUrl, '/') . '/mcp/v1'],
                ['id' => 'username',   'value' => $ownerEmail],
            ],
        ];
    }

    /**
     * Execute bin/mcp-create-key.php via php8.4-cli and parse the API key from stdout.
     *
     * @return array{success: true, key: string}|array{success: false, error: string}
     */
    private function generateKey(string $ownerEmail): array
    {
        if (!function_exists('proc_open')) {
            return ['success' => false, 'error' => 'Server configuration error: proc_open is unavailable.'];
        }

        $script = dirname(__DIR__, 2) . '/bin/mcp-create-key.php';
        if (!is_file($script)) {
            return ['success' => false, 'error' => 'Key generation script not found. Contact support.'];
        }

        $cmd = 'php8.4-cli ' . escapeshellarg($script) . ' ' . escapeshellarg($ownerEmail);

        $descriptors = [
            0 => ['pipe', 'r'],   // stdin  (unused, closed immediately)
            1 => ['pipe', 'w'],   // stdout — captured
            2 => ['pipe', 'w'],   // stderr — captured for error messages
        ];

        $process = proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($process)) {
            return ['success' => false, 'error' => 'Failed to launch key generation process.'];
        }

        fclose($pipes[0]);
        $stdout   = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr   = (string) stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            // Strip non-printable chars and truncate before surfacing stderr to the UI.
            $msg = substr(preg_replace('/[^\x20-\x7E]/', '', $stderr) ?? '', 0, 200);
            return [
                'success' => false,
                'error'   => 'Key generation failed' . ($msg !== '' ? ': ' . $msg : '.'),
            ];
        }

        // The CLI script outputs several lines; the final non-empty line is the raw key.
        $lines = array_values(array_filter(array_map('trim', explode("\n", $stdout))));
        $key   = end($lines);

        if (!is_string($key) || !preg_match(self::KEY_PATTERN, $key)) {
            return [
                'success' => false,
                'error'   => 'Unexpected output from key generator. Contact support.',
            ];
        }

        return ['success' => true, 'key' => $key];
    }
}
