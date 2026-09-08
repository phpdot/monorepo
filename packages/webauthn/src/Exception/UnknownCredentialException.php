<?php

declare(strict_types=1);

/**
 * An authentication presented a credential this relying party never issued,
 * or one whose user handle no longer maps to an identity. Fail-closed.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\WebAuthn\Exception;

final class UnknownCredentialException extends WebauthnException
{
    /**
     * The refused authentication.
     *
     * @param string $credentialId The unknown credential id (base64url)
     *
     * @return self
     */
    public static function for(string $credentialId): self
    {
        return new self(sprintf('Credential [%s] is not registered to any identity.', $credentialId));
    }
}
