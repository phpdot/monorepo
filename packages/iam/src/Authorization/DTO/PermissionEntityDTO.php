<?php

declare(strict_types=1);

/**
 * One mirrored permission as the list shows it.
 *
 * The mirror is storage's copy of the declared vocabulary — keyed by id like
 * every stored row, addressed by id by every edge, and carrying the key as
 * DATA: the key is what code declares and what the check path matches, and
 * `status` says whether code still declares it. An orphaned row stays
 * visible so an administrator can see what a grant used to point at.
 *
 * No owning app: the first segment of a key is grouping convention, not a
 * fact this record owns — an App is a framework concept, and it will be a
 * real one or it will not be here.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Authorization\DTO;

use PHPdot\Contracts\DTO\EntityDTO;
use PHPdot\Iam\Authorization\Enum\PermissionStatus;

final readonly class PermissionEntityDTO implements EntityDTO
{
    /**
     * @param int $id The mirrored permission id — what a grant edge refers to
     * @param string $key The declared key — what the check path matches
     * @param string $name Human display name
     * @param string $description What holding it allows
     * @param bool $is_root Grantable only to the reserved root role
     * @param PermissionStatus $status Declared in code, or declared once and gone
     * @param string $declared_by Declaration site, Class::CONST — where code owns this key
     */
    public function __construct(
        public int $id,
        public string $key,
        public string $name,
        public string $description,
        public bool $is_root,
        public PermissionStatus $status,
        public string $declared_by,
    ) {}
}
