<?php

declare(strict_types=1);

/**
 * SettingsOnePasswordPayloadTest.php
 * Purpose: Unit tests for SettingsController::buildOnePasswordPayload — the 1Password
 *          login-type Save Button payload builder.
 * Project: Hillmeet
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 */

namespace Hillmeet\Tests\Controllers;

use Hillmeet\Controllers\SettingsController;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Hillmeet\Controllers\SettingsController::buildOnePasswordPayload
 */
final class SettingsOnePasswordPayloadTest extends TestCase
{
    private const API_KEY     = 'a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6'; // 48 hex chars
    private const OWNER_EMAIL = 'owner@example.com';
    private const BASE_URL    = 'https://meet.hillwork.net';

    // -------------------------------------------------------------------------
    // title
    // -------------------------------------------------------------------------

    public function testTitleIsHillmeetMcpGatewayApiKey(): void
    {
        $payload = SettingsController::buildOnePasswordPayload(
            self::API_KEY,
            self::OWNER_EMAIL,
            self::BASE_URL
        );

        $this->assertSame('Hillmeet MCP Gateway API Key', $payload['title']);
    }

    // -------------------------------------------------------------------------
    // notes — carries the MCP endpoint URL
    // -------------------------------------------------------------------------

    public function testNotesIncludeMcpV1Suffix(): void
    {
        $payload = SettingsController::buildOnePasswordPayload(
            self::API_KEY,
            self::OWNER_EMAIL,
            self::BASE_URL
        );

        $this->assertStringContainsString('https://meet.hillwork.net/mcp/v1', $payload['notes']);
    }

    public function testNotesUrlStripsTrailingSlashBeforeAppendingSuffix(): void
    {
        $payload = SettingsController::buildOnePasswordPayload(
            self::API_KEY,
            self::OWNER_EMAIL,
            self::BASE_URL . '/'   // trailing slash must be normalised away
        );

        $this->assertStringContainsString('https://meet.hillwork.net/mcp/v1', $payload['notes']);
        $this->assertStringNotContainsString('//mcp/v1', $payload['notes']);
    }

    // -------------------------------------------------------------------------
    // password field (current-password autocomplete)
    // -------------------------------------------------------------------------

    public function testPasswordFieldValueIsApiKey(): void
    {
        $payload = SettingsController::buildOnePasswordPayload(
            self::API_KEY,
            self::OWNER_EMAIL,
            self::BASE_URL
        );

        $field = $this->findField($payload['fields'], 'current-password');
        $this->assertSame(self::API_KEY, $field['value']);
    }

    // -------------------------------------------------------------------------
    // username field
    // -------------------------------------------------------------------------

    public function testUsernameFieldValueIsOwnerEmail(): void
    {
        $payload = SettingsController::buildOnePasswordPayload(
            self::API_KEY,
            self::OWNER_EMAIL,
            self::BASE_URL
        );

        $field = $this->findField($payload['fields'], 'username');
        $this->assertSame(self::OWNER_EMAIL, $field['value']);
    }

    // -------------------------------------------------------------------------
    // Helper
    // -------------------------------------------------------------------------

    /**
     * @param  list<array<string, string>> $fields
     * @return array<string, string>
     */
    private function findField(array $fields, string $autocomplete): array
    {
        foreach ($fields as $field) {
            if (($field['autocomplete'] ?? '') === $autocomplete) {
                return $field;
            }
        }

        $this->fail("Field with autocomplete '{$autocomplete}' not found in payload fields.");
    }
}
