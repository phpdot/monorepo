<?php

declare(strict_types=1);

/**
 * A real, identified application user. The id is non-null — constructing a
 * UserIdentity without one is a programming error, not a guest state.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Identities\Types;

use PHPdot\Iam\Identities\Contract\Identity\IdentityInterface;

final readonly class UserIdentity implements IdentityInterface
{
    public function __construct(
        private int|string $id,
    ) {}

    public function id(): int|string
    {
        return $this->id;
    }

    public function type(): string
    {
        return 'user';
    }
}
