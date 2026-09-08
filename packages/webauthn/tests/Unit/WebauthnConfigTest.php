<?php

declare(strict_types=1);

namespace PHPdot\WebAuthn\Tests\Unit;

use PHPdot\WebAuthn\Exception\ConfigurationException;
use PHPdot\WebAuthn\WebauthnConfig;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The relying party refuses to run half-configured: no id, no origins, weak
 * challenges, or unknown algorithms never construct — and the shipped
 * default algorithm set is the wide one real passkeys need.
 */
final class WebauthnConfigTest extends TestCase
{
    #[Test]
    public function theDefaultsAreWideAndValid(): void
    {
        $config = new WebauthnConfig(rpId: 'example.test', rpName: 'Example', allowedOrigins: ['https://example.test']);

        self::assertSame([-7, -35, -36, -257, -258, -259, -37, -38, -39, -8], $config->algorithms);
        self::assertSame(32, $config->challengeBytes);
        self::assertSame(300, $config->ceremonyTtl);
        self::assertSame('none', $config->attestation);
    }

    #[Test]
    public function aMissingRpIdIsRefused(): void
    {
        $this->expectException(ConfigurationException::class);

        new WebauthnConfig(rpId: '', allowedOrigins: ['https://example.test']);
    }

    #[Test]
    public function noAllowedOriginsIsRefused(): void
    {
        $this->expectException(ConfigurationException::class);

        new WebauthnConfig(rpId: 'example.test', allowedOrigins: []);
    }

    #[Test]
    public function aShortChallengeIsRefused(): void
    {
        $this->expectException(ConfigurationException::class);

        new WebauthnConfig(rpId: 'example.test', allowedOrigins: ['https://example.test'], challengeBytes: 8);
    }

    #[Test]
    public function anUnknownAlgorithmIsRefused(): void
    {
        $this->expectException(ConfigurationException::class);

        new WebauthnConfig(rpId: 'example.test', allowedOrigins: ['https://example.test'], algorithms: [-9999]);
    }
}
