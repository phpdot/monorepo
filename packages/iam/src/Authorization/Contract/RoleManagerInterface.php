<?php

declare(strict_types=1);

/**
 * The sanctioned mutation surface for roles, grants, and assignments — the
 * only place authorization data changes. Every mutation validates first
 * (mirror membership, root-only flags, life-stage, system-role protection,
 * duplicate names) and persists through the repositories; ids address
 * everything — a name is data, never an address. System roles are
 * structural: they are never created, saved, granted, revoked, or assigned
 * here — the host provisions them. Auditing observes from outside the
 * contract (a decorator, or platform events), never from inside it.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Authorization\Contract;

use PHPdot\Iam\Authorization\DTO\AssignmentSaveDTO;
use PHPdot\Iam\Authorization\DTO\GrantSaveDTO;
use PHPdot\Iam\Authorization\DTO\RoleSaveDTO;

interface RoleManagerInterface
{
    /**
     * Save a role. A null id creates — refusing a name that already exists
     * (creation is never an upsert) and names that fail the format law; a
     * set id updates — refusing unknown ids and system roles, whose shape
     * the host owns.
     *
     * @param RoleSaveDTO $role The values to write
     *
     * @return void
     */
    public function saveRole(RoleSaveDTO $role): void;

    /**
     * Delete a role — refuses system roles.
     *
     * @param int $id Role id
     *
     * @return void
     */
    public function deleteRole(int $id): void;

    /**
     * Grant a permission to a role. Validates the mirrored permission
     * exists and is still declared — granting an orphaned key is refused,
     * because no code path will ever check it — refuses root-flagged
     * permissions (they belong to root alone) and refuses system roles —
     * their grants are host-provisioned.
     *
     * @param GrantSaveDTO $grant The edge, both ends by id
     *
     * @return void
     */
    public function grant(GrantSaveDTO $grant): void;

    /**
     * Revoke a permission from a role — refuses system roles.
     *
     * @param GrantSaveDTO $grant The edge, both ends by id
     *
     * @return void
     */
    public function revoke(GrantSaveDTO $grant): void;

    /**
     * Assign a role to an identity — refuses system roles: root is implied
     * by the catalog, guest by the absence of authentication.
     *
     * @param AssignmentSaveDTO $assignment The edge, identity and role id
     *
     * @return void
     */
    public function assign(AssignmentSaveDTO $assignment): void;

    /**
     * Remove a role from an identity — refuses system roles.
     *
     * @param AssignmentSaveDTO $assignment The edge, identity and role id
     *
     * @return void
     */
    public function unassign(AssignmentSaveDTO $assignment): void;
}
