<?php

declare(strict_types=1);

/**
 * Whether a stored identity may still act. Consulted every time an
 * authenticated identity is read back — the seam that keeps a disabled or
 * deleted account from riding an existing session to its natural expiry.
 *
 * The host implements it against whatever knows account status (a users
 * table, a directory, a cache); the default trusts the session, which is
 * exactly the behaviour of a host that has not wired one.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Authentication\Contract;

use PHPdot\Iam\Identities\Contract\Identity\IdentityInterface;

interface IdentityRevalidatorInterface
{
    /**
     * An identity that fails revalidation reads back as logged out.
     *
     * @param IdentityInterface $identity The identity reconstructed from the session
     *
     * @return bool
     */
    public function revalidate(IdentityInterface $identity): bool;
}
