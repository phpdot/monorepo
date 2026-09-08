<?php

declare(strict_types=1);

/**
 * One declared permission as the scan found it: the key (the constant's
 * value), the attribute metadata, and the declaration site for error messages
 * and admin screens. No owning app: the first segment of a key is grouping
 * convention, not a fact this record owns — an App is a framework concept,
 * and deriving one by splitting a key would be pretending a module registry
 * exists.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Authorization\Discovery;

final readonly class DiscoveredPermission
{
    /**
     * @param string $key Permission key (validated by the scan)
     * @param string $name Human display name
     * @param string $description What holding it allows
     * @param bool $root Grantable only to the reserved root role
     * @param string $declaredBy Declaration site, Class::CONST
     */
    public function __construct(
        public string $key,
        public string $name,
        public string $description,
        public bool $root,
        public string $declaredBy,
    ) {}
}
