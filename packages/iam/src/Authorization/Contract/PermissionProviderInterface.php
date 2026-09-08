<?php

declare(strict_types=1);

/**
 * What an actor holds, asked by the check path. permissionsFor answers
 * permission KEYS — the one currency a check understands — and rolesFor
 * answers role IDS, for the explain tooling that then asks the role
 * repository what each one grants. Fail-closed answers live in the
 * implementations: an identity nothing is known about holds nothing.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Authorization\Contract;

use PHPdot\Iam\Identities\Contract\Identity\IdentityInterface;

interface PermissionProviderInterface
{
    /**
     * Permission keys this identity holds, directly or through roles.
     *
     * @param IdentityInterface $identity The acting identity
     *
     * @return list<string>
     */
    public function permissionsFor(IdentityInterface $identity): array;

    /**
     * Role ids assigned to this identity.
     *
     * @param IdentityInterface $identity The acting identity
     *
     * @return list<int>
     */
    public function rolesFor(IdentityInterface $identity): array;
}
