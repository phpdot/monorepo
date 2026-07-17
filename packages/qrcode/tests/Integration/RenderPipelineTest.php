<?php

declare(strict_types=1);

namespace PHPdot\QrCode\Tests\Integration;

use PHPdot\QrCode\Color;
use PHPdot\QrCode\Enum\ErrorCorrection;
use PHPdot\QrCode\Enum\ImageFormat;
use PHPdot\QrCode\QrCodeFactory;
use PHPUnit\Framework\TestCase;

final class RenderPipelineTest extends TestCase
{
    private QrCodeFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new QrCodeFactory();
    }

    public function test_make_returns_a_value_object(): void
    {
        $qr = $this->factory->make('https://phpdot.com');

        self::assertGreaterThanOrEqual(21, $qr->matrix->size);
        self::assertSame(ErrorCorrection::Medium, $qr->errorCorrection);
    }

    public function test_svg_helper_produces_well_formed_svg(): void
    {
        $svg = $this->factory->svg('https://phpdot.com');

        self::assertStringStartsWith('<?xml', $svg);
        self::assertNotFalse(simplexml_load_string($svg));
    }

    public function test_png_helper_produces_a_png_signature(): void
    {
        $png = $this->factory->png('https://phpdot.com');

        self::assertStringStartsWith("\x89PNG\r\n\x1a\n", $png);
    }

    public function test_data_uri_helpers_carry_the_right_mime_type(): void
    {
        self::assertStringStartsWith(
            'data:image/svg+xml;base64,',
            $this->factory->dataUri('x', ImageFormat::Svg),
        );
        self::assertStringStartsWith(
            'data:image/png;base64,',
            $this->factory->dataUri('x', ImageFormat::Png),
        );
    }

    public function test_fluent_chain_applies_options(): void
    {
        $svg = $this->factory->create('https://phpdot.com')
            ->errorCorrection(ErrorCorrection::High)
            ->size(400)
            ->margin(2)
            ->foreground(Color::fromHex('#0b5'))
            ->toSvg();

        self::assertStringContainsString('width="400" height="400"', $svg);
        self::assertStringContainsString('fill="#00bb55"', $svg);
    }
}
