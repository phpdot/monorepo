<?php

declare(strict_types=1);

/**
 * A role as another screen refers to it: who, by id.
 *
 * Two fields, against the entities' four and five, and the difference is a
 * CONTRACT rather than a saving. A picker naming the roles an identity may
 * take has no use for the description or the grant list, and handing them
 * over anyway publishes internals that then cannot be changed without
 * breaking whoever started reading them.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Authorization\DTO;

use PHPdot\Contracts\DTO\EntityDTO;

final readonly class RoleLookupDTO implements EntityDTO
{
    /**
     * @param int $id The role id — what an assignment edge carries
     * @param string $name The name a person reads
     */
    public function __construct(
        public int $id,
        public string $name,
    ) {}
}
