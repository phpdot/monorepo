<?php

declare(strict_types=1);

namespace PHPdot\QrCode\Tests\Unit;

use PHPdot\QrCode\Enum\ErrorCorrection;
use PHPdot\QrCode\Enum\ImageFormat;
use PHPUnit\Framework\TestCase;

final class EnumTest extends TestCase
{
    public function test_error_correction_recovery_rates_increase(): void
    {
        self::assertLessThan(ErrorCorrection::Medium->recoveryRate(), ErrorCorrection::Low->recoveryRate());
        self::assertLessThan(ErrorCorrection::Quartile->recoveryRate(), ErrorCorrection::Medium->recoveryRate());
        self::assertLessThan(ErrorCorrection::High->recoveryRate(), ErrorCorrection::Quartile->recoveryRate());
    }

    public function test_error_correction_values(): void
    {
        self::assertSame('L', ErrorCorrection::Low->value);
        self::assertSame('H', ErrorCorrection::High->value);
    }

    public function test_image_format_mime_types(): void
    {
        self::assertSame('image/svg+xml', ImageFormat::Svg->mimeType());
        self::assertSame('image/png', ImageFormat::Png->mimeType());
    }

    public function test_image_format_extensions(): void
    {
        self::assertSame('svg', ImageFormat::Svg->extension());
        self::assertSame('png', ImageFormat::Png->extension());
    }
}
