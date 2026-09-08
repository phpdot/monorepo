<?php

declare(strict_types=1);

/**
 * The two authorization verbs. has() asks possession for the CURRENT actor:
 * does the grant set contain this permission key — pure membership, roles
 * invisible (they only group grants), fail-closed when the context holds no
 * identity. can() runs ONE policy — explicit policy class, explicit identity,
 * explicit typed resource; nothing ambient, nothing discovered — and returns
 * its decision. Composing rules is calling can() twice, or one policy
 * injecting another.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Authorization\Contract;

use PHPdot\Iam\Authorization\IamResource;
use PHPdot\Iam\Identities\Contract\Identity\IdentityInterface;

interface AuthorizerInterface
{
    /**
     * Does the current actor hold this permission?
     *
     * @param string $permission Permission key (use the app catalog constant)
     *
     * @return bool
     */
    public function has(string $permission): bool;

    /**
     * Does the current actor hold at least one of these permissions?
     *
     * @param non-empty-list<string> $permissions Permission keys
     *
     * @return bool
     */
    public function hasAny(array $permissions): bool;

    /**
     * Does the current actor hold every one of these permissions?
     *
     * An empty list answers false — fail-closed, symmetric with hasAny(): a
     * caller that built no requirements gets no grant, never a vacuous pass.
     *
     * @param list<string> $permissions Permission keys
     *
     * @return bool
     */
    public function hasAll(array $permissions): bool;

    /**
     * May this identity do this to this resource, per this policy?
     *
     * @param string $policy The policy class (must carry PolicyInterface)
     * @param IdentityInterface $identity The acting identity, explicit
     * @param IamResource $resource The typed resource the policy judges
     *
     * @return bool
     */
    public function can(string $policy, IdentityInterface $identity, IamResource $resource): bool;

    /**
     * The current actor, null when the context is empty.
     *
     * @return IdentityInterface|null
     */
    public function identity(): null|IdentityInterface;
}
