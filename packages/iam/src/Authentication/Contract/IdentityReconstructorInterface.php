<?php

declare(strict_types=1);

/**
 * Rebuilds a lightweight identity (the correct concrete type) from a stored
 * id + type — turns persisted state back into a real IdentityInterface.
 *
 * Returns the identity, or null if the stored type is unknown/unsupported.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Authentication\Contract;

use PHPdot\Iam\Identities\Contract\Identity\IdentityInterface;

interface IdentityReconstructorInterface
{
    public function reconstruct(int|string|null $id, string $type): null|IdentityInterface;
}
