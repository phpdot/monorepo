<?php

declare(strict_types=1);

/**
 * The relying party is not configurable enough to run a ceremony — no RP id,
 * no allowed origins, or an identity the user repository cannot map.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\WebAuthn\Exception;

final class ConfigurationException extends WebauthnException
{
    /**
     * The missing piece.
     *
     * @param string $what What is absent or empty
     *
     * @return self
     */
    public static function missing(string $what): self
    {
        return new self(sprintf('The WebAuthn configuration is incomplete: %s.', $what));
    }
}
