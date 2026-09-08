<?php

declare(strict_types=1);

namespace PHPdot\WebAuthn\Tests\Unit;

use PHPdot\WebAuthn\Base64Url;
use PHPdot\WebAuthn\Exception\ConfigurationException;
use PHPdot\WebAuthn\WebauthnUser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function str_repeat;

/**
 * The user handle is what an authenticator signs — empty or beyond the
 * library's 64 raw bytes never constructs.
 */
final class WebauthnUserTest extends TestCase
{
    #[Test]
    public function aHandleUpToSixtyFourBytesConstructs(): void
    {
        $user = new WebauthnUser(Base64Url::encode(str_repeat('a', 64)), 'alice', 'Alice');

        self::assertSame('alice', $user->name);
    }

    #[Test]
    public function anEmptyHandleIsRefused(): void
    {
        $this->expectException(ConfigurationException::class);

        new WebauthnUser('', 'alice');
    }

    #[Test]
    public function anOversizedHandleIsRefused(): void
    {
        $this->expectException(ConfigurationException::class);

        new WebauthnUser(Base64Url::encode(str_repeat('a', 65)), 'alice');
    }
}
