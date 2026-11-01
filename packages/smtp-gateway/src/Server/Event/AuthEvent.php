<?php

declare(strict_types=1);

/**
 * Server event for SMTP AUTH command.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Gateway\Server\Event;

use PHPdot\Mail\Gateway\DataType\DTO\AuthCredentials;

class AuthEvent extends Event
{
    public function __construct(
        public readonly AuthCredentials $credentials,
    ) {}

    public function username(): string
    {
        return $this->credentials->username;
    }

    public function password(): string
    {
        return $this->credentials->password;
    }

    public function mechanism(): string
    {
        return $this->credentials->mechanism->value;
    }
}
