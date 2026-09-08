<?php

declare(strict_types=1);

/**
 * The input of the WebAuthn authentication stage: the browser's assertion
 * JSON, exactly as posted. No identifier — a discoverable ceremony has none
 * before the credential resolves one — so the login throttle keys this
 * credential type by class until the ceremony identifies the account.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\WebAuthn\Authentication;

use PHPdot\Iam\Authentication\Contract\CredentialsInterface;

final readonly class WebauthnCredentials implements CredentialsInterface
{
    public function __construct(
        public string $responseJson,
    ) {}
}
