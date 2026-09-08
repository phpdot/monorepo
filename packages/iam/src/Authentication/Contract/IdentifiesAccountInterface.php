<?php

declare(strict_types=1);

/**
 * Credentials that can name the account they are trying to reach — the input
 * a login throttle keys on. Password credentials carry an identifier; a bare
 * second-factor code does not, and throttling by it would be meaningless, so
 * the capability is opt-in per credential type rather than a burden on the
 * marker contract.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Authentication\Contract;

interface IdentifiesAccountInterface
{
    /**
     * The stable account hint the throttle accumulates failures against.
     *
     * @return string
     */
    public function accountIdentifier(): string;
}
