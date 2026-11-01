<?php

declare(strict_types=1);

/**
 * Value object representing a single Received header hop with parsed hostname, IP address,
 * protocol, and timestamp.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Parser\Result;

use DateTimeImmutable;

final readonly class ReceivedHop
{
    public function __construct(
        public string|null $from,
        public string|null $fromIp,
        public string|null $by,
        public string|null $byIp,
        public string|null $via,
        public string|null $with,
        public string|null $id,
        public string|null $for,
        public DateTimeImmutable|null $date,
        public string $raw,
    ) {}
}
