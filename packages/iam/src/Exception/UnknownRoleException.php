<?php

declare(strict_types=1);

/**
 * A role id no storage knows — the address of a mutation or read that has
 * nothing behind it.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Exception;

final class UnknownRoleException extends IamException
{
    public static function for(int $id): self
    {
        return new self(sprintf('Role [%d] does not exist.', $id));
    }

    public static function named(string $name): self
    {
        return new self(sprintf('Role [%s] does not exist.', $name));
    }
}
