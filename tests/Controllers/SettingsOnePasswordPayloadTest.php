<?php

declare(strict_types=1);

/**
 * SettingsOnePasswordPayloadTest.php
 * Purpose: Unit tests for SettingsController::buildOnePasswordPayload — the 1Password
 *          credential-type Save Button payload builder.
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
    private const API_KEY      = 'a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6'; // 48 hex chars
    private const OWNER_EMAIL  = 'owner@example.com';
    private const BASE_URL     = 'https://meet.hillwork.net';

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
    // url field
    // -------------------------------------------------------------------------

    public function testUrlFieldIncludesMcpV1Suffix(): void
    {
        $payload = SettingsController::buildOnePasswordPayload(
            self::API_KEY,
            self::OWNER_EMAIL,
            self::BASE_URL
        );

        $urlField = $this->findField($payload['fields'], 'url');
        $this->assertSame('https://meet.hillwork.net/mcp/v1', $urlField['value']);
    }

    public function testUrlFieldStripsTrailingSlashBeforeAppendingSuffix(): void
    {
        $payload = SettingsController::buildOnePasswordPayload(
            self::API_KEY,
            self::OWNER_EMAIL,
            self::BASE_URL . '/'       // trailing slash must be normalised away
        );

        $urlField = $this->findField($payload['fields'], 'url');
        $this->assertSame('https://meet.hillwork.net/mcp/v1', $urlField['value']);
    }

    // -------------------------------------------------------------------------
    // credential field
    // -------------------------------------------------------------------------

    public function testCredentialFieldValueIsApiKey(): void
    {
        $payload = SettingsController::buildOnePasswordPayload(
            self::API_KEY,
            self::OWNER_EMAIL,
            self::BASE_URL
        );

        $credField = $this->findField($payload['fields'], 'credential');
        $this->assertSame(self::API_KEY, $credField['value']);
    }

    public function testCredentialFieldTypeIsPassword(): void
    {
        $payload = SettingsController::buildOnePasswordPayload(
            self::API_KEY,
            self::OWNER_EMAIL,
            self::BASE_URL
        );

        $credField = $this->findField($payload['fields'], 'credential');
        $this->assertSame('password', $credField['type']);
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

        $usernameField = $this->findField($payload['fields'], 'username');
        $this->assertSame(self::OWNER_EMAIL, $usernameField['value']);
    }

    // -------------------------------------------------------------------------
    // Helper
    // -------------------------------------------------------------------------

    /**
     * @param  list<array<string, string>> $fields
     * @return array<string, string>
     */
    private function findField(array $fields, string $id): array
    {
        foreach ($fields as $field) {
            if (($field['id'] ?? '') === $id) {
                return $field;
            }
        }

        $this->fail("Field with id '{$id}' not found in payload fields.");
    }
}
