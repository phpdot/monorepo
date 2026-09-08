<?php

declare(strict_types=1);

/**
 * Storage seam for roles and their grants. Reads serve the CLI and admin
 * screens and take a filter — a list without a question does not exist, and
 * an unfiltered list is the empty filter. Mutation methods are the
 * persistence half of RoleManagerInterface — implemented by the SQL
 * repositories at the login milestone. Roles are addressed by id everywhere:
 * the name is unique data a person reads, never an address. The seed-backed
 * memory implementation is deliberately read-only: an in-memory "mutation"
 * would be worker-local and lost on reload — a mirage this package refuses
 * to sell (the Casbin-memory CLI lesson).
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Authorization\Contract;

use PHPdot\Iam\Authorization\DTO\GrantSaveDTO;
use PHPdot\Iam\Authorization\DTO\RoleDetailEntityDTO;
use PHPdot\Iam\Authorization\DTO\RoleLookupDTO;
use PHPdot\Iam\Authorization\DTO\RoleSaveDTO;
use PHPdot\Iam\Authorization\DTO\RolesFilterDTO;
use PHPdot\Iam\Authorization\DTO\RolesSearchDTO;

interface RoleRepositoryInterface
{
    /**
     * One page of roles matching a filter.
     *
     * @param RolesFilterDTO $filter The validated question
     *
     * @return RolesSearchDTO
     */
    public function search(RolesFilterDTO $filter): RolesSearchDTO;

    /**
     * The first role matching a filter, with its grants.
     *
     * The same filter search() pages, narrowed to one row — the detail page
     * wants the stored role AND what it grants in one answer.
     *
     * @param RolesFilterDTO $filter The validated question
     *
     * @return RoleDetailEntityDTO|null
     */
    public function findOne(RolesFilterDTO $filter): null|RoleDetailEntityDTO;

    /**
     * Is there a role matching a filter?
     *
     * The same question the other reads take, answered yes or no.
     *
     * @param RolesFilterDTO $filter The validated question
     *
     * @return bool
     */
    public function exists(RolesFilterDTO $filter): bool;

    /**
     * The roles a picker is asking for: who, by id.
     *
     * A list and not a page, because a picker draws every row it gets and
     * nothing else — the filter's perPage stays the ceiling, so an unbounded
     * ask cannot become an unbounded answer.
     *
     * @param RolesFilterDTO $filter The validated question
     *
     * @return list<RoleLookupDTO>
     */
    public function lookups(RolesFilterDTO $filter): array;

    /**
     * Persist a new or updated role. A null id inserts, a set id updates.
     *
     * @param RoleSaveDTO $role The values to write
     *
     * @return void
     */
    public function save(RoleSaveDTO $role): void;

    /**
     * Delete a role and its edges.
     *
     * @param int $id Role id
     *
     * @return void
     */
    public function delete(int $id): void;

    /**
     * Persist a grant edge.
     *
     * @param GrantSaveDTO $grant The edge, both ends by id
     *
     * @return void
     */
    public function grant(GrantSaveDTO $grant): void;

    /**
     * Remove a grant edge.
     *
     * @param GrantSaveDTO $grant The edge, both ends by id
     *
     * @return void
     */
    public function revoke(GrantSaveDTO $grant): void;

    /**
     * Permission keys granted to a role.
     *
     * Keys, not ids, because this read serves the check path — the one place
     * a permission is addressed by what code declares it.
     *
     * @param int $id Role id
     *
     * @return list<string>
     */
    public function permissionsOf(int $id): array;
}
