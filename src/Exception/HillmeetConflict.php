<?php

declare(strict_types=1);

namespace Hillmeet\Exception;

/**
 * Conflict (e.g. duplicate, state conflict) (maps to JSON-RPC -32030).
 */
final class HillmeetConflict extends \RuntimeException
{
}
