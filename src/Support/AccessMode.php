<?php

declare(strict_types=1);

/**
 * AccessMode.php
 * Purpose: How the poll was accessed (organizer, invitee, secret-link, or public).
 * Project: Hillmeet
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 */

namespace Hillmeet\Support;

enum AccessMode: string
{
    case ORGANIZER = 'organizer';
    case INVITEE = 'invitee';
    case SECRET_LINK = 'secret-link';
    case PUBLIC = 'public';
}
