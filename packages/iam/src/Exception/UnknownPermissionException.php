<?php

declare(strict_types=1);

/**
 * A mirrored permission id no storage knows, or one a grant cannot target.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Exception;

final class UnknownPermissionException extends IamException
{
    public static function forId(int $id): self
    {
        return new self(sprintf('Permission [%d] does not exist in the mirror — sync the declared vocabulary first.', $id));
    }

    public static function forOrphanedKey(string $key): self
    {
        return new self(sprintf(
            'Permission [%s] is orphaned — declared nowhere in code, so granting it would point at nothing a check reads.',
            $key,
        ));
    }
}
