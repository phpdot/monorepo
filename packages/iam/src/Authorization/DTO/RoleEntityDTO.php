<?php

declare(strict_types=1);

/**
 * One role as the list shows it.
 *
 * Stored values only — `is_system` comes back as a boolean, and whether a
 * system role renders with a lock is the screen's decision, made client-side.
 * The name is DATA here, never an address: roles are identified by id, and
 * the name is what a person reads.
 *
 * The property list IS the wire row: json_encode() reads it as-is, so
 * renaming a property here renames a key on every screen that draws one.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Authorization\DTO;

use PHPdot\Contracts\DTO\EntityDTO;

final readonly class RoleEntityDTO implements EntityDTO
{
    /**
     * @param int $id The role id — the key every edge and grant refers to
     * @param string $name The unique display name
     * @param string $description What this grouping is for
     * @param bool $is_system Reserved role (root, guest) — undeletable, host-provisioned
     *
     * A leading underscore marks a field the query COMPUTED from the role's
     * OWN grant edges; null means it was not asked for. Facts other modules
     * own — how many users hold this role — are not here: that is the
     * assignment search's answer.
     * @param ?int $_grants How many permissions this role grants, when asked for
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $description,
        public bool $is_system,
        public null|int $_grants = null,
    ) {}
}
