<?php

declare(strict_types=1);

namespace PHPdot\Iam\Tests\Unit\Authorization;

use PHPdot\Contracts\Pagination\Paginator;
use PHPdot\Iam\Authorization\Contract\AssignmentRepositoryInterface;
use PHPdot\Iam\Authorization\Contract\PermissionRepositoryInterface;
use PHPdot\Iam\Authorization\Contract\RoleRepositoryInterface;
use PHPdot\Iam\Authorization\DefaultRoleManager;
use PHPdot\Iam\Authorization\DTO\AssignmentSaveDTO;
use PHPdot\Iam\Authorization\DTO\AssignmentsFilterDTO;
use PHPdot\Iam\Authorization\DTO\AssignmentsSearchDTO;
use PHPdot\Iam\Authorization\DTO\GrantSaveDTO;
use PHPdot\Iam\Authorization\DTO\PermissionEntityDTO;
use PHPdot\Iam\Authorization\DTO\PermissionsFilterDTO;
use PHPdot\Iam\Authorization\DTO\PermissionsSearchDTO;
use PHPdot\Iam\Authorization\DTO\RoleDetailEntityDTO;
use PHPdot\Iam\Authorization\DTO\RoleSaveDTO;
use PHPdot\Iam\Authorization\DTO\RolesFilterDTO;
use PHPdot\Iam\Authorization\DTO\RolesSearchDTO;
use PHPdot\Iam\Authorization\Enum\PermissionStatus;
use PHPdot\Iam\Exception\DuplicateRoleException;
use PHPdot\Iam\Exception\InvalidRoleNameException;
use PHPdot\Iam\Exception\RootOnlyPermissionException;
use PHPdot\Iam\Exception\SystemRoleException;
use PHPdot\Iam\Exception\UnknownPermissionException;
use PHPdot\Iam\Exception\UnknownRoleException;
use PHPdot\Iam\Identities\Contract\Identity\IdentityInterface;
use PHPdot\Iam\Identities\Types\UserIdentity;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The sanctioned mutation surface: every mutation validates before it
 * persists — mirror membership, life-stage, root-only flags, system-role
 * protection, role and name existence — and everything is addressed by id.
 * Auditing is deliberately absent: the future audit package observes from
 * outside the contract.
 */
final class DefaultRoleManagerTest extends TestCase
{
    #[Test]
    public function savingANewRolePersists(): void
    {
        [$manager, $roles] = $this->world();

        $manager->saveRole(new RoleSaveDTO(id: null, name: 'hr-editor', description: 'Edits employees.'));

        self::assertSame('hr-editor', $roles->findOne(new RolesFilterDTO(name: 'hr-editor'))?->name);
    }

    #[Test]
    public function updatingARoleRenamesIt(): void
    {
        [$manager, $roles] = $this->world();

        $manager->saveRole(new RoleSaveDTO(id: null, name: 'hr-editor'));
        $stored = $roles->findOne(new RolesFilterDTO(name: 'hr-editor'));

        $manager->saveRole(new RoleSaveDTO(id: $stored?->id, name: 'hr-manager'));

        self::assertNull($roles->findOne(new RolesFilterDTO(name: 'hr-editor')));
        self::assertSame('hr-manager', $roles->findOne(new RolesFilterDTO(name: 'hr-manager'))?->name);
    }

    #[Test]
    public function renamingOntoAnExistingNameIsRefused(): void
    {
        [$manager, $roles] = $this->world();

        $manager->saveRole(new RoleSaveDTO(id: null, name: 'hr-editor'));
        $manager->saveRole(new RoleSaveDTO(id: null, name: 'site-editor'));
        $stored = $roles->findOne(new RolesFilterDTO(name: 'hr-editor'));

        $this->expectException(DuplicateRoleException::class);

        $manager->saveRole(new RoleSaveDTO(id: $stored?->id, name: 'site-editor'));
    }

    #[Test]
    public function updatingASystemRoleIsRefused(): void
    {
        [$manager] = $this->world();

        $this->expectException(SystemRoleException::class);

        $manager->saveRole(new RoleSaveDTO(id: 1, name: 'root', description: 'An impostor.'));
    }

    #[Test]
    public function updatingAnUnknownRoleIsRefused(): void
    {
        [$manager] = $this->world();

        $this->expectException(UnknownRoleException::class);

        $manager->saveRole(new RoleSaveDTO(id: 99, name: 'ghost'));
    }

    #[Test]
    public function aNameOutsideTheLawIsRefused(): void
    {
        [$manager] = $this->world();

        $this->expectException(InvalidRoleNameException::class);

        $manager->saveRole(new RoleSaveDTO(id: null, name: ' padded'));
    }

    #[Test]
    public function deletingASystemRoleIsRefused(): void
    {
        [$manager, $roles] = $this->world();

        $this->expectException(SystemRoleException::class);

        try {
            $manager->deleteRole(1);
        } finally {
            self::assertNotNull($roles->findOne(new RolesFilterDTO(ids: [1])));
        }
    }

    #[Test]
    public function deletingAnUnknownRoleIsRefused(): void
    {
        [$manager] = $this->world();

        $this->expectException(UnknownRoleException::class);

        $manager->deleteRole(99);
    }

    #[Test]
    public function grantingAnUnknownPermissionIsRefused(): void
    {
        [$manager, $roles] = $this->world();

        $roleId = $this->mutableRole($manager, $roles);

        $this->expectException(UnknownPermissionException::class);

        try {
            $manager->grant(new GrantSaveDTO(role_id: $roleId, permission_id: 99));
        } finally {
            self::assertSame([], $roles->permissionsOf($roleId));
        }
    }

    #[Test]
    public function grantingAnOrphanedPermissionIsRefused(): void
    {
        [$manager, $roles] = $this->world();

        $roleId = $this->mutableRole($manager, $roles);

        $this->expectException(UnknownPermissionException::class);
        $this->expectExceptionMessage('old.gone.key');

        try {
            $manager->grant(new GrantSaveDTO(role_id: $roleId, permission_id: 3));
        } finally {
            self::assertSame([], $roles->permissionsOf($roleId));
        }
    }

    #[Test]
    public function grantingARootOnlyPermissionToAnOrdinaryRoleIsRefused(): void
    {
        [$manager, $roles] = $this->world();

        $roleId = $this->mutableRole($manager, $roles);

        $this->expectException(RootOnlyPermissionException::class);

        $manager->grant(new GrantSaveDTO(role_id: $roleId, permission_id: 2));
    }

    #[Test]
    public function grantingToAnUnknownRoleIsRefused(): void
    {
        [$manager] = $this->world();

        $this->expectException(UnknownRoleException::class);

        $manager->grant(new GrantSaveDTO(role_id: 99, permission_id: 1));
    }

    #[Test]
    public function grantingToASystemRoleIsRefused(): void
    {
        [$manager, $roles] = $this->world();

        $this->expectException(SystemRoleException::class);

        try {
            $manager->grant(new GrantSaveDTO(role_id: 1, permission_id: 1));
        } finally {
            self::assertSame([], $roles->permissionsOf(1));
        }
    }

    #[Test]
    public function aValidGrantPersists(): void
    {
        [$manager, $roles] = $this->world();

        $roleId = $this->mutableRole($manager, $roles);
        $manager->grant(new GrantSaveDTO(role_id: $roleId, permission_id: 1));

        self::assertSame(['hr.employee.edit'], $roles->permissionsOf($roleId));
    }

    #[Test]
    public function revokePersists(): void
    {
        [$manager, $roles] = $this->world();

        $roleId = $this->mutableRole($manager, $roles);
        $manager->grant(new GrantSaveDTO(role_id: $roleId, permission_id: 1));
        $manager->revoke(new GrantSaveDTO(role_id: $roleId, permission_id: 1));

        self::assertSame([], $roles->permissionsOf($roleId));
    }

    #[Test]
    public function revokingAnUnknownPermissionIsRefused(): void
    {
        [$manager, $roles] = $this->world();

        $roleId = $this->mutableRole($manager, $roles);

        $this->expectException(UnknownPermissionException::class);

        $manager->revoke(new GrantSaveDTO(role_id: $roleId, permission_id: 99));
    }

    #[Test]
    public function revokingFromASystemRoleIsRefused(): void
    {
        [$manager] = $this->world();

        $this->expectException(SystemRoleException::class);

        $manager->revoke(new GrantSaveDTO(role_id: 2, permission_id: 1));
    }

    #[Test]
    public function assignAndUnassignPersist(): void
    {
        [$manager, $roles, $assignments] = $this->world();

        $roleId = $this->mutableRole($manager, $roles);
        $manager->assign(new AssignmentSaveDTO(new UserIdentity(42), $roleId));

        self::assertSame([$roleId], $assignments->rolesFor(new UserIdentity(42)));

        $manager->unassign(new AssignmentSaveDTO(new UserIdentity(42), $roleId));

        self::assertSame([], $assignments->rolesFor(new UserIdentity(42)));
    }

    #[Test]
    public function assigningASystemRoleIsRefused(): void
    {
        [$manager, , $assignments] = $this->world();

        $this->expectException(SystemRoleException::class);

        try {
            $manager->assign(new AssignmentSaveDTO(new UserIdentity(42), 1));
        } finally {
            self::assertSame([], $assignments->rolesFor(new UserIdentity(42)));
        }
    }

    #[Test]
    public function unassigningASystemRoleIsRefused(): void
    {
        [$manager] = $this->world();

        $this->expectException(SystemRoleException::class);

        $manager->unassign(new AssignmentSaveDTO(new UserIdentity(42), 1));
    }

    #[Test]
    public function assigningAnUnknownRoleIsRefused(): void
    {
        [$manager] = $this->world();

        $this->expectException(UnknownRoleException::class);

        $manager->assign(new AssignmentSaveDTO(new UserIdentity(42), 99));
    }

    /**
     * A saved mutable role, and its id.
     *
     * @param DefaultRoleManager $manager The mutation surface
     * @param RolesDouble $roles The storage double it persists through
     *
     * @return int
     */
    private function mutableRole(DefaultRoleManager $manager, RolesDouble $roles): int
    {
        $manager->saveRole(new RoleSaveDTO(id: null, name: 'hr-editor'));

        return $roles->findOne(new RolesFilterDTO(name: 'hr-editor'))?->id ?? 0;
    }

    /**
     * @return array{DefaultRoleManager, RolesDouble, AssignmentsDouble}
     */
    private function world(): array
    {
        $roles = new RolesDouble();
        $assignments = new AssignmentsDouble();

        return [new DefaultRoleManager($roles, $assignments, new PermissionsDouble()), $roles, $assignments];
    }
}

final class RolesDouble implements RoleRepositoryInterface
{
    /** @var array<int, array{name: string, description: string, is_system: bool}> */
    public array $roles = [
        1 => ['name' => 'root', 'description' => 'Holds the entire catalog.', 'is_system' => true],
        2 => ['name' => 'guest', 'description' => 'Unauthenticated visitors.', 'is_system' => true],
    ];

    /** @var array<int, list<int>> */
    public array $grants = [];

    private int $next = 3;

    public function search(RolesFilterDTO $filter): RolesSearchDTO
    {
        return new RolesSearchDTO(new Paginator([], 0, 20, 1), null, 0);
    }

    public function findOne(RolesFilterDTO $filter): null|RoleDetailEntityDTO
    {
        foreach ($this->roles as $id => $role) {
            if (($filter->name === null || $role['name'] === $filter->name)
                && ($filter->ids === [] || in_array($id, $filter->ids, true))) {
                return new RoleDetailEntityDTO(
                    $id,
                    $role['name'],
                    $role['description'],
                    $role['is_system'],
                    array_map(
                        static fn(int $permissionId): string => PermissionsDouble::KEYS[$permissionId],
                        $this->grants[$id] ?? [],
                    ),
                );
            }
        }

        return null;
    }

    public function exists(RolesFilterDTO $filter): bool
    {
        return $this->findOne($filter) !== null;
    }

    public function lookups(RolesFilterDTO $filter): array
    {
        return [];
    }

    public function save(RoleSaveDTO $role): void
    {
        if ($role->id === null) {
            $this->roles[$this->next++] = ['name' => $role->name, 'description' => $role->description, 'is_system' => false];

            return;
        }

        $stored = $this->roles[$role->id] ?? throw UnknownRoleException::for($role->id);

        $this->roles[$role->id] = ['name' => $role->name, 'description' => $role->description, 'is_system' => $stored['is_system']];
    }

    public function delete(int $id): void
    {
        unset($this->roles[$id], $this->grants[$id]);
    }

    public function grant(GrantSaveDTO $grant): void
    {
        $keys = $this->grants[$grant->role_id] ?? [];
        $keys[] = $grant->permission_id;
        $this->grants[$grant->role_id] = array_values(array_unique($keys));
    }

    public function revoke(GrantSaveDTO $grant): void
    {
        $this->grants[$grant->role_id] = array_values(array_diff($this->grants[$grant->role_id] ?? [], [$grant->permission_id]));
    }

    public function permissionsOf(int $id): array
    {
        return array_map(
            static fn(int $permissionId): string => PermissionsDouble::KEYS[$permissionId],
            $this->grants[$id] ?? [],
        );
    }
}

final class PermissionsDouble implements PermissionRepositoryInterface
{
    /** @var array<int, string> */
    public const KEYS = [
        1 => 'hr.employee.edit',
        2 => 'system.server.restart',
        3 => 'old.gone.key',
    ];

    public function find(int $id): null|PermissionEntityDTO
    {
        if (!isset(self::KEYS[$id])) {
            return null;
        }

        return new PermissionEntityDTO(
            $id,
            self::KEYS[$id],
            'Name',
            '',
            $id === 2,
            $id === 3 ? PermissionStatus::Orphaned : PermissionStatus::Active,
            'Fixture::KEY',
        );
    }

    public function search(PermissionsFilterDTO $filter): PermissionsSearchDTO
    {
        return new PermissionsSearchDTO(new Paginator([], 0, 20, 1), null, 0);
    }

    public function exists(PermissionsFilterDTO $filter): bool
    {
        return false;
    }

    public function sync(array $permissions): array
    {
        return ['added' => [], 'updated' => [], 'orphaned' => []];
    }
}

final class AssignmentsDouble implements AssignmentRepositoryInterface
{
    /** @var array<string, list<int>> */
    public array $held = [];

    public function rolesFor(IdentityInterface $identity): array
    {
        return $this->held[$identity->type() . ':' . (string) ($identity->id() ?? '')] ?? [];
    }

    public function search(AssignmentsFilterDTO $filter): AssignmentsSearchDTO
    {
        return new AssignmentsSearchDTO(new Paginator([], 0, 20, 1), null, 0);
    }

    public function assign(AssignmentSaveDTO $assignment): void
    {
        $key = $assignment->identity->type() . ':' . (string) ($assignment->identity->id() ?? '');
        $roles = $this->held[$key] ?? [];
        $roles[] = $assignment->role_id;
        $this->held[$key] = array_values(array_unique($roles));
    }

    public function unassign(AssignmentSaveDTO $assignment): void
    {
        $key = $assignment->identity->type() . ':' . (string) ($assignment->identity->id() ?? '');
        $this->held[$key] = array_values(array_diff($this->held[$key] ?? [], [$assignment->role_id]));
    }
}
