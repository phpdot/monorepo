<?php

declare(strict_types=1);

/**
 * The sanctioned mutation surface. Every mutation validates first — role
 * existence, system-role protection, mirror membership, root-only flags,
 * life-stage — then persists through the repositories. Validation reads the
 * MIRROR, not the catalog: a grant addresses a mirrored row by id, so the
 * row is what existence, root-only and orphaning are read from — one lookup
 * per check, not a catalog scan. Stateless by design: auditing is NOT this
 * class's job — the future audit package observes mutations from outside (a
 * decorator on the RoleManagerInterface contract, or platform events), with
 * zero coupling here.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Authorization;

use PHPdot\Container\Attribute\Binds;
use PHPdot\Container\Attribute\Scoped;
use PHPdot\Iam\Authorization\Contract\AssignmentRepositoryInterface;
use PHPdot\Iam\Authorization\Contract\PermissionRepositoryInterface;
use PHPdot\Iam\Authorization\Contract\RoleManagerInterface;
use PHPdot\Iam\Authorization\Contract\RoleRepositoryInterface;
use PHPdot\Iam\Authorization\DTO\AssignmentSaveDTO;
use PHPdot\Iam\Authorization\DTO\GrantSaveDTO;
use PHPdot\Iam\Authorization\DTO\RoleDetailEntityDTO;
use PHPdot\Iam\Authorization\DTO\RoleSaveDTO;
use PHPdot\Iam\Authorization\DTO\RolesFilterDTO;
use PHPdot\Iam\Authorization\Enum\PermissionStatus;
use PHPdot\Iam\Exception\DuplicateRoleException;
use PHPdot\Iam\Exception\RootOnlyPermissionException;
use PHPdot\Iam\Exception\SystemRoleException;
use PHPdot\Iam\Exception\UnknownPermissionException;
use PHPdot\Iam\Exception\UnknownRoleException;

#[Scoped]
#[Binds(RoleManagerInterface::class)]
final readonly class DefaultRoleManager implements RoleManagerInterface
{
    /**
     * @param RoleRepositoryInterface $roles role ↔ grant storage
     * @param AssignmentRepositoryInterface $assignments user ↔ role storage
     * @param PermissionRepositoryInterface $permissions the mirrored vocabulary — grants validate against it
     */
    public function __construct(
        private RoleRepositoryInterface $roles,
        private AssignmentRepositoryInterface $assignments,
        private PermissionRepositoryInterface $permissions,
    ) {}

    /**
     * @inheritDoc
     */
    public function saveRole(RoleSaveDTO $role): void
    {
        Role::assertName($role->name);

        if (in_array($role->name, ['root', 'guest'], true)) {
            throw SystemRoleException::reserved($role->name);
        }

        if ($role->id === null) {
            if ($this->roles->exists(new RolesFilterDTO(name: $role->name))) {
                throw DuplicateRoleException::for($role->name);
            }

            $this->roles->save($role);

            return;
        }

        $stored = $this->requireStoredRole($role->id);

        if ($stored->is_system) {
            throw SystemRoleException::immutable($stored->name);
        }

        if ($role->name !== $stored->name && $this->roles->exists(new RolesFilterDTO(name: $role->name))) {
            throw DuplicateRoleException::for($role->name);
        }

        $this->roles->save($role);
    }

    /**
     * @inheritDoc
     */
    public function deleteRole(int $id): void
    {
        $stored = $this->requireStoredRole($id);

        if ($stored->is_system) {
            throw SystemRoleException::undeletable($stored->name);
        }

        $this->roles->delete($id);
    }

    /**
     * @inheritDoc
     */
    public function grant(GrantSaveDTO $grant): void
    {
        $role = $this->requireMutableRole($grant->role_id);
        $permission = $this->permissions->find($grant->permission_id)
            ?? throw UnknownPermissionException::forId($grant->permission_id);

        if ($permission->status === PermissionStatus::Orphaned) {
            throw UnknownPermissionException::forOrphanedKey($permission->key);
        }

        if ($permission->is_root) {
            throw RootOnlyPermissionException::forGrant($permission->key, $role->name);
        }

        $this->roles->grant($grant);
    }

    /**
     * @inheritDoc
     */
    public function revoke(GrantSaveDTO $grant): void
    {
        $this->requireMutableRole($grant->role_id);

        if ($this->permissions->find($grant->permission_id) === null) {
            throw UnknownPermissionException::forId($grant->permission_id);
        }

        $this->roles->revoke($grant);
    }

    /**
     * @inheritDoc
     */
    public function assign(AssignmentSaveDTO $assignment): void
    {
        $this->requireAssignableRole($assignment->role_id);

        $this->assignments->assign($assignment);
    }

    /**
     * @inheritDoc
     */
    public function unassign(AssignmentSaveDTO $assignment): void
    {
        $this->requireAssignableRole($assignment->role_id);

        $this->assignments->unassign($assignment);
    }

    /**
     * The role must exist.
     *
     * @param int $id Role id
     *
     * @return RoleDetailEntityDTO
     */
    private function requireStoredRole(int $id): RoleDetailEntityDTO
    {
        return $this->roles->findOne(new RolesFilterDTO(ids: [$id]))
            ?? throw UnknownRoleException::for($id);
    }

    /**
     * The role must exist and take grants through the mutation surface —
     * system roles hold exactly what the host provisioned.
     *
     * @param int $id Role id
     *
     * @return RoleDetailEntityDTO
     */
    private function requireMutableRole(int $id): RoleDetailEntityDTO
    {
        $role = $this->requireStoredRole($id);

        if ($role->is_system) {
            throw SystemRoleException::immutable($role->name);
        }

        return $role;
    }

    /**
     * The role must exist and be attachable to an identity — root is implied
     * by the catalog, guest by the absence of authentication.
     *
     * @param int $id Role id
     *
     * @return void
     */
    private function requireAssignableRole(int $id): void
    {
        $role = $this->requireStoredRole($id);

        if ($role->is_system) {
            throw SystemRoleException::notAssignable($role->name);
        }
    }
}
