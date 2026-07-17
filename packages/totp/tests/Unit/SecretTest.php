<?php

declare(strict_types=1);

namespace PHPdot\Totp\Tests\Unit;

use PHPdot\Totp\Exception\InvalidParameterException;
use PHPdot\Totp\Exception\InvalidSecretException;
use PHPdot\Totp\Secret\Secret;
use PHPUnit\Framework\TestCase;

final class SecretTest extends TestCase
{
    public function test_generate_produces_requested_length(): void
    {
        self::assertSame(20, strlen(Secret::generate()->bytes()));
        self::assertSame(32, strlen(Secret::generate(32)->bytes()));
    }

    public function test_generate_is_random(): void
    {
        self::assertNotSame(Secret::generate()->bytes(), Secret::generate()->bytes());
    }

    public function test_generate_at_minimum_boundary(): void
    {
        self::assertSame(16, strlen(Secret::generate(16)->bytes()));
    }

    public function test_generate_just_below_minimum_throws(): void
    {
        $this->expectException(InvalidParameterException::class);

        Secret::generate(15);
    }

    public function test_generate_well_below_minimum_throws(): void
    {
        $this->expectException(InvalidParameterException::class);

        Secret::generate(8);
    }

    public function test_empty_secret_throws(): void
    {
        $this->expectException(InvalidSecretException::class);

        new Secret('');
    }

    public function test_base32_roundtrip(): void
    {
        $secret = Secret::generate(20);

        self::assertSame($secret->bytes(), Secret::fromBase32($secret->toBase32())->bytes());
    }

    public function test_known_base32_value(): void
    {
        // Base32 of ASCII "12345678901234567890" (the RFC test seed).
        self::assertSame('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', (new Secret('12345678901234567890'))->toBase32());
    }
}
