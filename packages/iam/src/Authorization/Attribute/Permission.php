<?php

declare(strict_types=1);

/**
 * Declares one permission on a catalog class constant — the constant's VALUE
 * is the key (single source of truth), the attribute carries the metadata
 * that admin screens and the CLI display. App code references the constant
 * (`Permissions::EmployeeEdit`), so a mistyped permission is a PHPStan error,
 * not a silent deny. Discovered by the iam scan subprocess; a malformed key
 * or a duplicate declaration fails the scan, never a runtime check.
 *
 * `root: true` marks a permission auto-granted to the reserved root role by
 * sync and grantable to no other role.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Authorization\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
final readonly class Permission
{
    /**
     * @param string $name Human display name
     * @param string $description What holding this permission allows
     * @param bool $root Grantable only to the reserved root role
     */
    public function __construct(
        public string $name,
        public string $description = '',
        public bool $root = false,
    ) {}
}
