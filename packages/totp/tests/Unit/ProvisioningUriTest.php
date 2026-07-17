<?php

declare(strict_types=1);

namespace PHPdot\Totp\Tests\Unit;

use PHPdot\Totp\Enum\Algorithm;
use PHPdot\Totp\Exception\InvalidParameterException;
use PHPdot\Totp\Otp\Hotp;
use PHPdot\Totp\Otp\Totp;
use PHPdot\Totp\Secret\Secret;
use PHPUnit\Framework\TestCase;

final class ProvisioningUriTest extends TestCase
{
    public function test_totp_uri_structure(): void
    {
        $totp = new Totp(new Secret('12345678901234567890'));

        $uri = $totp->provisioningUri('alice@example.com', 'phpdot');

        self::assertStringStartsWith('otpauth://totp/phpdot:alice%40example.com?', $uri);
        self::assertStringContainsString('secret=GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', $uri);
        self::assertStringContainsString('issuer=phpdot', $uri);
        self::assertStringContainsString('algorithm=SHA1', $uri);
        self::assertStringContainsString('digits=6', $uri);
        self::assertStringContainsString('period=30', $uri);
    }

    public function test_spaces_are_percent_encoded_not_plus(): void
    {
        $uri = (new Totp(new Secret('12345678901234567890')))
            ->provisioningUri('alice', 'PHP Dot');

        self::assertStringContainsString('otpauth://totp/PHP%20Dot:alice?', $uri);
        self::assertStringContainsString('issuer=PHP%20Dot', $uri);
        self::assertStringNotContainsString('+', $uri);
    }

    public function test_hotp_uri_carries_counter(): void
    {
        $uri = (new Hotp(new Secret('12345678901234567890')))
            ->provisioningUri('alice', 'phpdot', 5);

        self::assertStringStartsWith('otpauth://hotp/phpdot:alice?', $uri);
        self::assertStringContainsString('counter=5', $uri);
    }

    public function test_non_default_parameters_appear(): void
    {
        $uri = (new Totp(new Secret('12345678901234567890'), Algorithm::Sha256, 8, 60))
            ->provisioningUri('alice', 'phpdot');

        self::assertStringContainsString('algorithm=SHA256', $uri);
        self::assertStringContainsString('digits=8', $uri);
        self::assertStringContainsString('period=60', $uri);
    }

    public function test_empty_issuer_throws(): void
    {
        $this->expectException(InvalidParameterException::class);

        (new Totp(new Secret('12345678901234567890')))->provisioningUri('alice', '');
    }
}
