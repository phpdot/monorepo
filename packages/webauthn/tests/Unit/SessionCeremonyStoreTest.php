<?php

declare(strict_types=1);

namespace PHPdot\WebAuthn\Tests\Unit;

use PHPdot\WebAuthn\SessionCeremonyStore;
use PHPdot\WebAuthn\Tests\Support\ArraySession;
use PHPdot\WebAuthn\Tests\Support\FrozenClock;
use PHPdot\WebAuthn\WebauthnConfig;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Ceremony state is read-once and TTL-bound: the library only compares
 * challenges, so single-use here is the replay protection and expiry prunes
 * what hangs.
 */
final class SessionCeremonyStoreTest extends TestCase
{
    #[Test]
    public function stateIsReadableExactlyOnce(): void
    {
        $store = $this->store($clock = new FrozenClock(1_000));

        $store->save('the-challenge', '{"options":true}');

        self::assertSame('{"options":true}', $store->consume('the-challenge'));
        self::assertNull($store->consume('the-challenge'), 'a second read is a replay and answers null');
    }

    #[Test]
    public function expiredStateAnswersNull(): void
    {
        $store = $this->store($clock = new FrozenClock(1_000));

        $store->save('the-challenge', '{"options":true}');
        $clock->advance(301);

        self::assertNull($store->consume('the-challenge'));
    }

    #[Test]
    public function savingPrunesExpiredState(): void
    {
        $session = new ArraySession();
        $store = $this->store($clock = new FrozenClock(1_000), $session);

        $store->save('old', '{}');
        $clock->advance(301);
        $store->save('new', '{}');

        self::assertFalse($session->has('_webauthn.ceremony.old'));
        self::assertTrue($session->has('_webauthn.ceremony.new'));
    }

    private function store(FrozenClock $clock, null|ArraySession $session = new ArraySession()): SessionCeremonyStore
    {
        $config = new WebauthnConfig(rpId: 'example.test', allowedOrigins: ['https://example.test']);

        return new SessionCeremonyStore($session, $config, $clock);
    }
}
