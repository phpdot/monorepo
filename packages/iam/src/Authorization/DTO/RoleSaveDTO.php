<?php

declare(strict_types=1);

/**
 * One role on its way IN — the values a save will write.
 *
 * The write counterpart of RoleEntityDTO, and a different shape on purpose:
 * no `is_system`, because reserved roles are host-provisioned and never
 * arrive through a save, and no computed fields, because nothing is stored
 * yet.
 *
 * `id` separates an insert from an update, and does so as data rather than
 * as a flag: null is the literal truth that no row exists yet, not a
 * convention meaning "add". The name is the one human identifier and stays
 * unique — uniqueness is enforced where the row lives, not here.
 *
 * A CARRIER, nothing more: it is built by the caller that read the intent
 * (a CLI argument list, a request body), and validation — format, duplicates,
 * system-role protection — is the mutation surface's job, which rejects
 * rather than degrades.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Authorization\DTO;

use PHPdot\Contracts\DTO\SaveDTO;

final readonly class RoleSaveDTO implements SaveDTO
{
    /**
     * @param ?int $id The stored row, or null when there is not one yet
     * @param string $name The unique role name
     * @param string $description What this grouping is for
     */
    public function __construct(
        public null|int $id,
        public string $name,
        public string $description = '',
    ) {}
}
