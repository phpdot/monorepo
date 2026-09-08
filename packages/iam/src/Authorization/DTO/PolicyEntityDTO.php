<?php

declare(strict_types=1);

/**
 * One mirrored policy as the list shows it: the rule class and the resource
 * it judges.
 *
 * Policies are CODE; the mirror is an inventory so the admin surface reads
 * data, never reflection. Edgeless — no other row references a policy — so
 * its life is simpler than a permission's: a class that vanishes from code
 * is removed here, not orphaned.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Authorization\DTO;

use PHPdot\Contracts\DTO\EntityDTO;

final readonly class PolicyEntityDTO implements EntityDTO
{
    /**
     * @param int $id The mirrored policy id
     * @param string $policy The policy class — what can() runs
     * @param string $resource The resource type it judges
     */
    public function __construct(
        public int $id,
        public string $policy,
        public string $resource,
    ) {}
}
