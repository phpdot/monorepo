<?php

declare(strict_types=1);

/**
 * The iam bridge's mapping seam: iam identities in, WebAuthn users out, and
 * back. The host mints and keeps the handle — stable, opaque, at most 64
 * raw bytes, never recycled across accounts — it is what binds a credential
 * to an identity across both ceremonies.
 *
 * Part of the optional iam bridge: meaningful only where phpdot/iam drives
 * authentication.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\WebAuthn\Authentication;

use PHPdot\Iam\Identities\Contract\Identity\IdentityInterface;
use PHPdot\WebAuthn\WebauthnUser;

interface WebauthnIdentityProviderInterface
{
    /**
     * The WebAuthn user an identity maps to, null when it cannot register.
     *
     * @param IdentityInterface $identity The actor
     *
     * @return WebauthnUser|null
     */
    public function webauthnUserFor(IdentityInterface $identity): null|WebauthnUser;

    /**
     * The identity a user handle belongs to, null when it maps to none.
     *
     * @param string $userHandle The user handle (base64url)
     *
     * @return IdentityInterface|null
     */
    public function identityForUserHandle(string $userHandle): null|IdentityInterface;
}
