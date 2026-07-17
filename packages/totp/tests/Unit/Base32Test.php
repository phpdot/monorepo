<?php

declare(strict_types=1);

namespace PHPdot\Totp\Tests\Unit;

use PHPdot\Totp\Exception\InvalidSecretException;
use PHPdot\Totp\Secret\Base32;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class Base32Test extends TestCase
{
    /**
     * RFC 4648 §10 test vectors (encoded here without the `=` padding this
     * package emits).
     */
    #[DataProvider('rfc4648Provider')]
    public function test_encode_matches_rfc4648(string $plain, string $base32): void
    {
        self::assertSame($base32, Base32::encode($plain));
    }

    #[DataProvider('rfc4648Provider')]
    public function test_decode_accepts_padded_rfc4648(string $plain, string $base32): void
    {
        // Pad back to a multiple of 8 to feed canonical padded input.
        $padded = str_pad($base32, (int) (ceil(strlen($base32) / 8) * 8), '=');

        self::assertSame($plain, Base32::decode($padded));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function rfc4648Provider(): iterable
    {
        yield 'empty' => ['', ''];
        yield 'f' => ['f', 'MY'];
        yield 'fo' => ['fo', 'MZXQ'];
        yield 'foo' => ['foo', 'MZXW6'];
        yield 'foob' => ['foob', 'MZXW6YQ'];
        yield 'fooba' => ['fooba', 'MZXW6YTB'];
        yield 'foobar' => ['foobar', 'MZXW6YTBOI'];
    }

    public function test_decode_is_case_insensitive_and_ignores_spaces(): void
    {
        self::assertSame('foobar', Base32::decode('mzxw 6ytb oi'));
    }

    public function test_roundtrip_preserves_arbitrary_bytes(): void
    {
        $bytes = random_bytes(33);

        self::assertSame($bytes, Base32::decode(Base32::encode($bytes)));
    }

    public function test_invalid_character_throws(): void
    {
        $this->expectException(InvalidSecretException::class);

        Base32::decode('MZXW6YTB1'); // '1' is not in the Base32 alphabet
    }
}
