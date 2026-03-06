<?php

declare(strict_types=1);

/**
 * McpContext.php
 * Purpose: Request-scoped context for MCP: current tenant (set after API key auth). Used by tool handlers to get owner_email.
 * Project: Hillmeet
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 */

namespace Hillmeet\Mcp;

/**
 * Request-scoped context for MCP: current tenant (set after API key auth).
 * Used by tool handlers to get owner_email without passing it through the SDK.
 */
final class McpContext
{
    private static ?object $tenant = null;

    public static function setTenant(object $tenant): void
    {
        self::$tenant = $tenant;
    }

    public static function getTenant(): ?object
    {
        return self::$tenant;
    }

    public static function clear(): void
    {
        self::$tenant = null;
    }
}
