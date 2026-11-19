<?php

declare(strict_types=1);

namespace PHPdot\WebAuthn\Tests\Integration;

use function json_decode;

use PHPdot\WebAuthn\Base64Url;
use PHPdot\WebAuthn\Exception\CeremonyFailedException;
use PHPdot\WebAuthn\Exception\CounterRegressedException;
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
use PHPdot\WebAuthn\WebauthnUser;
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

    #[Test]
    public function theOptionsAskForUserVerificationAndAResidentKeyByDefault(): void
    {
        [$service, , ] = $this->service();

        $creation = json_decode($service->beginRegistration((new StaticUsers())->user()), true);
        $request = json_decode($service->beginAuthentication(null), true);

        self::assertSame('required', $creation['authenticatorSelection']['userVerification']);
        self::assertSame('required', $creation['authenticatorSelection']['residentKey']);
        self::assertSame('required', $request['userVerification']);
    }

    #[Test]
    public function aRegistrationWithoutUserVerificationIsRefusedByDefault(): void
    {
        [$service, $records, $sim] = $this->service();
        $user = (new StaticUsers())->user();
        $challenge = $this->challengeOf($service->beginRegistration($user));

        try {
            $service->finishRegistration($user, $sim->attestation($challenge, self::REGISTRATION_COUNT, verified: false));
            self::fail('an unverified registration was accepted');
        } catch (CeremonyFailedException) {
            self::assertFalse($records->exists(Base64Url::encode($sim->credentialId())));
        }
    }

    #[Test]
    public function anAuthenticationWithoutUserVerificationIsRefusedByDefault(): void
    {
        [$service, , $sim] = $this->service();
        $this->register($service, $sim);
        $challenge = $this->challengeOf($service->beginAuthentication(null));

        $this->expectException(CeremonyFailedException::class);

        $service->finishAuthentication($sim->assertion($challenge, self::REGISTRATION_COUNT + 1, verified: false));
    }

    #[Test]
    public function aHostThatOnlyPrefersVerificationAcceptsAnUnverifiedAuthentication(): void
    {
        [$service, , $sim] = $this->service(userVerification: 'preferred', residentKey: 'preferred');
        $this->register($service, $sim);

        $request = $service->beginAuthentication(null);
        $handle = $service->finishAuthentication($sim->assertion($this->challengeOf($request), self::REGISTRATION_COUNT + 1, verified: false));

        self::assertSame('preferred', json_decode($request, true)['userVerification']);
        self::assertSame((new StaticUsers())->user()->handle, $handle);
    }

    #[Test]
    public function aCounterThatWentBackIsItsOwnFailureAndIsNotSaved(): void
    {
        [$service, $records, $sim] = $this->service();
        $this->register($service, $sim);
        $challenge = $this->challengeOf($service->beginAuthentication(null));

        try {
            $service->finishAuthentication($sim->assertion($challenge, self::REGISTRATION_COUNT - 1));
            self::fail('a counter that went back was accepted');
        } catch (CounterRegressedException) {
            $stored = json_decode((string) $records->findByCredentialId(Base64Url::encode($sim->credentialId())), true);

            self::assertSame(self::REGISTRATION_COUNT, $stored['counter']);
        }
    }

    #[Test]
    public function anAuthenticatorThatKeepsNoCounterKeepsWorking(): void
    {
        [$service, , $sim] = $this->service();
        $user = (new StaticUsers())->user();
        $service->finishRegistration($user, $sim->attestation($this->challengeOf($service->beginRegistration($user)), 0));

        foreach ([1, 2] as $round) {
            $handle = $service->finishAuthentication($sim->assertion($this->challengeOf($service->beginAuthentication(null)), 0));

            self::assertSame($user->handle, $handle, "round {$round}");
        }
    }

    #[Test]
    public function aRegistrationChallengeCannotFinishAnAuthentication(): void
    {
        [$service, , $sim] = $this->service();
        $this->register($service, $sim);
        $parked = $this->challengeOf($service->beginRegistration(new WebauthnUser(Base64Url::encode('someone-else'), 'mallory')));

        $this->expectException(CeremonyFailedException::class);

        $service->finishAuthentication($sim->assertion($parked, self::REGISTRATION_COUNT + 1, verified: false));
    }

    #[Test]
    public function anAuthenticationChallengeCannotFinishARegistration(): void
    {
        [$service, $records, $sim] = $this->service();
        $user = (new StaticUsers())->user();
        $parked = $this->challengeOf($service->beginAuthentication(null));

        try {
            $service->finishRegistration($user, $sim->attestation($parked, self::REGISTRATION_COUNT));
            self::fail('a registration finished on an authentication challenge');
        } catch (CeremonyFailedException|InvalidResponseException) {
            self::assertFalse($records->exists(Base64Url::encode($sim->credentialId())));
        }
    }

    /**
     * @return array{WebauthnService, InMemoryCredentialRecords, AuthenticatorSimulator}
     */
    private function service(string $userVerification = 'required', string $residentKey = 'required'): array
    {
        $config = new WebauthnConfig(rpId: 'example.test', rpName: 'Example', allowedOrigins: ['https://example.test'], userVerification: $userVerification, residentKey: $residentKey);
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
