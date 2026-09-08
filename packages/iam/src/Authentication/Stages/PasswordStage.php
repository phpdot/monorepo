<?php

declare(strict_types=1);

/**
 * The password factor. Fetches stored credentials via the app-provided
 * CredentialsProviderInterface, then verifies the password via PasswordHasherInterface. Returns
 * Authenticated (with the fetched identity) or Failed — never Pending (multi-
 * step is the job of later stages such as TOTP).
 *
 * A wrong identifier and a wrong password both yield the SAME failure reason,
 * and a miss performs a hashing operation before failing, so an unknown
 * identifier is not distinguishable from a wrong password by response time
 * (user-enumeration protection, closing the dominant KDF timing channel).
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Authentication\Stages;

use PHPdot\Iam\Authentication\AuthenticationResult;
use PHPdot\Iam\Authentication\Contract\AuthenticationStageInterface;
use PHPdot\Iam\Authentication\Contract\CredentialsInterface;
use PHPdot\Iam\Authentication\Contract\CredentialsProviderInterface;
use PHPdot\Iam\Authentication\Contract\PasswordHasherInterface;
use PHPdot\Iam\Authentication\PasswordCredentials;

final readonly class PasswordStage implements AuthenticationStageInterface
{
    public function __construct(
        private CredentialsProviderInterface $credentials,
        private PasswordHasherInterface $hasher,
    ) {}

    public function factor(): string
    {
        return 'password';
    }

    public function supports(CredentialsInterface $credentials): bool
    {
        return $credentials instanceof PasswordCredentials;
    }

    /**
     * Verify the identifier and password. An unknown identifier burns a
     * hashing operation before failing, so it is not distinguishable from a
     * wrong password by response time.
     */
    public function __invoke(CredentialsInterface $credentials): AuthenticationResult
    {
        if (!$credentials instanceof PasswordCredentials) {
            return AuthenticationResult::failed('invalid_credentials_type');
        }

        $stored = $this->credentials->findByIdentifier($credentials->identifier);

        if ($stored === null) {
            $this->hasher->hash($credentials->password);

            return AuthenticationResult::failed('invalid_credentials');
        }

        if (!$this->hasher->verify($credentials->password, $stored->passwordHash)) {
            return AuthenticationResult::failed('invalid_credentials');
        }

        return AuthenticationResult::authenticated($stored->identity);
    }
}
