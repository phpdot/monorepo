<?php

declare(strict_types=1);

/**
 * The result of fetching credentials by identifier: the matched identity and
 * the stored password hash to verify against.
 *
 * Wraps an identity (it is NOT itself an identity). The hash is storage data,
 * deliberately kept off the lightweight IdentityInterface.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Authentication;

use PHPdot\Iam\Identities\Contract\Identity\IdentityInterface;

final readonly class StoredCredentials
{
    public function __construct(
        public IdentityInterface $identity,
        public string $passwordHash,
    ) {}
}
