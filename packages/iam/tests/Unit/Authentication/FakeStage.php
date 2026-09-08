<?php

declare(strict_types=1);

namespace PHPdot\Iam\Tests\Unit\Authentication;

use Closure;
use PHPdot\Iam\Authentication\AuthenticationResult;
use PHPdot\Iam\Authentication\Contract\AuthenticationStageInterface;
use PHPdot\Iam\Authentication\Contract\CredentialsInterface;

/**
 * Configurable AuthenticationStageInterface for engine tests: declares a factor, the
 * credential class it supports, and a closure that produces the result.
 */
final class FakeStage implements AuthenticationStageInterface
{
    public function __construct(
        private readonly string $factor,
        private readonly string $supportsClass,
        private readonly Closure $invoke,
    ) {}

    public function factor(): string
    {
        return $this->factor;
    }

    public function supports(CredentialsInterface $credentials): bool
    {
        return $credentials instanceof $this->supportsClass;
    }

    public function __invoke(CredentialsInterface $credentials): AuthenticationResult
    {
        return ($this->invoke)($credentials);
    }
}
