<?php

declare(strict_types=1);

namespace Hillmeet\Exception;

/**
 * Poll access forbidden (HTTP 403, MCP -32002).
 * Use when the actor is not allowed to access the poll (e.g. not the organizer).
 * Project: Hillmeet
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 */
final class PollForbidden extends \RuntimeException
{
}
