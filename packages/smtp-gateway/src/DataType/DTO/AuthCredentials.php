<?php

declare(strict_types=1);

/**
 * Decoded SASL authentication credentials.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Gateway\DataType\DTO;

use PHPdot\Mail\Gateway\DataType\Enum\AuthMechanism;

/**
 * Holds decoded credentials from AUTH exchange.
 */
readonly class AuthCredentials
{
    public function __construct(
        public AuthMechanism $mechanism,
        public string $username,
        public string $password,
    ) {}
}
