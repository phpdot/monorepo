<?php

declare(strict_types=1);

/**
 * An unauthenticated visitor — the identity an request starts with before any
 * stage has identified the actor. Has no id; the type alone carries the state.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Identities\Types;

use PHPdot\Iam\Identities\Contract\Identity\IdentityInterface;

final readonly class GuestIdentity implements IdentityInterface
{
    public function id(): null
    {
        return null;
    }

    public function type(): string
    {
        return 'guest';
    }
}
