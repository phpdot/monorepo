<?php

declare(strict_types=1);

/**
 * Persists the half-finished authentication between requests (step-up / MFA).
 *
 * One implementation ships (session-backed). The interface exists so a
 * stateless, token-backed store can be added later without touching the engine.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Authentication\Contract;

use PHPdot\Iam\Authentication\PendingAuth;

interface PendingAuthStoreInterface
{
    public function put(PendingAuth $pending): void;

    public function get(): null|PendingAuth;

    public function clear(): void;
}
