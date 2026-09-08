<?php

declare(strict_types=1);

/**
 * Seed-backed permission mirror, immutable after construction — the catalog
 * declared in code, dressed as storage so the mutation surface can validate
 * grants in the seed world exactly the way it will against tables. Ids are
 * minted deterministically in catalog order (key-sorted, so a re-scan cannot
 * renumber them); every row is live, because a key neither declared nor
 * stored cannot be wired either. sync() throws: the mirror's write half is
 * the SQL repository's, reached only through iam:permission:sync.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Authorization;

use PHPdot\Contracts\Pagination\Paginator;
use PHPdot\Iam\Authorization\Contract\PermissionCatalogInterface;
use PHPdot\Iam\Authorization\Contract\PermissionRepositoryInterface;
use PHPdot\Iam\Authorization\Discovery\DiscoveredPermission;
use PHPdot\Iam\Authorization\DTO\PermissionEntityDTO;
use PHPdot\Iam\Authorization\DTO\PermissionsFilterDTO;
use PHPdot\Iam\Authorization\DTO\PermissionsSearchDTO;
use PHPdot\Iam\Authorization\Enum\PermissionStatus;
use PHPdot\Iam\Exception\MemoryStorageException;

final readonly class SeedPermissionRepository implements PermissionRepositoryInterface
{
    /**
     * @var array<int, PermissionEntityDTO>
     */
    private array $byId;

    /**
     * @param PermissionCatalogInterface $catalog The declared vocabulary, mirrored here
     */
    public function __construct(PermissionCatalogInterface $catalog)
    {
        $declared = $catalog->all();

        usort($declared, static fn(DiscoveredPermission $a, DiscoveredPermission $b): int => $a->key <=> $b->key);

        $byId = [];
        $next = 1;

        foreach ($declared as $permission) {
            $byId[$next] = new PermissionEntityDTO(
                id: $next,
                key: $permission->key,
                name: $permission->name,
                description: $permission->description,
                is_root: $permission->root,
                status: PermissionStatus::Active,
                declared_by: $permission->declaredBy,
            );

            $next++;
        }

        $this->byId = $byId;
    }

    /**
     * @inheritDoc
     */
    public function search(PermissionsFilterDTO $filter): PermissionsSearchDTO
    {
        $matched = array_values(array_filter(
            $this->byId,
            static fn(PermissionEntityDTO $permission): bool => self::matches($permission, $filter),
        ));

        $total = count($matched);
        $offset = ($filter->page - 1) * $filter->perPage;
        $slice = array_slice($matched, $offset, $filter->perPage);

        return new PermissionsSearchDTO(
            page: new Paginator($slice, $total, $filter->perPage, $filter->page),
            sort: null,
            dir: 0,
        );
    }

    /**
     * @inheritDoc
     */
    public function find(int $id): null|PermissionEntityDTO
    {
        return $this->byId[$id] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function exists(PermissionsFilterDTO $filter): bool
    {
        foreach ($this->byId as $permission) {
            if (self::matches($permission, $filter)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function sync(array $permissions): array
    {
        throw MemoryStorageException::for('syncing the permission mirror');
    }

    /**
     * Does this permission answer this filter?
     *
     * @param PermissionEntityDTO $permission The candidate
     * @param PermissionsFilterDTO $filter The question
     *
     * @return bool
     */
    private static function matches(PermissionEntityDTO $permission, PermissionsFilterDTO $filter): bool
    {
        if ($filter->q !== null) {
            $needle = mb_strtolower($filter->q);

            if (!str_contains(mb_strtolower($permission->key), $needle)
                && !str_contains(mb_strtolower($permission->name), $needle)) {
                return false;
            }
        }

        if ($filter->ids !== [] && !in_array($permission->id, $filter->ids, true)) {
            return false;
        }

        if ($filter->exclude !== [] && in_array($permission->id, $filter->exclude, true)) {
            return false;
        }

        if ($filter->is_root !== null && $permission->is_root !== $filter->is_root) {
            return false;
        }

        return $filter->status === null || $permission->status === $filter->status;
    }
}
