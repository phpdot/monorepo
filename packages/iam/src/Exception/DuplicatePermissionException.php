<?php

declare(strict_types=1);

/**
 * The same permission key declared at two sites. Fail-fast at discovery —
 * two owners of one key is a vocabulary conflict no runtime should arbitrate.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Exception;

final class DuplicatePermissionException extends IamException
{
    /**
     * A duplicate, naming both declaration sites.
     *
     * @param string $key The colliding key
     * @param string $first First declaration site (Class::CONST)
     * @param string $second Second declaration site (Class::CONST)
     *
     * @return self
     */
    public static function between(string $key, string $first, string $second): self
    {
        return new self(sprintf(
            'Permission key [%s] is declared twice: %s and %s.',
            $key,
            $first,
            $second,
        ));
    }
}
