<?php

declare(strict_types=1);

namespace PHPdot\QrCode\Tests\Integration;

use PHPdot\QrCode\Encoder\Encoder;
use PHPdot\QrCode\Enum\ErrorCorrection;
use PHPdot\QrCode\Exception\EncodingException;
use PHPUnit\Framework\TestCase;

final class EncodeTest extends TestCase
{
    private Encoder $encoder;

    protected function setUp(): void
    {
        $this->encoder = new Encoder();
    }

    public function test_alphanumeric_content_uses_alphanumeric_mode_at_version_one(): void
    {
        $qr = $this->encoder->encode('HELLO WORLD', ErrorCorrection::Medium);

        self::assertSame('ALPHANUMERIC', $qr->mode);
        self::assertSame(1, $qr->version);
        self::assertSame(21, $qr->matrix->size);
        self::assertSame(ErrorCorrection::Medium, $qr->errorCorrection);
    }

    public function test_numeric_content_uses_numeric_mode(): void
    {
        $qr = $this->encoder->encode('1234567890', ErrorCorrection::Low);

        self::assertSame('NUMERIC', $qr->mode);
    }

    public function test_unicode_content_uses_byte_mode(): void
    {
        $qr = $this->encoder->encode('héllo wörld', ErrorCorrection::Medium);

        self::assertSame('BYTE', $qr->mode);
    }

    public function test_symbol_dimension_matches_version(): void
    {
        // Version N has 17 + 4N modules per side.
        $qr = $this->encoder->encode(str_repeat('A', 50), ErrorCorrection::High);

        self::assertSame(17 + (4 * $qr->version), $qr->matrix->size);
    }

    public function test_higher_error_correction_needs_a_larger_or_equal_symbol(): void
    {
        $payload = str_repeat('PHPDOT-', 12);

        $low = $this->encoder->encode($payload, ErrorCorrection::Low);
        $high = $this->encoder->encode($payload, ErrorCorrection::High);

        self::assertGreaterThanOrEqual($low->version, $high->version);
    }

    public function test_finder_patterns_are_present_in_three_corners(): void
    {
        $matrix = $this->encoder->encode('FINDER', ErrorCorrection::Medium)->matrix;
        $last = $matrix->size - 1;

        // Each of the three finder patterns has a solid dark module in its
        // outermost corner; the fourth (bottom-right) corner has no finder.
        self::assertTrue($matrix->isDark(0, 0), 'top-left finder');
        self::assertTrue($matrix->isDark($last, 0), 'top-right finder');
        self::assertTrue($matrix->isDark(0, $last), 'bottom-left finder');

        // A finder pattern is a 7×7 dark ring around a 5×5 light ring; module
        // (1,1) is on that inner light ring, distinguishing a real finder from
        // incidental dark data.
        self::assertFalse($matrix->isDark(1, 1), 'top-left finder inner ring');
    }

    public function test_forcing_a_version_is_honoured(): void
    {
        $qr = $this->encoder->encode('short', ErrorCorrection::Low, forceVersion: 10);

        self::assertSame(10, $qr->version);
    }

    public function test_payload_too_large_for_forced_version_throws(): void
    {
        $this->expectException(EncodingException::class);

        $this->encoder->encode(str_repeat('A', 100), ErrorCorrection::High, forceVersion: 1);
    }

    public function test_invalid_version_number_throws_encoding_exception(): void
    {
        $this->expectException(EncodingException::class);

        $this->encoder->encode('x', ErrorCorrection::Low, forceVersion: 99);
    }
}
