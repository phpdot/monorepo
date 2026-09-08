<?php

declare(strict_types=1);

/**
 * The login throttle seam: the package consults it on every attempt, the
 * host implements the storage. The engine asks before running any stage
 * (a closed throttle answers a failed result without touching a hasher —
 * the 64 MiB Argon2id cost is exactly what an attacker must not be allowed
 * to trigger freely), registers a failure whenever an attempt fails, and
 * clears on a completed login. Keys are the package's: "account:<identifier>"
 * for credentials that identify one, "pending:<type>:<id>" for an in-flight
 * staged login.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Authentication\Contract;

interface LoginThrottleInterface
{
    /**
     * Whether an attempt under this key may run.
     *
     * @param string $key The throttle key
     *
     * @return bool
     */
    public function allows(string $key): bool;

    /**
     * Record a failed attempt under this key.
     *
     * @param string $key The throttle key
     *
     * @return void
     */
    public function registerFailure(string $key): void;

    /**
     * Clear the key's history — a completed login forgives.
     *
     * @param string $key The throttle key
     *
     * @return void
     */
    public function clear(string $key): void;
}
