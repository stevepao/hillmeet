<?php

declare(strict_types=1);

namespace Hillmeet\Tests\Mcp;

use Mcp\Server\Session\SessionInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Reusable mock session for MCP tool tests.
 */
final class MockSession implements SessionInterface
{
    public function __construct(private readonly Uuid $id = new \Symfony\Component\Uid\UuidV4()) {}

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function save(): bool
    {
        return true;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $default;
    }

    public function set(string $key, mixed $value, bool $overwrite = true): void {}

    public function has(string $key): bool
    {
        return false;
    }

    public function forget(string $key): void {}

    public function clear(): void {}

    public function pull(string $key, mixed $default = null): mixed
    {
        return $default;
    }

    public function all(): array
    {
        return [];
    }

    public function hydrate(array $attributes): void {}

    public function getStore(): \Mcp\Server\Session\SessionStoreInterface
    {
        return new class implements \Mcp\Server\Session\SessionStoreInterface {
            public function exists(Uuid $id): bool
            {
                return false;
            }

            public function read(Uuid $id): string|false
            {
                return false;
            }

            public function write(Uuid $id, string $data): bool
            {
                return true;
            }

            public function destroy(Uuid $id): bool
            {
                return true;
            }

            public function gc(): array
            {
                return [];
            }
        };
    }

    public function jsonSerialize(): array
    {
        return [];
    }
}
