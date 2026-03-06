<?php

declare(strict_types=1);

namespace Hillmeet\Exception;

/**
 * Conflict (e.g. duplicate, state conflict) (maps to JSON-RPC -32030).
 * Project: Hillmeet
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 */
final class HillmeetConflict extends \RuntimeException
{
}
