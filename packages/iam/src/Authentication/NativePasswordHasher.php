<?php

declare(strict_types=1);

/**
 * Argon2id password hasher (P3 ruling) — the algorithm AND its parameters are
 * EXPLICIT, never PASSWORD_DEFAULT: a security parameter must not change
 * because a PHP upgrade changed its mind. The costs are PHP's argon2
 * defaults, above OWASP minimums; a verify costs real blocking CPU + 64 MiB
 * transient, which only the login path pays. Legacy hashes (any algorithm
 * password_verify() recognises) keep verifying and report needsRehash() true,
 * so they self-upgrade on the owner's next login.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Authentication;

use const PASSWORD_ARGON2ID;

use function password_hash;
use function password_needs_rehash;
use function password_verify;

use PHPdot\Container\Attribute\Binds;
use PHPdot\Container\Attribute\Singleton;
use PHPdot\Iam\Authentication\Contract\PasswordHasherInterface;

#[Singleton]
#[Binds(PasswordHasherInterface::class)]
final class NativePasswordHasher implements PasswordHasherInterface
{
    private const int MEMORY_COST = 65536;

    private const int TIME_COST = 4;

    private const int THREADS = 1;

    /**
     * @inheritDoc
     */
    public function hash(string $plain): string
    {
        return password_hash($plain, PASSWORD_ARGON2ID, [
            'memory_cost' => self::MEMORY_COST,
            'time_cost' => self::TIME_COST,
            'threads' => self::THREADS,
        ]);
    }

    /**
     * @inheritDoc
     */
    public function verify(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }

    /**
     * @inheritDoc
     */
    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_ARGON2ID, [
            'memory_cost' => self::MEMORY_COST,
            'time_cost' => self::TIME_COST,
            'threads' => self::THREADS,
        ]);
    }
}
