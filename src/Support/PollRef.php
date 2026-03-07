<?php

declare(strict_types=1);

/**
 * PollRef.php
 * Purpose: Value object for how a poll is referenced (slug, secret, or numeric id).
 * Conservative parse: ambiguous input is treated as slug.
 * Project: Hillmeet
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 */

namespace Hillmeet\Support;

final readonly class PollRef
{
    public const TYPE_SLUG = 'slug';
    public const TYPE_SECRET = 'secret';
    public const TYPE_ID = 'id';

    public function __construct(
        public string $type,
        public string $value,
    ) {
    }

    /**
     * Parse a string reference into a PollRef.
     * - Numeric string -> id
     * - Otherwise -> slug (conservative; no separate secret-only ref without slug).
     */
    public static function parse(string $pollRef): self
    {
        $v = trim($pollRef);
        if ($v === '') {
            return new self(self::TYPE_SLUG, '');
        }
        if (ctype_digit($v)) {
            return new self(self::TYPE_ID, $v);
        }
        return new self(self::TYPE_SLUG, $v);
    }

    public function isId(): bool
    {
        return $this->type === self::TYPE_ID;
    }

    public function isSlug(): bool
    {
        return $this->type === self::TYPE_SLUG;
    }
}
