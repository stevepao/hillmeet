<?php

declare(strict_types=1);

namespace Hillmeet\Exception;

/**
 * Poll not found (HTTP 404, MCP -32020).
 * Use when the poll does not exist or when we intentionally mask forbidden as not-found.
 * Project: Hillmeet
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 */
final class PollNotFound extends HillmeetNotFound
{
}
