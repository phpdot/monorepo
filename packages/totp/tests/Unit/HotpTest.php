<?php

declare(strict_types=1);

namespace PHPdot\Totp\Tests\Unit;

use PHPdot\Totp\Enum\Algorithm;
use PHPdot\Totp\Exception\InvalidParameterException;
use PHPdot\Totp\Otp\Hotp;
use PHPdot\Totp\Secret\Secret;
use PHPUnit\Framework\TestCase;

final class HotpTest extends TestCase
{
    private Hotp $hotp;

    protected function setUp(): void
    {
        $this->hotp = new Hotp(new Secret('12345678901234567890'));
    }

    public function test_known_value(): void
    {
        self::assertSame('755224', $this->hotp->at(0));
        self::assertSame('520489', $this->hotp->at(9));
    }

    public function test_negative_counter_throws(): void
    {
        $this->expectException(InvalidParameterException::class);

        $this->hotp->at(-1);
    }

    public function test_verify_matches_exact_counter_and_returns_it(): void
    {
        $result = $this->hotp->verify('359152', 2);

        self::assertTrue($result->passed);
        self::assertSame(2, $result->timestep);
    }

    public function test_verify_look_ahead_window_resynchronises(): void
    {
        // Code is for counter 5; server is at 3 but looks ahead 3.
        $result = $this->hotp->verify($this->hotp->at(5), 3, 3);

        self::assertTrue($result->passed);
        self::assertSame(5, $result->timestep);
    }

    public function test_verify_outside_window_fails(): void
    {
        $result = $this->hotp->verify($this->hotp->at(5), 3, 1);

        self::assertFalse($result->passed);
        self::assertNull($result->timestep);
    }

    public function test_digit_count_is_validated(): void
    {
        $this->expectException(InvalidParameterException::class);

        new Hotp(new Secret('12345678901234567890'), Algorithm::Sha1, 9);
    }
}
