<?php

declare(strict_types=1);

/**
 * A role name is empty, untrimmed, or oversized. Fail-fast at construction —
 * a malformed name never reaches storage.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Exception;

final class InvalidRoleNameException extends IamException
{
    /**
     * A malformed name.
     *
     * @param string $name The offending name
     *
     * @return self
     */
    public static function for(string $name): self
    {
        return new self(sprintf('Invalid role name [%s] — a name is non-empty, trimmed, and at most 64 characters.', $name));
    }
}
