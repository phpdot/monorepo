<?php

declare(strict_types=1);

/**
 * The default IdentityRevalidatorInterface: every identity passes. A host
 * overrides the binding with a status-and-existence check the moment it has
 * something that knows account state.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Authentication;

use PHPdot\Container\Attribute\Binds;
use PHPdot\Iam\Authentication\Contract\IdentityRevalidatorInterface;
use PHPdot\Iam\Identities\Contract\Identity\IdentityInterface;

#[Binds(IdentityRevalidatorInterface::class)]
final readonly class TrustingRevalidator implements IdentityRevalidatorInterface
{
    public function revalidate(IdentityInterface $identity): bool
    {
        return true;
    }
}
