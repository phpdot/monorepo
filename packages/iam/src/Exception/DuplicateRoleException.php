<?php

declare(strict_types=1);

/**
 * A role was created under a name that already exists. Creation is not an
 * upsert at the manager level: overwriting an existing role — especially a
 * system role — through createRole() would silently rewrite its grants flag
 * set, so the duplicate is refused loudly instead.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Exception;

final class DuplicateRoleException extends IamException
{
    /**
     * The refused creation.
     *
     * @param string $name The duplicated role name
     *
     * @return self
     */
    public static function for(string $name): self
    {
        return new self(sprintf('Role [%s] already exists.', $name));
    }
}
