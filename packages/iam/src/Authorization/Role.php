<?php

declare(strict_types=1);

/**
 * A role: a named grouping of permissions and NOTHING else — no hierarchy, no
 * behavior (the platform law). The ID is the address — every edge and grant
 * references it, and a rename never ripples through edge rows — so a Role is
 * always a stored record: ids are minted by the seed wiring or read from
 * storage, and a not-yet-stored role is a RoleSaveDTO, never this. The NAME
 * is the one human identifier, unique data a person reads. is_system marks
 * the reserved undeletable roles (root, guest). Fail-fast: empty or
 * oversized names never construct.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Authorization;

use PHPdot\Iam\Exception\InvalidRoleNameException;

final readonly class Role
{
    private const int MAX_NAME_LENGTH = 64;

    /**
     * @param int $id The storage id
     * @param string $name The identifier and display name ('admin')
     * @param string $description What this grouping is for
     * @param bool $isSystem Reserved role — undeletable (root, guest)
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $description = '',
        public bool $isSystem = false,
    ) {
        self::assertName($name);
    }

    /**
     * The name format law, stated where every writer answers to it — the
     * constructor enforces it for stored records, the mutation surface for
     * values on their way in, before a row exists to construct.
     *
     * @param string $name The candidate name
     *
     * @return void
     */
    public static function assertName(string $name): void
    {
        if (trim($name) === '' || $name !== trim($name) || mb_strlen($name) > self::MAX_NAME_LENGTH) {
            throw InvalidRoleNameException::for($name);
        }
    }
}
