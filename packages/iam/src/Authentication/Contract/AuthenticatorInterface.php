<?php

declare(strict_types=1);

/**
 * Establishes and clears the logged-in identity, and reports the current one.
 * Separate from the engine: the engine decides IF login happens; the
 * authenticator makes it persist across requests.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Authentication\Contract;

use PHPdot\Iam\Identities\Contract\Identity\IdentityInterface;

interface AuthenticatorInterface
{
    /**
     * Persist the given identity as the logged-in actor (e.g. write it to the
     * session). Called by the application once the engine returns Authenticated.
     */
    public function authenticate(IdentityInterface $identity): void;

    /**
     * Clear the logged-in identity (logout).
     */
    public function deauthenticate(): void;

    /**
     * The currently logged-in identity, or null when there is none.
     */
    public function current(): null|IdentityInterface;
}
