<?php

declare(strict_types=1);

namespace PHPdot\Iam\Tests\Unit\Authentication;

use PHPdot\Iam\Authentication\Contract\CredentialsInterface;

/**
 * A third factor credential for engine tests (a stand-in for an email code),
 * used to exercise advancing through multiple ordered factors.
 */
final readonly class EmailCredentials implements CredentialsInterface
{
    public function __construct(public string $token) {}
}
