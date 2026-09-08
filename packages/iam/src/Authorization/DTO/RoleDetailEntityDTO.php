<?php

declare(strict_types=1);

/**
 * One role as its own page shows it: the stored row plus the permission keys
 * it grants.
 *
 * The keys are the declared vocabulary — strings are what a permission IS on
 * the check path, and a detail page shows what a person is reading, not what
 * an edge references. Storage ids stay storage's business.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Authorization\DTO;

use PHPdot\Contracts\DTO\EntityDTO;

final readonly class RoleDetailEntityDTO implements EntityDTO
{
    /**
     * @param int $id The role id — the key every edge and grant refers to
     * @param string $name The unique display name
     * @param string $description What this grouping is for
     * @param bool $is_system Reserved role (root, guest) — undeletable, host-provisioned
     * @param list<string> $grants Permission keys granted to this role
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $description,
        public bool $is_system,
        public array $grants,
    ) {}
}
