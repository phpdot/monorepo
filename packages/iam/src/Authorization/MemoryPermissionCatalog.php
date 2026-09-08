<?php

declare(strict_types=1);

/**
 * The declared vocabulary, immutable after construction — built once from the
 * scan (pre-fork; workers inherit the frozen copy, which is exactly right for
 * data that only changes with a deploy).
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Authorization;

use PHPdot\Iam\Authorization\Contract\PermissionCatalogInterface;
use PHPdot\Iam\Authorization\Discovery\DiscoveredPermission;

final readonly class MemoryPermissionCatalog implements PermissionCatalogInterface
{
    /**
     * @var array<string, DiscoveredPermission> keyed by permission key
     */
    private array $byKey;

    /**
     * @param list<DiscoveredPermission> $permissions The scan output
     */
    public function __construct(array $permissions)
    {
        $byKey = [];

        foreach ($permissions as $permission) {
            $byKey[$permission->key] = $permission;
        }

        $this->byKey = $byKey;
    }

    /**
     * @inheritDoc
     */
    public function all(): array
    {
        return array_values($this->byKey);
    }

    /**
     * @inheritDoc
     */
    public function exists(string $key): bool
    {
        return isset($this->byKey[$key]);
    }
}
