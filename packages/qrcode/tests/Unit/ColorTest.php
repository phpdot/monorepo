<?php

declare(strict_types=1);

namespace PHPdot\QrCode\Tests\Unit;

use InvalidArgumentException;
use PHPdot\QrCode\Color;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ColorTest extends TestCase
{
    public function test_named_constructors(): void
    {
        self::assertSame('#000000', Color::black()->toHex());
        self::assertSame('#ffffff', Color::white()->toHex());
        self::assertTrue(Color::transparent()->isTransparent());
        self::assertFalse(Color::black()->isTransparent());
    }

    public function test_channels_are_validated(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Color(256, 0, 0);
    }

    public function test_negative_channel_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Color(0, -1, 0);
    }

    #[DataProvider('hexProvider')]
    public function test_from_hex(string $input, int $r, int $g, int $b, int $a): void
    {
        $color = Color::fromHex($input);

        self::assertSame($r, $color->red);
        self::assertSame($g, $color->green);
        self::assertSame($b, $color->blue);
        self::assertSame($a, $color->alpha);
    }

    /**
     * @return iterable<string, array{string, int, int, int, int}>
     */
    public static function hexProvider(): iterable
    {
        yield 'shorthand' => ['#0b5', 0x00, 0xbb, 0x55, 255];
        yield 'no hash' => ['0bb55a', 0x0b, 0xb5, 0x5a, 255];
        yield 'full' => ['#1a2b3c', 0x1a, 0x2b, 0x3c, 255];
        yield 'with alpha' => ['#1a2b3c80', 0x1a, 0x2b, 0x3c, 0x80];
    }

    public function test_invalid_hex_length_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Color::fromHex('#1234');
    }

    public function test_invalid_hex_digits_throw(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Color::fromHex('#zzzzzz');
    }

    public function test_to_css_opaque_uses_rgb(): void
    {
        self::assertSame('rgb(10,20,30)', (new Color(10, 20, 30))->toCss());
    }

    public function test_to_css_transparent_uses_rgba(): void
    {
        self::assertSame('rgba(10,20,30,0.502)', (new Color(10, 20, 30, 128))->toCss());
        self::assertSame('rgba(0,0,0,0)', (new Color(0, 0, 0, 0))->toCss());
    }

    public function test_gd_alpha_maps_to_inverted_range(): void
    {
        self::assertSame(0, (new Color(0, 0, 0, 255))->gdAlpha());
        self::assertSame(127, (new Color(0, 0, 0, 0))->gdAlpha());
    }
}
