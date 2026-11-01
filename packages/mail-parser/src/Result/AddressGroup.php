<?php

declare(strict_types=1);

/**
 * Value object representing an RFC 5322 address group (distribution list) containing a group name
 * and member addresses.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Parser\Result;

final readonly class AddressGroup
{
    /** @param list<Address> $addresses */
    public function __construct(
        public string $name,
        public array $addresses,
    ) {}

    public function __toString(): string
    {
        $list = implode(', ', array_map(fn(Address $a) => (string) $a, $this->addresses));

        return sprintf('%s: %s;', $this->name, $list);
    }
}
