<?php

declare(strict_types=1);

namespace Hillmeet\Models;

use stdClass;

final class User
{
    public int $id;
    public string $email;
    public string $name;
    public ?string $google_id;
    public ?string $avatar_url;
    /** @var string|null IANA timezone (e.g. America/New_York) for displaying times to this user */
    public ?string $timezone;
    public string $created_at;
    public string $updated_at;

    public static function fromRow(stdClass $row): self
    {
        $u = new self();
        $u->id = (int) $row->id;
        $u->email = $row->email;
        $u->name = $row->name ?? '';
        $u->google_id = $row->google_id ?? null;
        $u->avatar_url = $row->avatar_url ?? null;
        $u->timezone = isset($row->timezone) && $row->timezone !== '' ? (string) $row->timezone : null;
        $u->created_at = $row->created_at;
        $u->updated_at = $row->updated_at;
        return $u;
    }
}
