<?php

declare(strict_types=1);

namespace PHPdot\Iam\Tests\Unit\Authentication;

use PHPdot\Iam\Authentication\Contract\CredentialsInterface;

/**
 * A second-factor credential for engine tests (a stand-in for a TOTP code).
 */
final readonly class OtpCredentials implements CredentialsInterface
{
    public function __construct(public string $code) {}
}
