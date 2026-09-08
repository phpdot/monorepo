<?php

declare(strict_types=1);

/**
 * Storage seam for identity ↔ role assignments. rolesFor serves the check
 * path and answers role IDS — names are what a person reads, ids are what
 * the edges reference. The search serves the admin surface and takes a
 * filter like every other list. Mutations are RoleManagerInterface's
 * persistence half, real only in the SQL implementation (login milestone) —
 * the seed-backed memory one is read-only by design.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Authorization\Contract;

use PHPdot\Iam\Authorization\DTO\AssignmentSaveDTO;
use PHPdot\Iam\Authorization\DTO\AssignmentsFilterDTO;
use PHPdot\Iam\Authorization\DTO\AssignmentsSearchDTO;
use PHPdot\Iam\Identities\Contract\Identity\IdentityInterface;

interface AssignmentRepositoryInterface
{
    /**
     * Role ids assigned to this identity.
     *
     * @param IdentityInterface $identity The identity
     *
     * @return list<int>
     */
    public function rolesFor(IdentityInterface $identity): array;

    /**
     * One page of assignment edges matching a filter.
     *
     * @param AssignmentsFilterDTO $filter The validated question
     *
     * @return AssignmentsSearchDTO
     */
    public function search(AssignmentsFilterDTO $filter): AssignmentsSearchDTO;

    /**
     * Persist an assignment edge.
     *
     * @param AssignmentSaveDTO $assignment The edge, identity and role id
     *
     * @return void
     */
    public function assign(AssignmentSaveDTO $assignment): void;

    /**
     * Remove an assignment edge.
     *
     * @param AssignmentSaveDTO $assignment The edge, identity and role id
     *
     * @return void
     */
    public function unassign(AssignmentSaveDTO $assignment): void;
}
