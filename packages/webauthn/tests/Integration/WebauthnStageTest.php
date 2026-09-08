<?php

declare(strict_types=1);

namespace PHPdot\WebAuthn\Tests\Integration;

use function json_decode;

use PHPdot\Iam\Authentication\PasswordCredentials;
use PHPdot\WebAuthn\Authentication\WebauthnCredentials;
use PHPdot\WebAuthn\Authentication\WebauthnStage;
use PHPdot\WebAuthn\Base64Url;
use PHPdot\WebAuthn\SessionCeremonyStore;
use PHPdot\WebAuthn\Tests\Support\ArraySession;
use PHPdot\WebAuthn\Tests\Support\AuthenticatorSimulator;
use PHPdot\WebAuthn\Tests\Support\FrozenClock;
use PHPdot\WebAuthn\Tests\Support\InMemoryCredentialRecords;
use PHPdot\WebAuthn\Tests\Support\StaticUsers;
use PHPdot\WebAuthn\WebauthnConfig;
use PHPdot\WebAuthn\WebauthnEngine;
use PHPdot\WebAuthn\WebauthnService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The iam seam: WebAuthn as one authentication stage. A passkey resolves its
 * own identity (the engine's identifying path); a failed ceremony answers a
 * failed result, never a leak.
 */
final class WebauthnStageTest extends TestCase
{
    #[Test]
    public function theStageFactorIsWebauthnAndItSupportsItsCredentials(): void
    {
        $stage = new WebauthnStage($this->service()[0], new StaticUsers());

        self::assertSame('webauthn', $stage->factor());
        self::assertTrue($stage->supports(new WebauthnCredentials('{}')));
        self::assertFalse($stage->supports(new PasswordCredentials('alice', 'pw')));
    }

    #[Test]
    public function aPasskeyIdentifiesAndAuthenticates(): void
    {
        [$service, , $sim] = $this->service();
        $stage = new WebauthnStage($service, new StaticUsers());
        $this->register($service, $sim);

        $challenge = $this->challengeOf($service->beginAuthentication(null));
        $result = $stage(new WebauthnCredentials($sim->assertion($challenge, 6)));

        self::assertTrue($result->isAuthenticated());
        self::assertSame('alice', $result->identity?->id());
    }

    #[Test]
    public function aFailedCeremonyAnswersAFailedResult(): void
    {
        [$service, , $sim] = $this->service();
        $stage = new WebauthnStage($service, new StaticUsers());
        $this->register($service, $sim);

        $challenge = $this->challengeOf($service->beginAuthentication(null));
        $service->finishAuthentication($sim->assertion($challenge, 6));

        $replay = $stage(new WebauthnCredentials($sim->assertion($challenge, 7)));

        self::assertTrue($replay->isFailed());
        self::assertSame('webauthn_ceremony_failed', $replay->reason);
    }

    /**
     * @return array{WebauthnService, InMemoryCredentialRecords, AuthenticatorSimulator}
     */
    private function service(): array
    {
        $config = new WebauthnConfig(rpId: 'example.test', rpName: 'Example', allowedOrigins: ['https://example.test']);
        $records = new InMemoryCredentialRecords();
        $service = new WebauthnService(
            new WebauthnEngine($config),
            $config,
            $records,
            new SessionCeremonyStore(new ArraySession(), $config, new FrozenClock(1_000)),
        );

        return [$service, $records, new AuthenticatorSimulator('https://example.test', 'example.test')];
    }

    private function register(WebauthnService $service, AuthenticatorSimulator $sim): void
    {
        $user = (new StaticUsers())->user();
        $challenge = $this->challengeOf($service->beginRegistration($user));
        $service->finishRegistration($user, $sim->attestation($challenge, 5));
    }

    private function challengeOf(string $optionsJson): string
    {
        return Base64Url::decode(json_decode($optionsJson, true)['challenge']);
    }
}
