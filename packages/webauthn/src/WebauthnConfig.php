<?php

declare(strict_types=1);

/**
 * The relying party, once: id, name, the origins ceremonies may run on, the
 * COSE algorithms advertised and accepted, challenge length, and the ceremony
 * TTL. Everything the library's narrow factory defaults would otherwise
 * decide for the worse — attestation format none-only, ES256/RS256-only — is
 * widened here or refused loudly.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\WebAuthn;

use Cose\Algorithm\Signature\ECDSA\ES256;
use Cose\Algorithm\Signature\ECDSA\ES384;
use Cose\Algorithm\Signature\ECDSA\ES512;
use Cose\Algorithm\Signature\EdDSA\Ed25519;
use Cose\Algorithm\Signature\RSA\PS256;
use Cose\Algorithm\Signature\RSA\PS384;
use Cose\Algorithm\Signature\RSA\PS512;
use Cose\Algorithm\Signature\RSA\RS256;
use Cose\Algorithm\Signature\RSA\RS384;
use Cose\Algorithm\Signature\RSA\RS512;
use PHPdot\Container\Attribute\Config;
use PHPdot\Container\Attribute\Singleton;
use PHPdot\WebAuthn\Exception\ConfigurationException;

#[Config('webauthn')]
#[Singleton]
final readonly class WebauthnConfig
{
    /**
     * The COSE identifiers the relying party accepts, keyed as the library's
     * algorithm manager expects. Shipped wide: real passkeys arrive on more
     * than ES256.
     */
    public const array ALGORITHM_MAP = [
        -7 => ES256::class,
        -35 => ES384::class,
        -36 => ES512::class,
        -257 => RS256::class,
        -258 => RS384::class,
        -259 => RS512::class,
        -37 => PS256::class,
        -38 => PS384::class,
        -39 => PS512::class,
        -8 => Ed25519::class,
    ];

    private const int MIN_CHALLENGE_BYTES = 16;

    /**
     * @param string $rpId The relying party id (the effective domain)
     * @param string $rpName The human name presented at registration
     * @param list<string> $allowedOrigins Full origins ceremonies may run on
     * @param int $timeout Options timeout in milliseconds handed to the browser
     * @param string $attestation Attestation conveyance preference (none, indirect, direct, required)
     * @param list<int> $algorithms COSE identifiers to advertise and accept
     * @param int $challengeBytes Challenge entropy in bytes
     * @param int $ceremonyTtl Seconds a begun ceremony may hang unfinished
     */
    public function __construct(
        public string $rpId = '',
        public string $rpName = '',
        public array $allowedOrigins = [],
        /** @var positive-int */
        public int $timeout = 60_000,
        public string $attestation = 'none',
        public array $algorithms = [-7, -35, -36, -257, -258, -259, -37, -38, -39, -8],
        /** @var positive-int */
        public int $challengeBytes = 32,
        public int $ceremonyTtl = 300,
    ) {
        if ($rpId === '') {
            throw ConfigurationException::missing('rpId is required');
        }

        if ($allowedOrigins === []) {
            throw ConfigurationException::missing('at least one allowed origin is required');
        }

        if ($challengeBytes < self::MIN_CHALLENGE_BYTES) {
            throw ConfigurationException::missing(
                sprintf('challengeBytes must be at least %d', self::MIN_CHALLENGE_BYTES),
            );
        }

        foreach ($algorithms as $algorithm) {
            if (!isset(self::ALGORITHM_MAP[$algorithm])) {
                throw ConfigurationException::missing(
                    sprintf('algorithm %d is not in the supported set', $algorithm),
                );
            }
        }
    }
}
