<?php

declare(strict_types=1);

namespace PHPdot\Iam\Tests\Unit\Authentication;

use PHPdot\Iam\Authentication\Contract\IdentityRevalidatorInterface;
use PHPdot\Iam\Authentication\DefaultIdentityReconstructor;
use PHPdot\Iam\Authentication\PendingAuth;
use PHPdot\Iam\Authentication\Requirement;
use PHPdot\Iam\Authentication\RequirementSet;
use PHPdot\Iam\Authentication\SessionAuthenticator;
use PHPdot\Iam\Authentication\TrustingRevalidator;
use PHPdot\Iam\Identities\Contract\Identity\IdentityInterface;
use PHPdot\Iam\Identities\Types\UserIdentity;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SessionAuthenticatorTest extends TestCase
{
    #[Test]
    public function authenticateStoresIdAndTypeAndRegeneratesTheSession(): void
    {
        $session = new ArraySession();
        $authenticator = $this->authenticator($session);

        $authenticator->authenticate(new UserIdentity(42));

        self::assertSame(42, $session->get('_auth.id'));
        self::assertSame('user', $session->get('_auth.type'));
        self::assertSame(1, $session->regenerations(), 'authenticate must regenerate the session id (fixation defense)');
        self::assertTrue($session->lastRegenerateDestroyed(), 'the pre-authentication record is destroyed with it');
    }

    #[Test]
    public function currentReconstructsTheIdentityFromIdAndType(): void
    {
        $session = new ArraySession();
        $session->set('_auth.id', 'alice');
        $session->set('_auth.type', 'user');

        $identity = $this->authenticator($session)->current();

        self::assertNotNull($identity);
        self::assertSame('user', $identity->type());
        self::assertSame('alice', $identity->id());
    }

    #[Test]
    public function currentIsNullWhenNothingIsStored(): void
    {
        self::assertNull($this->authenticator(new ArraySession())->current());
    }

    #[Test]
    public function currentFailsClosedOnATamperedType(): void
    {
        $session = new ArraySession();
        $session->set('_auth.id', 'alice');
        $session->set('_auth.type', 123);

        self::assertNull($this->authenticator($session)->current(), 'a tampered session must not yield an identity');
    }

    #[Test]
    public function deauthenticateClearsTheIdentityAndInvalidates(): void
    {
        $session = new ArraySession();
        $session->set('_auth.id', 'alice');
        $session->set('_auth.type', 'user');
        $authenticator = $this->authenticator($session);

        $authenticator->deauthenticate();

        self::assertFalse($session->has('_auth.id'));
        self::assertNull($authenticator->current());
        self::assertSame(1, $session->invalidations());
    }

    #[Test]
    public function deauthenticateEndsAHalfFinishedStagedLoginToo(): void
    {
        $session = new ArraySession();
        $store = new MemoryPendingAuthStore();
        $authenticator = new SessionAuthenticator($session, new DefaultIdentityReconstructor(), $store);

        $authenticator->authenticate(new UserIdentity('alice'));
        $store->put(new PendingAuth(
            new UserIdentity('alice'),
            new RequirementSet([new Requirement('otp')]),
            0,
        ));

        $authenticator->deauthenticate();

        self::assertNull($store->get(), 'logout ends the whole authentication, not just its completed half');
    }

    #[Test]
    public function currentIsNullWhenTheRevalidatorRejects(): void
    {
        $session = new ArraySession();
        $session->set('_auth.id', 'alice');
        $session->set('_auth.type', 'user');
        $authenticator = new SessionAuthenticator(
            $session,
            new DefaultIdentityReconstructor(),
            new MemoryPendingAuthStore(),
            new class implements IdentityRevalidatorInterface {
                public function revalidate(IdentityInterface $identity): bool
                {
                    return false;
                }
            },
        );

        self::assertNull($authenticator->current(), 'a disabled identity reads back as logged out');
    }

    #[Test]
    public function theDefaultRevalidatorTrustsTheSession(): void
    {
        $session = new ArraySession();
        $session->set('_auth.id', 'alice');
        $session->set('_auth.type', 'user');
        $authenticator = new SessionAuthenticator(
            $session,
            new DefaultIdentityReconstructor(),
            new MemoryPendingAuthStore(),
            new TrustingRevalidator(),
        );

        self::assertSame('alice', $authenticator->current()?->id());
    }

    private function authenticator(ArraySession $session): SessionAuthenticator
    {
        return new SessionAuthenticator($session, new DefaultIdentityReconstructor(), new MemoryPendingAuthStore());
    }
}
