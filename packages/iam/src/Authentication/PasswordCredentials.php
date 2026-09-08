<?php

declare(strict_types=1);

/**
 * Input for the password stage: an identifier (email/username — the developer
 * decides which) and the plaintext password to verify.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Authentication;

use PHPdot\Iam\Authentication\Contract\CredentialsInterface;
use PHPdot\Iam\Authentication\Contract\IdentifiesAccountInterface;

final readonly class PasswordCredentials implements CredentialsInterface, IdentifiesAccountInterface
{
    public function __construct(
        public string $identifier,
        public string $password,
    ) {}

    public function accountIdentifier(): string
    {
        return $this->identifier;
    }
}
