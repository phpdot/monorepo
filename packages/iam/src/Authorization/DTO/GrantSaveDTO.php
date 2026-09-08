<?php

declare(strict_types=1);

/**
 * One grant edge on its way IN or OUT: the role, the permission, both by id.
 *
 * grant() and revoke() share this carrier because they address the same edge
 * from both sides — there is nothing to write but the pair, and a revoke is
 * not a different fact about it. No names, no keys: the role is a stored row
 * and the permission a mirrored one, and both are addressed the way their
 * edges reference them.
 *
 * A CARRIER, nothing more: catalog membership, root-only flags and
 * system-role protection are the mutation surface's job, which rejects
 * rather than degrades.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Authorization\DTO;

use PHPdot\Contracts\DTO\SaveDTO;

final readonly class GrantSaveDTO implements SaveDTO
{
    /**
     * @param int $role_id The role taking the grant
     * @param int $permission_id The mirrored permission being granted
     */
    public function __construct(
        public int $role_id,
        public int $permission_id,
    ) {}
}
