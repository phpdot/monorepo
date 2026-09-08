<?php

declare(strict_types=1);

/**
 * Seed-backed roles and grants, immutable after construction — the memory
 * implementation for every deployment until the SQL repositories arrive.
 * Ids are minted deterministically: root is 1, guest is 2, and every seeded
 * role takes the next id in wiring order — a stable law both seeds and their
 * wiring can rely on, because nothing here can mutate and drift. The system
 * roles always exist: `root`, auto-granted the ENTIRE catalog at construction
 * ("sync" in memory form — root is data, never a code bypass), and `guest`,
 * holding exactly the grants the seeds give it. Mutations throw: an in-memory
 * write would be worker-local and lost on reload — grants live in seed wiring
 * until they live in tables.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Authorization;

use PHPdot\Contracts\Pagination\Paginator;
use PHPdot\Iam\Authorization\Contract\PermissionCatalogInterface;
use PHPdot\Iam\Authorization\Contract\RoleRepositoryInterface;
use PHPdot\Iam\Authorization\DTO\GrantSaveDTO;
use PHPdot\Iam\Authorization\DTO\RoleDetailEntityDTO;
use PHPdot\Iam\Authorization\DTO\RoleEntityDTO;
use PHPdot\Iam\Authorization\DTO\RoleLookupDTO;
use PHPdot\Iam\Authorization\DTO\RoleSaveDTO;
use PHPdot\Iam\Authorization\DTO\RolesFilterDTO;
use PHPdot\Iam\Authorization\DTO\RolesSearchDTO;
use PHPdot\Iam\Exception\DuplicateRoleException;
use PHPdot\Iam\Exception\MemoryStorageException;
use PHPdot\Iam\Exception\SystemRoleException;
use PHPdot\Iam\Exception\UnknownRoleException;

final readonly class SeedRoleRepository implements RoleRepositoryInterface
{
    private const int ROOT_ID = 1;

    private const int GUEST_ID = 2;

    /**
     * @var array<int, Role>
     */
    private array $roles;

    /**
     * @var array<string, int>
     */
    private array $idsByName;

    /**
     * @var array<int, list<string>>
     */
    private array $grants;

    /**
     * @param PermissionCatalogInterface $catalog The declared vocabulary; root receives all of it
     * @param list<Role> $roles Seeded application roles — ids are minted here, in wiring order
     * @param array<string, list<string>> $grants Seeded grants: role name => permission keys
     */
    public function __construct(PermissionCatalogInterface $catalog, array $roles = [], array $grants = [])
    {
        $byId = [
            self::ROOT_ID => new Role(self::ROOT_ID, 'root', 'Holds the entire permission catalog.', isSystem: true),
            self::GUEST_ID => new Role(self::GUEST_ID, 'guest', 'Unauthenticated visitors.', isSystem: true),
        ];

        $next = 3;

        foreach ($roles as $role) {
            if (in_array($role->name, ['root', 'guest'], true)) {
                throw SystemRoleException::reserved($role->name);
            }

            if ($this->named($role->name, $byId) !== null) {
                throw DuplicateRoleException::for($role->name);
            }

            $byId[$next] = new Role($next, $role->name, $role->description, $role->isSystem);
            $next++;
        }

        $this->roles = $byId;
        $idsByName = [];

        foreach ($byId as $id => $stored) {
            $idsByName[$stored->name] = $id;
        }

        $this->idsByName = $idsByName;

        $allKeys = array_map(static fn($permission) => $permission->key, $catalog->all());

        $resolved = [self::ROOT_ID => $allKeys];

        foreach ($grants as $name => $keys) {
            $id = $idsByName[$name] ?? throw UnknownRoleException::named($name);

            $resolved[$id] = $keys;
        }

        $this->grants = $resolved;
    }

    /**
     * @inheritDoc
     */
    public function search(RolesFilterDTO $filter): RolesSearchDTO
    {
        $matched = array_values(array_filter($this->roles, static fn(Role $role): bool => self::matches($role, $filter)));

        return new RolesSearchDTO(
            page: self::page($matched, $filter, fn(Role $role): RoleEntityDTO => new RoleEntityDTO(
                id: $role->id,
                name: $role->name,
                description: $role->description,
                is_system: $role->isSystem,
                _grants: $filter->_grants ? count($this->grants[$role->id] ?? []) : null,
            )),
            sort: null,
            dir: 0,
        );
    }

    /**
     * @inheritDoc
     */
    public function findOne(RolesFilterDTO $filter): null|RoleDetailEntityDTO
    {
        foreach ($this->roles as $role) {
            if (self::matches($role, $filter)) {
                return new RoleDetailEntityDTO(
                    id: $role->id,
                    name: $role->name,
                    description: $role->description,
                    is_system: $role->isSystem,
                    grants: $this->grants[$role->id] ?? [],
                );
            }
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function exists(RolesFilterDTO $filter): bool
    {
        foreach ($this->roles as $role) {
            if (self::matches($role, $filter)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function lookups(RolesFilterDTO $filter): array
    {
        $matched = array_filter($this->roles, static fn(Role $role): bool => self::matches($role, $filter));

        return array_map(static fn(Role $role): RoleLookupDTO => new RoleLookupDTO(
            id: $role->id,
            name: $role->name,
        ), array_values($matched));
    }

    /**
     * The minted id behind a seeded name — the law the assignment seed and
     * host wiring address roles by, because they know names, not ids.
     *
     * @param string $name A role name
     *
     * @return int|null
     */
    public function idOf(string $name): null|int
    {
        return $this->idsByName[$name] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function save(RoleSaveDTO $role): void
    {
        throw MemoryStorageException::for('saving a role');
    }

    /**
     * @inheritDoc
     */
    public function delete(int $id): void
    {
        throw MemoryStorageException::for('deleting a role');
    }

    /**
     * @inheritDoc
     */
    public function grant(GrantSaveDTO $grant): void
    {
        throw MemoryStorageException::for('granting a permission');
    }

    /**
     * @inheritDoc
     */
    public function revoke(GrantSaveDTO $grant): void
    {
        throw MemoryStorageException::for('revoking a permission');
    }

    /**
     * @inheritDoc
     */
    public function permissionsOf(int $id): array
    {
        return $this->grants[$id] ?? [];
    }

    /**
     * Does this role answer this filter?
     *
     * @param Role $role The candidate
     * @param RolesFilterDTO $filter The question
     *
     * @return bool
     */
    private static function matches(Role $role, RolesFilterDTO $filter): bool
    {
        if ($filter->q !== null && !str_contains(mb_strtolower($role->name), mb_strtolower($filter->q))) {
            return false;
        }

        if ($filter->name !== null && $role->name !== $filter->name) {
            return false;
        }

        if ($filter->ids !== [] && !in_array($role->id, $filter->ids, true)) {
            return false;
        }

        return $filter->is_system === null || $role->isSystem === $filter->is_system;
    }

    /**
     * Find a role by name in a table still being built.
     *
     * @param string $name The role name
     * @param array<int, Role> $byId The table so far
     *
     * @return Role|null
     */
    private function named(string $name, array $byId): null|Role
    {
        foreach ($byId as $role) {
            if ($role->name === $name) {
                return $role;
            }
        }

        return null;
    }

    /**
     * The matched list as one page of entities.
     *
     * @template T
     *
     * @param list<Role> $matched The roles that answered the filter
     * @param RolesFilterDTO $filter The question, for the slice
     * @param callable(Role): T $map How one role becomes one row
     *
     * @return Paginator<T>
     */
    private static function page(array $matched, RolesFilterDTO $filter, callable $map): Paginator
    {
        $total = count($matched);
        $offset = ($filter->page - 1) * $filter->perPage;
        $slice = array_slice($matched, $offset, $filter->perPage);

        return new Paginator(array_map($map, $slice), $total, $filter->perPage, $filter->page);
    }
}
