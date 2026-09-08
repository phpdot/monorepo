<?php

declare(strict_types=1);

/**
 * A registration produced a credential id that already exists — for this user
 * or any other. The library never checks duplicates (excludeCredentials is
 * browser-side guidance only); the package refuses them at the fence, because
 * a repeated id means a cloned credential or a replayed registration.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\WebAuthn\Exception;

final class DuplicateCredentialException extends WebauthnException
{
    /**
     * The refused registration.
     *
     * @param string $credentialId The duplicated credential id (base64url)
     *
     * @return self
     */
    public static function for(string $credentialId): self
    {
        return new self(sprintf('Credential [%s] is already registered.', $credentialId));
    }
}
