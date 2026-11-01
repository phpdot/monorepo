<?php

declare(strict_types=1);

/**
 * Value object representing a parsed email address with display name and address components.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Parser\Result;

final readonly class Address
{
    public function __construct(
        public string $address,
        public string $name = '',
    ) {}

    public function __toString(): string
    {
        if ($this->name !== '') {
            return sprintf('"%s" <%s>', $this->name, $this->address);
        }

        return $this->address;
    }
}
