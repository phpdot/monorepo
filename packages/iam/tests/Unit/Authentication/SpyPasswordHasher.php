<?php

declare(strict_types=1);

namespace PHPdot\Iam\Tests\Unit\Authentication;

use PHPdot\Iam\Authentication\Contract\PasswordHasherInterface;

/**
 * PasswordHasherInterface double that counts hash() calls (so the password stage's
 * timing-equalization on an unknown identifier can be asserted) and returns a
 * configurable verify result.
 */
final class SpyPasswordHasher implements PasswordHasherInterface
{
    public int $hashCalls = 0;

    public function __construct(public bool $verifies = false) {}

    public function hash(string $plain): string
    {
        ++$this->hashCalls;

        return 'hashed:' . $plain;
    }

    public function verify(string $plain, string $hash): bool
    {
        return $this->verifies;
    }

    public function needsRehash(string $hash): bool
    {
        return false;
    }
}
