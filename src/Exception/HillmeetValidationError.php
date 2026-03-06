<?php

declare(strict_types=1);

namespace Hillmeet\Exception;

/**
 * MCP / adapter validation error (maps to JSON-RPC -32010).
 * Project: Hillmeet
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 */
final class HillmeetValidationError extends \RuntimeException
{
    public function __construct(
        string $message = 'Validation error',
        /** @var list<array{field?: string, reason?: string}> */
        public readonly array $data = [],
        \Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
