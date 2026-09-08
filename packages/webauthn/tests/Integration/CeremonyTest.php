<?php

declare(strict_types=1);

namespace PHPdot\WebAuthn\Tests\Integration;

use function json_decode;

use PHPdot\WebAuthn\Base64Url;
use PHPdot\WebAuthn\Exception\CeremonyFailedException;
use PHPdot\WebAuthn\Exception\DuplicateCredentialException;
use PHPdot\WebAuthn\Exception\InvalidResponseException;
use PHPdot\WebAuthn\Exception\UnknownCredentialException;
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
 * The full ceremonies against the real library and real cryptography: a
 * registration that persists a record, an authentication that resolves the
 * identity and advances the stored counter, and every failure shape the
 * fence owns — duplicate credentials, unknown credentials, replayed
 * challenges, wrong-origin responses, and payloads that are not credentials
 * at all.
 */
final class CeremonyTest extends TestCase
{
    private const int REGISTRATION_COUNT = 5;

    #[Test]
    public function aRegistrationCeremonyPersistsTheCredential(): void
    {
        [$service, $records, $sim] = $this->service();
        $user = (new StaticUsers())->user();

        $challenge = $this->challengeOf($service->beginRegistration($user));

        $recordJson = $service->finishRegistration($user, $sim->attestation($challenge, self::REGISTRATION_COUNT));

        self::assertNotSame('', $recordJson);
        self::assertTrue($records->exists(Base64Url::encode($sim->credentialId())));

        $stored = json_decode($records->findByCredentialId(Base64Url::encode($sim->credentialId())), true);

        self::assertSame(self::REGISTRATION_COUNT, $stored['counter']);
    }

    #[Test]
    public function anAuthenticationCeremonyResolvesTheIdentityAndAdvancesTheCounter(): void
    {
        [$service, $records, $sim] = $this->service();
        $this->register($service, $sim);

        $challenge = $this->challengeOf($service->beginAuthentication((new StaticUsers())->user()->handle));
        $resolved = $service->finishAuthentication($sim->assertion($challenge, self::REGISTRATION_COUNT + 1));

        self::assertSame((new StaticUsers())->user()->handle, $resolved, 'finish answers the user handle');

        $stored = json_decode($records->findByCredentialId(Base64Url::encode($sim->credentialId())), true);

        self::assertSame(self::REGISTRATION_COUNT + 1, $stored['counter'], 'the counter write is mandatory — it is the clone detector');
    }

    #[Test]
    public function aDuplicateCredentialIdIsRefused(): void
    {
        [$service, , $sim] = $this->service();
        $this->register($service, $sim);

        $challenge = $this->challengeOf($service->beginRegistration((new StaticUsers())->user()));

        $this->expectException(DuplicateCredentialException::class);

        $service->finishRegistration((new StaticUsers())->user(), $sim->attestation($challenge, self::REGISTRATION_COUNT));
    }

    #[Test]
    public function anUnknownCredentialIsRefused(): void
    {
        [$service, , $sim] = $this->service();
        $this->register($service, $sim);
        $handle = (new StaticUsers())->user()->handle;

        $challenge = $this->challengeOf($service->beginAuthentication($handle));
        $service->finishAuthentication($sim->assertion($challenge, self::REGISTRATION_COUNT + 1));

        $second = $this->challengeOf($service->beginAuthentication($handle));
        $stranger = new AuthenticatorSimulator('https://example.test', 'example.test');

        $this->expectException(UnknownCredentialException::class);

        $service->finishAuthentication($stranger->assertion($second, self::REGISTRATION_COUNT + 2));
    }

    #[Test]
    public function aReplayedResponseFailsClosed(): void
    {
        [$service, , $sim] = $this->service();
        $this->register($service, $sim);

        $challenge = $this->challengeOf($service->beginAuthentication(null));
        $response = $sim->assertion($challenge, self::REGISTRATION_COUNT + 1);
        $service->finishAuthentication($response);

        $this->expectException(CeremonyFailedException::class);

        $service->finishAuthentication($response);
    }

    #[Test]
    public function aWrongOriginFailsClosed(): void
    {
        $config = new WebauthnConfig(rpId: 'example.test', allowedOrigins: ['https://example.test']);
        $clock = new FrozenClock(1_000);
        $engine = new WebauthnEngine($config);
        $service = new WebauthnService(
            $engine,
            $config,
            $records = new InMemoryCredentialRecords(),
            new SessionCeremonyStore(new ArraySession(), $config, $clock),
        );
        $imposter = new AuthenticatorSimulator('https://evil.example', 'example.test');
        $user = (new StaticUsers())->user();

        $challenge = $this->challengeOf($service->beginRegistration($user));

        $this->expectException(CeremonyFailedException::class);

        $service->finishRegistration($user, $imposter->attestation($challenge, self::REGISTRATION_COUNT));
    }

    #[Test]
    public function aPayloadThatIsNotACredentialIsRefused(): void
    {
        [$service, , ] = $this->service();
        $user = (new StaticUsers())->user();
        $service->beginRegistration($user);

        $this->expectException(InvalidResponseException::class);

        $service->finishRegistration($user, '{"no":"id","at":"all"}');
    }

    /**
     * @return array{WebauthnService, InMemoryCredentialRecords, AuthenticatorSimulator}
     */
    private function service(): array
    {
        $config = new WebauthnConfig(rpId: 'example.test', rpName: 'Example', allowedOrigins: ['https://example.test']);
        $engine = new WebauthnEngine($config);
        $records = new InMemoryCredentialRecords();
        $service = new WebauthnService(
            $engine,
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
        $service->finishRegistration($user, $sim->attestation($challenge, self::REGISTRATION_COUNT));
    }

    /**
     * The raw challenge bytes out of a begun ceremony's options JSON.
     */
    private function challengeOf(string $optionsJson): string
    {
        $decoded = json_decode($optionsJson, true);

        return Base64Url::decode($decoded['challenge']);
    }
}
