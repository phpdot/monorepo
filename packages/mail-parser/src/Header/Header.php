<?php

declare(strict_types=1);

/**
 * Value object representing a single parsed email header with decoded value, raw value,
 * and optional structured data.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Parser\Header;

use DateTimeImmutable;
use PHPdot\Mail\Parser\Result\Address;
use PHPdot\Mail\Parser\Result\AddressGroup;
use PHPdot\Mail\Parser\Result\MimeHeader;

final readonly class Header
{
    /**
     * @param list<Address|AddressGroup>|null $addresses
     */
    public function __construct(
        public string $name,
        public string $value,
        public string $rawValue,
        public MimeHeader|null $structured = null,
        public array|null $addresses = null,
        public DateTimeImmutable|null $dateTime = null,
    ) {}
}
