<?php

declare(strict_types=1);

/**
 * Fetches stored credentials for an identifier.
 *
 * The package defines the contract; the application implements the lookup (it
 * knows its user storage). This only FETCHES — verifying the password is done
 * separately (PasswordHasherInterface), so the fetch can be reused by other stages.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Authentication\Contract;

use PHPdot\Iam\Authentication\StoredCredentials;

interface CredentialsProviderInterface
{
    public function findByIdentifier(string $identifier): null|StoredCredentials;
}
