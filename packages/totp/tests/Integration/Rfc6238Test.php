<?php

declare(strict_types=1);

namespace PHPdot\Totp\Tests\Integration;

use PHPdot\Totp\Enum\Algorithm;
use PHPdot\Totp\Otp\Totp;
use PHPdot\Totp\Secret\Secret;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * RFC 6238 Appendix B — the authoritative TOTP test vectors across all three
 * algorithms. Each algorithm uses a different seed length (20/32/64 bytes), the
 * detail implementations most often get wrong.
 */
final class Rfc6238Test extends TestCase
{
    private const string SEED_SHA1 = '12345678901234567890';
    private const string SEED_SHA256 = '12345678901234567890123456789012';
    private const string SEED_SHA512 = '1234567890123456789012345678901234567890123456789012345678901234';

    #[DataProvider('appendixB')]
    public function test_totp_vectors(int $timestamp, Algorithm $algorithm, string $seed, string $expected): void
    {
        $totp = new Totp(new Secret($seed), $algorithm, 8);

        self::assertSame($expected, $totp->at($timestamp));
    }

    /**
     * @return iterable<string, array{int, Algorithm, string, string}>
     */
    public static function appendixB(): iterable
    {
        $table = [
            59 => ['94287082', '46119246', '90693936'],
            1111111109 => ['07081804', '68084774', '25091201'],
            1111111111 => ['14050471', '67062674', '99943326'],
            1234567890 => ['89005924', '91819424', '93441116'],
            2000000000 => ['69279037', '90698825', '38618901'],
            20000000000 => ['65353130', '77737706', '47863826'],
        ];

        foreach ($table as $timestamp => [$sha1, $sha256, $sha512]) {
            yield "sha1@{$timestamp}" => [$timestamp, Algorithm::Sha1, self::SEED_SHA1, $sha1];
            yield "sha256@{$timestamp}" => [$timestamp, Algorithm::Sha256, self::SEED_SHA256, $sha256];
            yield "sha512@{$timestamp}" => [$timestamp, Algorithm::Sha512, self::SEED_SHA512, $sha512];
        }
    }
}
