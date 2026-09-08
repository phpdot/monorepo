<?php

declare(strict_types=1);

/**
 * What an identity holds, read from the assignment and role seams: the role
 * IDS the assignments carry, and the permission KEYS those roles grant — the
 * one currency a check understands. Keys are deduplicated across roles:
 * possession is a set, and a key granted twice is still held once.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Authorization;

use PHPdot\Iam\Authorization\Contract\AssignmentRepositoryInterface;
use PHPdot\Iam\Authorization\Contract\PermissionProviderInterface;
use PHPdot\Iam\Authorization\Contract\RoleRepositoryInterface;
use PHPdot\Iam\Identities\Contract\Identity\IdentityInterface;

final readonly class RepositoryPermissionProvider implements PermissionProviderInterface
{
    public function __construct(
        private AssignmentRepositoryInterface $assignments,
        private RoleRepositoryInterface $roles,
    ) {}

    /**
     * @inheritDoc
     */
    public function permissionsFor(IdentityInterface $identity): array
    {
        $permissions = [];

        foreach ($this->rolesFor($identity) as $roleId) {
            foreach ($this->roles->permissionsOf($roleId) as $key) {
                $permissions[$key] = true;
            }
        }

        return array_keys($permissions);
    }

    /**
     * @inheritDoc
     */
    public function rolesFor(IdentityInterface $identity): array
    {
        return $this->assignments->rolesFor($identity);
    }
}
