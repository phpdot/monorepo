<?php

declare(strict_types=1);

namespace PHPdot\WebAuthn\Tests\Support;

use PHPdot\Iam\Identities\Contract\Identity\IdentityInterface;
use PHPdot\Iam\Identities\Types\UserIdentity;
use PHPdot\WebAuthn\Authentication\WebauthnIdentityProviderInterface;
use PHPdot\WebAuthn\WebauthnUser;

/**
 * One fixed mapping: alice's identity to one fixed handle.
 */
final class StaticUsers implements WebauthnIdentityProviderInterface
{
    private const string HANDLE = 'c3RhdGljLWhhbmRsZS0wMQ';

    public function identity(): IdentityInterface
    {
        return new UserIdentity('alice');
    }

    public function user(): WebauthnUser
    {
        return new WebauthnUser(self::HANDLE, 'alice', 'Alice');
    }

    public function webauthnUserFor(IdentityInterface $identity): null|WebauthnUser
    {
        return $identity->id() === 'alice'
            ? new WebauthnUser(self::HANDLE, 'alice', 'Alice')
            : null;
    }

    public function identityForUserHandle(string $userHandle): null|IdentityInterface
    {
        return $userHandle === self::HANDLE ? $this->identity() : null;
    }
}
