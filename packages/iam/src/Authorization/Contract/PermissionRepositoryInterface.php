<?php

declare(strict_types=1);

/**
 * Storage seam for the permission mirror: the write half for
 * iam:permission:sync, the read half for every list screen and for grant
 * validation. Code is the SOURCE OF TRUTH for the vocabulary; storage is the
 * admin-visible mirror, addressed by id — a grant edge references the
 * mirrored row, never the key string. sync() upserts every declared key,
 * marks keys present in storage but no longer declared as orphaned (never
 * deletes — grants referencing them keep failing closed), guarantees the
 * system roles exist, and keeps root granted the ENTIRE active catalog —
 * root is data, maintained by sync, never a code branch.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Authorization\Contract;

use PHPdot\Iam\Authorization\Discovery\DiscoveredPermission;
use PHPdot\Iam\Authorization\DTO\PermissionEntityDTO;
use PHPdot\Iam\Authorization\DTO\PermissionsFilterDTO;
use PHPdot\Iam\Authorization\DTO\PermissionsSearchDTO;

interface PermissionRepositoryInterface
{
    /**
     * One page of mirrored permissions matching a filter.
     *
     * @param PermissionsFilterDTO $filter The validated question
     *
     * @return PermissionsSearchDTO
     */
    public function search(PermissionsFilterDTO $filter): PermissionsSearchDTO;

    /**
     * One mirrored permission by id, or null when absent.
     *
     * Grant validation reads here: existence, the root-only flag and the
     * life-stage all live on the row a grant would reference.
     *
     * @param int $id Mirrored permission id
     *
     * @return PermissionEntityDTO|null
     */
    public function find(int $id): null|PermissionEntityDTO;

    /**
     * Is there a permission matching a filter?
     *
     * @param PermissionsFilterDTO $filter The validated question
     *
     * @return bool
     */
    public function exists(PermissionsFilterDTO $filter): bool;

    /**
     * Mirror the declared vocabulary into storage.
     *
     * @param list<DiscoveredPermission> $permissions The scan output
     *
     * @return array{added: list<string>, updated: list<string>, orphaned: list<string>}
     */
    public function sync(array $permissions): array;
}
