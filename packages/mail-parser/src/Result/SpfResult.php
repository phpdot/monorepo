<?php

declare(strict_types=1);

/**
 * Value object representing a parsed Received-SPF header with authentication result, domain,
 * and client IP.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Parser\Result;

final readonly class SpfResult
{
    public function __construct(
        public string $result,
        public string|null $domain,
        public string|null $ip,
        public string|null $mechanism,
        public string|null $receiver,
        public string $raw,
    ) {}
}
