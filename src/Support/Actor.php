<?php

declare(strict_types=1);

/**
 * Actor.php
 * Purpose: Who is trying to access the poll (organizer or invitee). Value objects for access flows.
 * Project: Hillmeet
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 */

namespace Hillmeet\Support;

/** Organizer identity: by normalized email or by user id. */
final readonly class OrganizerActor
{
    public function __construct(
        public ?string $ownerEmail = null,
        public ?int $userId = null,
    ) {
    }

    public static function byEmail(string $ownerEmail): self
    {
        return new self(ownerEmail: $ownerEmail, userId: null);
    }

    public static function byUserId(int $userId): self
    {
        return new self(ownerEmail: null, userId: $userId);
    }
}

/** Invitee identity: email plus optional secret and invite token. */
final readonly class InviteeActor
{
    public function __construct(
        public string $inviteeEmail,
        public ?string $secret = null,
        public ?string $inviteToken = null,
    ) {
    }
}
