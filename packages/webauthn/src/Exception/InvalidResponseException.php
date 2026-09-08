<?php

declare(strict_types=1);

/**
 * The browser payload is not a WebAuthn response at all — undecodable JSON,
 * a denormalizer that silently passed an array through (the library's
 * behaviour when the credential id key is missing), or a response of the
 * wrong ceremony type.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\WebAuthn\Exception;

final class InvalidResponseException extends WebauthnException
{
    /**
     * The refused payload.
     *
     * @param string $reason What shape the payload failed
     *
     * @return self
     */
    public static function because(string $reason): self
    {
        return new self(sprintf('The response is not a valid WebAuthn payload: %s', $reason));
    }
}
