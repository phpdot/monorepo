<?php

declare(strict_types=1);

/**
 * WebAuthn as an iam authentication stage — one factor, one seam. Works as
 * the identifying factor (a discoverable passkey resolves its own identity
 * through the credential's user handle — the engine takes the identity the
 * stage returns) or as a step-up factor after a password. The stage owns no
 * state: the ceremony lives in the store, the record in the repository, the
 * policy and pending lifecycle in iam's engine.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\WebAuthn\Authentication;

use PHPdot\Iam\Authentication\AuthenticationResult;
use PHPdot\Iam\Authentication\Contract\AuthenticationStageInterface;
use PHPdot\Iam\Authentication\Contract\CredentialsInterface;
use PHPdot\WebAuthn\Exception\WebauthnException;
use PHPdot\WebAuthn\WebauthnService;

final readonly class WebauthnStage implements AuthenticationStageInterface
{
    public function __construct(
        private WebauthnService $webauthn,
        private WebauthnIdentityProviderInterface $identities,
    ) {}

    public function factor(): string
    {
        return 'webauthn';
    }

    public function supports(CredentialsInterface $credentials): bool
    {
        return $credentials instanceof WebauthnCredentials;
    }

    public function __invoke(CredentialsInterface $credentials): AuthenticationResult
    {
        if (!$credentials instanceof WebauthnCredentials) {
            return AuthenticationResult::failed('invalid_credentials_type');
        }

        try {
            $handle = $this->webauthn->finishAuthentication($credentials->responseJson);
        } catch (WebauthnException) {
            return AuthenticationResult::failed('webauthn_ceremony_failed');
        }

        $identity = $this->identities->identityForUserHandle($handle);

        if ($identity === null) {
            return AuthenticationResult::failed('webauthn_unknown_identity');
        }

        return AuthenticationResult::authenticated($identity);
    }
}
