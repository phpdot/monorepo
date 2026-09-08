<?php

declare(strict_types=1);

/**
 * The ceremony orchestrator — the only class a host calls. Strings in,
 * strings out: begin* returns the options JSON for the browser, finish*
 * takes the credential JSON the browser posted. Everything the library
 * leaves to the integrator is owned here — challenge generation and its
 * single-use TTL-bound state, the duplicate-credential refusal the library
 * never performs, the mandatory save of the counter-mutated record after
 * every authentication, and the exception fence that no vendor type leaks
 * past.
 *
 * Scoped: it holds nothing per-request itself, but its collaborators
 * (session-backed state) do.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\WebAuthn;

use Closure;
use PHPdot\Container\Attribute\Scoped;
use PHPdot\WebAuthn\Contract\CeremonyStoreInterface;
use PHPdot\WebAuthn\Contract\CredentialRecordRepositoryInterface;
use PHPdot\WebAuthn\Exception\CeremonyFailedException;
use PHPdot\WebAuthn\Exception\DuplicateCredentialException;
use PHPdot\WebAuthn\Exception\InvalidResponseException;
use PHPdot\WebAuthn\Exception\UnknownCredentialException;
use PHPdot\WebAuthn\Exception\WebauthnException;
use Throwable;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\CredentialRecord;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;

#[Scoped]
final class WebauthnService
{
    public function __construct(
        private readonly WebauthnEngine $engine,
        private readonly WebauthnConfig $config,
        private readonly CredentialRecordRepositoryInterface $records,
        private readonly CeremonyStoreInterface $ceremonies,
    ) {}

    /**
     * Begin registering a credential for a user. Returns the creation
     * options JSON for the browser; the ceremony state parks under the
     * challenge and lives once.
     *
     * @param WebauthnUser $user The user adding a credential
     *
     * @return string
     */
    public function beginRegistration(WebauthnUser $user): string
    {
        $excluded = [];

        foreach ($this->records->recordsForUserHandle($user->handle) as $recordJson) {
            $record = $this->recordFromJson($recordJson);
            $excluded[] = $record->getPublicKeyCredentialDescriptor();
        }

        $options = PublicKeyCredentialCreationOptions::create(
            new PublicKeyCredentialRpEntity($this->config->rpName, $this->config->rpId),
            new PublicKeyCredentialUserEntity($user->name, Base64Url::decode($user->handle), $user->displayName),
            $this->newChallenge(),
            $this->credentialParameters(),
            AuthenticatorSelectionCriteria::create(),
            $this->config->attestation,
            $excluded,
            $this->config->timeout,
        );

        return $this->park($options);
    }

    /**
     * Finish a registration: validate the attestation, refuse a duplicate
     * credential id, persist the record. Returns the record JSON.
     *
     * @param WebauthnUser $user The user completing registration
     * @param string $responseJson The browser's credential JSON
     *
     * @return string
     */
    public function finishRegistration(WebauthnUser $user, string $responseJson): string
    {
        $credential = $this->credentialFromJson($responseJson, AuthenticatorAttestationResponse::class);
        $options = $this->consumeCreationOptions($credential);

        $response = $credential->response;
        assert($response instanceof AuthenticatorAttestationResponse);

        try {
            $record = $this->engine->attestationValidator()->check(
                $response,
                $options,
                $this->config->rpId,
            );
        } catch (Throwable $failure) {
            throw WebauthnException::from($failure);
        }

        $credentialId = Base64Url::encode($record->publicKeyCredentialId);

        if ($this->records->exists($credentialId)) {
            throw DuplicateCredentialException::for($credentialId);
        }

        $recordJson = $this->engine->serializer()->serialize($record, 'json');
        $this->records->save($credentialId, $recordJson, $user->handle);

        return $recordJson;
    }

    /**
     * Begin an authentication. A null handle starts a discoverable (passkey)
     * ceremony with no credential hints; a handle constrains the ceremony to
     * that user's own credentials. Returns the request options JSON.
     *
     * @param null|string $userHandle The expected user's handle (base64url), null for discoverable
     *
     * @return string
     */
    public function beginAuthentication(null|string $userHandle = null): string
    {
        $allowed = [];

        if ($userHandle !== null) {
            foreach ($this->records->recordsForUserHandle($userHandle) as $recordJson) {
                $allowed[] = $this->recordFromJson($recordJson)->getPublicKeyCredentialDescriptor();
            }
        }

        $options = PublicKeyCredentialRequestOptions::create(
            $this->newChallenge(),
            $this->config->rpId,
            $allowed,
            PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_PREFERRED,
            $this->config->timeout,
        );

        return $this->park($options);
    }

    /**
     * Finish an authentication: validate the assertion, ALWAYS persist the
     * counter-mutated record (the signature counter is the clone detector —
     * an unsaved record silently disarms it). Returns the authenticated
     * user's handle (base64url) — whoever drove the ceremony maps it to an
     * actor.
     *
     * @param string $responseJson The browser's credential JSON
     *
     * @return string The user handle (base64url)
     */
    public function finishAuthentication(string $responseJson): string
    {
        $credential = $this->credentialFromJson($responseJson, AuthenticatorAssertionResponse::class);
        $options = $this->consumeRequestOptions($credential);

        $credentialId = Base64Url::encode($credential->rawId);
        $recordJson = $this->records->findByCredentialId($credentialId)
            ?? throw UnknownCredentialException::for($credentialId);

        $record = $this->recordFromJson($recordJson);

        $response = $credential->response;
        assert($response instanceof AuthenticatorAssertionResponse);

        try {
            $mutated = $this->engine->assertionValidator()->check(
                $record,
                $response,
                $options,
                $this->config->rpId,
                $record->userHandle,
            );
        } catch (Throwable $failure) {
            throw WebauthnException::from($failure);
        }

        $userHandle = Base64Url::encode($mutated->userHandle);

        $this->records->save($credentialId, $this->engine->serializer()->serialize($mutated, 'json'), $userHandle);

        return $userHandle;
    }

    /**
     * A fresh challenge, parked with its options under the challenge key.
     *
     * @param PublicKeyCredentialCreationOptions|PublicKeyCredentialRequestOptions $options The begun ceremony
     *
     * @return string The options JSON for the browser
     */
    private function park(PublicKeyCredentialCreationOptions|PublicKeyCredentialRequestOptions $options): string
    {
        $json = $this->engine->serializer()->serialize($options, 'json');
        $this->ceremonies->save(Base64Url::encode($options->challenge), $json);

        return $json;
    }

    /**
     * @return list<PublicKeyCredentialParameters>
     */
    private function credentialParameters(): array
    {
        $parameters = [];

        foreach ($this->config->algorithms as $identifier) {
            $parameters[] = PublicKeyCredentialParameters::createPk($identifier);
        }

        return $parameters;
    }

    /**
     * The browser payload as a typed credential, guarding the library's
     * silent array passthrough and the response-type mismatch.
     *
     * @param string $responseJson The raw browser payload
     * @param class-string $responseClass The ceremony's expected response type
     *
     * @return PublicKeyCredential
     */
    private function credentialFromJson(string $responseJson, string $responseClass): PublicKeyCredential
    {
        $parsed = $this->decode(function () use ($responseJson): mixed {
            return $this->engine->serializer()->deserialize($responseJson, PublicKeyCredential::class, 'json');
        });

        if (!$parsed instanceof PublicKeyCredential) {
            throw InvalidResponseException::because('the payload is missing its credential id');
        }

        if (!$parsed->response instanceof $responseClass) {
            throw InvalidResponseException::because('the response is for the other ceremony');
        }

        return $parsed;
    }

    /**
     * Take back the parked creation options for this credential's
     * challenge — once.
     *
     * @param PublicKeyCredential $credential The presented credential
     *
     * @return PublicKeyCredentialCreationOptions
     */
    private function consumeCreationOptions(PublicKeyCredential $credential): PublicKeyCredentialCreationOptions
    {
        $options = $this->consumeOptions($credential, PublicKeyCredentialCreationOptions::class);

        return $options instanceof PublicKeyCredentialCreationOptions
            ? $options
            : throw InvalidResponseException::because('the stored ceremony state is inconsistent');
    }

    /**
     * Take back the parked request options for this credential's
     * challenge — once.
     *
     * @param PublicKeyCredential $credential The presented credential
     *
     * @return PublicKeyCredentialRequestOptions
     */
    private function consumeRequestOptions(PublicKeyCredential $credential): PublicKeyCredentialRequestOptions
    {
        $options = $this->consumeOptions($credential, PublicKeyCredentialRequestOptions::class);

        return $options instanceof PublicKeyCredentialRequestOptions
            ? $options
            : throw InvalidResponseException::because('the stored ceremony state is inconsistent');
    }

    /**
     * @param PublicKeyCredential $credential The presented credential
     * @param class-string $optionsClass The ceremony's options type
     *
     * @return mixed
     */
    private function consumeOptions(PublicKeyCredential $credential, string $optionsClass): mixed
    {
        $challenge = Base64Url::encode($credential->response->clientDataJSON->challenge);

        $optionsJson = $this->ceremonies->consume($challenge)
            ?? throw new CeremonyFailedException('The challenge is unknown, consumed, or expired.');

        return $this->decode(fn(): mixed => $this->engine->serializer()->deserialize($optionsJson, $optionsClass, 'json'));
    }

    /**
     * A stored record JSON back into a record, guarding corrupt storage.
     *
     * @param string $recordJson The stored record
     *
     * @return CredentialRecord
     */
    private function recordFromJson(string $recordJson): CredentialRecord
    {
        $record = $this->decode(function () use ($recordJson): mixed {
            return $this->engine->serializer()->deserialize($recordJson, CredentialRecord::class, 'json');
        });

        if (!$record instanceof CredentialRecord) {
            throw InvalidResponseException::because('a stored credential record does not decode');
        }

        return $record;
    }

    /**
     * Run a decode through the mixed boundary, translating any decoding
     * failure at the fence — what comes back is guarded by the caller, not
     * trusted by type.
     *
     * @param Closure(): mixed $decode
     *
     * @return mixed
     */
    private function decode(Closure $decode): mixed
    {
        try {
            return $decode();
        } catch (Throwable $failure) {
            throw InvalidResponseException::because('the payload does not decode');
        }
    }

    /**
     * @return string The raw challenge bytes
     */
    private function newChallenge(): string
    {
        return random_bytes($this->config->challengeBytes);
    }
}
