<?php

declare(strict_types=1);

/**
 * The default LoginThrottleInterface: every attempt runs. A host overrides
 * the binding with a real limiter (cache- or table-backed) the moment it
 * faces the internet — the engine's contract around the seam already holds.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Authentication;

use PHPdot\Container\Attribute\Binds;
use PHPdot\Iam\Authentication\Contract\LoginThrottleInterface;

#[Binds(LoginThrottleInterface::class)]
final readonly class NullThrottle implements LoginThrottleInterface
{
    public function allows(string $key): bool
    {
        return true;
    }

    public function registerFailure(string $key): void {}

    public function clear(string $key): void {}
}
