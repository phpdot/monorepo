<?php

declare(strict_types=1);

/**
 * Default reconstructor for the three built-in identity kinds.
 *
 * UserIdentity requires a non-null id; guest/cli ignore the stored id (guest's
 * is null, cli's is fixed). An unknown type yields null, which the caller treats
 * as "no valid identity" (fail-closed).
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Authentication;

use PHPdot\Container\Attribute\Binds;
use PHPdot\Container\Attribute\Singleton;
use PHPdot\Iam\Authentication\Contract\IdentityReconstructorInterface;
use PHPdot\Iam\Identities\Contract\Identity\IdentityInterface;
use PHPdot\Iam\Identities\Types\CliIdentity;
use PHPdot\Iam\Identities\Types\GuestIdentity;
use PHPdot\Iam\Identities\Types\UserIdentity;

#[Singleton]
#[Binds(IdentityReconstructorInterface::class)]
final class DefaultIdentityReconstructor implements IdentityReconstructorInterface
{
    public function reconstruct(int|string|null $id, string $type): null|IdentityInterface
    {
        return match ($type) {
            'user' => $id === null ? null : new UserIdentity($id),
            'guest' => new GuestIdentity(),
            'cli' => new CliIdentity(),
            default => null,
        };
    }
}
