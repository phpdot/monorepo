<?php

declare(strict_types=1);

/**
 * A root-flagged permission was granted to a non-root role. Root-only
 * permissions reach exactly one group, by declaration.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Exception;

final class RootOnlyPermissionException extends IamException
{
    /**
     * A refused grant.
     *
     * @param string $key The root-only permission key
     * @param string $role The refused role
     *
     * @return self
     */
    public static function forGrant(string $key, string $role): self
    {
        return new self(sprintf('Permission [%s] is root-only and cannot be granted to role [%s].', $key, $role));
    }
}
