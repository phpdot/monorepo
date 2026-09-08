<?php

declare(strict_types=1);

/**
 * The declared permission vocabulary — the discovery output, queryable. The
 * catalog is immutable after boot (built from the scan, inherited by workers)
 * and answers "does this key exist" for dev-mode typo warnings, the CLI, and
 * admin screens.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Authorization\Contract;

use PHPdot\Iam\Authorization\Discovery\DiscoveredPermission;

interface PermissionCatalogInterface
{
    /**
     * Every declared permission.
     *
     * @return list<DiscoveredPermission>
     */
    public function all(): array;

    /**
     * Whether a key is declared.
     *
     * @param string $key Permission key
     *
     * @return bool
     */
    public function exists(string $key): bool;
}
