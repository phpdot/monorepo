<?php

declare(strict_types=1);

/**
 * One authentication step (password, TOTP, email, OIDC, …).
 *
 * `factor()` names the step so the engine and policy can refer to it and a
 * PendingAuth can record progress. `supports()` selects the identifying path:
 * the engine picks the identifying stage by asking which stage supports the
 * given credentials. `__invoke()` performs the verification.
 *
 * A new factor is added by writing a new AuthenticationStageInterface; the engine never
 * changes.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Authentication\Contract;

use PHPdot\Iam\Authentication\AuthenticationResult;

interface AuthenticationStageInterface
{
    /**
     * The factor this stage satisfies, e.g. 'password', 'totp', 'email'.
     *
     * @return string
     */
    public function factor(): string;

    public function supports(CredentialsInterface $credentials): bool;

    public function __invoke(CredentialsInterface $credentials): AuthenticationResult;
}
