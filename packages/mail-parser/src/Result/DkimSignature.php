<?php

declare(strict_types=1);

/**
 * Value object representing a parsed DKIM-Signature header with domain, selector, algorithm,
 * and cryptographic data.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Parser\Result;

final readonly class DkimSignature
{
    public function __construct(
        public string|null $version,
        public string|null $algorithm,
        public string|null $domain,
        public string|null $selector,
        public string|null $signature,
        public string|null $bodyHash,
        public string|null $headers,
        public string|null $canonicalization,
        public string|null $identity,
        public int|null $bodyLength,
        public string|null $queryMethod,
        public int|null $timestamp,
        public int|null $expiration,
        public string $raw,
    ) {}
}
