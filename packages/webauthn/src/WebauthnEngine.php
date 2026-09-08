<?php

declare(strict_types=1);

/**
 * The library, built once. The mutable CeremonyStepManagerFactory and the
 * serializer are configured here and never touched again — no post-build
 * setters run, so a singleton is safe on the coroutine law: everything the
 * ceremonies mutate lives in request-scoped records and state stores.
 *
 * Configured wide on purpose: none AND packed attestation support (real
 * passkey clients ignore a none preference — a spec SHOULD — and would
 * hard-fail at parse time otherwise), and the full COSE algorithm set from
 * the config, so an Ed25519 credential fails its ceremony, not the parse.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\WebAuthn;

use Cose\Algorithm\Manager;
use PHPdot\Container\Attribute\Singleton;
use Symfony\Component\Serializer\SerializerInterface;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AttestationStatement\PackedAttestationStatementSupport;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\Denormalizer\WebauthnSerializerFactory;

#[Singleton]
final class WebauthnEngine
{
    private readonly SerializerInterface $serializer;

    private readonly AuthenticatorAttestationResponseValidator $attestationValidator;

    private readonly AuthenticatorAssertionResponseValidator $assertionValidator;

    public function __construct(
        private readonly WebauthnConfig $config,
    ) {
        $algorithms = Manager::create();

        foreach ($config->algorithms as $identifier) {
            $algorithms->add(WebauthnConfig::ALGORITHM_MAP[$identifier]::create());
        }

        $supports = AttestationStatementSupportManager::create();
        $supports->add(new NoneAttestationStatementSupport());
        $supports->add(PackedAttestationStatementSupport::create($algorithms));

        $this->serializer = (new WebauthnSerializerFactory($supports))->create();

        $factory = new CeremonyStepManagerFactory();
        $factory->setAllowedOrigins($config->allowedOrigins);
        $factory->setAlgorithmManager($algorithms);
        $factory->setAttestationStatementSupportManager($supports);

        $this->attestationValidator = AuthenticatorAttestationResponseValidator::create($factory->creationCeremony());
        $this->assertionValidator = AuthenticatorAssertionResponseValidator::create($factory->requestCeremony());
    }

    public function serializer(): SerializerInterface
    {
        return $this->serializer;
    }

    public function attestationValidator(): AuthenticatorAttestationResponseValidator
    {
        return $this->attestationValidator;
    }

    public function assertionValidator(): AuthenticatorAssertionResponseValidator
    {
        return $this->assertionValidator;
    }
}
