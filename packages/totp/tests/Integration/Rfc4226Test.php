<?php

declare(strict_types=1);

namespace PHPdot\Totp\Tests\Integration;

use PHPdot\Totp\Otp\Hotp;
use PHPdot\Totp\Secret\Secret;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * RFC 4226 Appendix D — the authoritative HOTP test vectors.
 */
final class Rfc4226Test extends TestCase
{
    #[DataProvider('appendixD')]
    public function test_hotp_vectors(int $counter, string $expected): void
    {
        $hotp = new Hotp(new Secret('12345678901234567890'));

        self::assertSame($expected, $hotp->at($counter));
    }

    /**
     * @return iterable<int, array{int, string}>
     */
    public static function appendixD(): iterable
    {
        $codes = ['755224', '287082', '359152', '969429', '338314', '254676', '287922', '162583', '399871', '520489'];

        foreach ($codes as $counter => $code) {
            yield $counter => [$counter, $code];
        }
    }
}
