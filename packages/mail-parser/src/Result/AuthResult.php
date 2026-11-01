<?php

declare(strict_types=1);

/**
 * Value object representing parsed Authentication-Results with DKIM, SPF, DMARC,
 * and other method outcomes.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Parser\Result;

final readonly class AuthResult
{
    public function __construct(
        public string|null $server,
        public string|null $dkim,
        public string|null $dkimDomain,
        public string|null $spf,
        public string|null $spfDomain,
        public string|null $dmarc,
        public string|null $dmarcDomain,
        public string|null $iprev,
        public string $raw,
    ) {}
}
