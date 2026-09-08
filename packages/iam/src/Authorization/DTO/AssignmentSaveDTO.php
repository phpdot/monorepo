<?php

declare(strict_types=1);

/**
 * One assignment edge on its way IN or OUT: the identity, the role, the role
 * by id.
 *
 * assign() and unassign() share this carrier for the same reason the grant
 * pair shares one: both address the same edge. The identity stays the
 * identity contract rather than a bare user id because the check path and
 * the mutation path speak the same "who" — the storage seam narrows it to a
 * user id where the edge lives, and refuses anything it cannot store.
 *
 * A CARRIER, nothing more: role existence, system-role protection and
 * assignability are the mutation surface's job, which rejects rather than
 * degrades.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Authorization\DTO;

use PHPdot\Contracts\DTO\SaveDTO;
use PHPdot\Iam\Identities\Contract\Identity\IdentityInterface;

final readonly class AssignmentSaveDTO implements SaveDTO
{
    /**
     * @param IdentityInterface $identity The identity taking the role
     * @param int $role_id The role being assigned
     */
    public function __construct(
        public IdentityInterface $identity,
        public int $role_id,
    ) {}
}
