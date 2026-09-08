<?php

declare(strict_types=1);

/**
 * One assignment edge as the list shows it: who holds what, since when.
 *
 * Names are deliberately absent — an edge is two ids and a timestamp, and
 * what a user or a role is CALLED is those records' answer, composed by the
 * caller that has both searches. Carrying joined names here would publish a
 * join this object cannot guarantee and cannot change.
 *
 * The property list IS the wire row: json_encode() reads it as-is.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Authorization\DTO;

use PHPdot\Contracts\DTO\EntityDTO;

final readonly class AssignmentEntityDTO implements EntityDTO
{
    /**
     * @param int $user_id The user holding the role
     * @param int $role_id The role they hold
     * @param ?string $assigned_at When the edge was written, or null when storage does not say
     */
    public function __construct(
        public int $user_id,
        public int $role_id,
        public null|string $assigned_at = null,
    ) {}
}
