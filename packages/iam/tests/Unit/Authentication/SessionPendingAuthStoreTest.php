<?php

declare(strict_types=1);

namespace PHPdot\Iam\Tests\Unit\Authentication;

use DateTimeImmutable;
use DateTimeInterface;
use PHPdot\Iam\Authentication\DefaultIdentityReconstructor;
use PHPdot\Iam\Authentication\PendingAuth;
use PHPdot\Iam\Authentication\Requirement;
use PHPdot\Iam\Authentication\RequirementSet;
use PHPdot\Iam\Authentication\SessionPendingAuthStore;
use PHPdot\Iam\Identities\Types\UserIdentity;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SessionPendingAuthStoreTest extends TestCase
{
    #[Test]
    public function putGetRoundTripsThePendingAuth(): void
    {
        $store = new SessionPendingAuthStore(new ArraySession(), new DefaultIdentityReconstructor());

        $pending = new PendingAuth(
            new UserIdentity('alice'),
            new RequirementSet([new Requirement('otp'), new Requirement('email')]),
            1,
            ['country' => 'JO'],
        );
        $store->put($pending);

        $restored = $store->get();

        self::assertNotNull($restored);
        self::assertSame('user', $restored->identity->type());
        self::assertSame('alice', $restored->identity->id());
        self::assertSame(1, $restored->satisfied);
        self::assertSame('email', $restored->current()->factor, 'satisfied=1 ⇒ the cursor is on the second requirement');
        self::assertSame(['country' => 'JO'], $restored->attributes);
    }

    #[Test]
    public function getIsNullWhenNothingIsStored(): void
    {
        $store = new SessionPendingAuthStore(new ArraySession(), new DefaultIdentityReconstructor());

        self::assertNull($store->get());
    }

    /**
     * The satisfied map is deliberately absent from the session, so the stored
     * shape is inconsistent and the store must answer null rather than
     * resurrect a half-written pending auth.
     */
    #[Test]
    public function getFailsClosedOnAMissingCursor(): void
    {
        $session = new ArraySession();
        $session->set('_pending_auth.id', 'alice');
        $session->set('_pending_auth.type', 'user');
        $session->set('_pending_auth.requirements', [['factor' => 'otp']]);

        $store = new SessionPendingAuthStore($session, new DefaultIdentityReconstructor());

        self::assertNull($store->get());
    }

    #[Test]
    public function getFailsClosedOnEmptyRequirements(): void
    {
        $session = new ArraySession();
        $session->set('_pending_auth.id', 'alice');
        $session->set('_pending_auth.type', 'user');
        $session->set('_pending_auth.requirements', []);
        $session->set('_pending_auth.satisfied', 0);

        $store = new SessionPendingAuthStore($session, new DefaultIdentityReconstructor());

        self::assertNull($store->get(), 'an empty requirement set is never a valid pending state');
    }

    #[Test]
    public function roundTripPreservesExpiryProvenFactorsAndSnapshot(): void
    {
        $store = new SessionPendingAuthStore(new ArraySession(), new DefaultIdentityReconstructor());
        $expires = new DateTimeImmutable('2026-01-01T00:05:00+00:00');
        $store->put(new PendingAuth(
            new UserIdentity('alice'),
            new RequirementSet([new Requirement('otp', ['length' => 6])]),
            0,
            ['country' => 'home'],
            $expires,
            ['password'],
        ));

        $back = $store->get();

        self::assertNotNull($back);
        self::assertSame($expires->format(DateTimeInterface::ATOM), $back?->expiresAt?->format(DateTimeInterface::ATOM));
        self::assertSame(['password'], $back?->provenFactors);
        self::assertSame(['country' => 'home'], $back?->attributes);
        self::assertSame('otp', $back?->current()->factor);
        self::assertSame(['length' => 6], $back?->current()->params);
    }

    #[Test]
    public function clearRemovesEveryPendingKey(): void
    {
        $session = new ArraySession();
        $session->set('_pending_auth.id', 'alice');
        $session->set('_pending_auth.type', 'user');

        (new SessionPendingAuthStore($session, new DefaultIdentityReconstructor()))->clear();

        self::assertFalse($session->has('_pending_auth.id'));
        self::assertFalse($session->has('_pending_auth.type'));
    }
}
