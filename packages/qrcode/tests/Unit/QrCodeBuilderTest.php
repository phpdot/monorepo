<?php

declare(strict_types=1);

namespace PHPdot\QrCode\Tests\Unit;

use PHPdot\QrCode\Color;
use PHPdot\QrCode\Encoder\Encoder;
use PHPdot\QrCode\QrCodeBuilder;
use PHPdot\QrCode\Renderer\DataUriRenderer;
use PHPdot\QrCode\Renderer\PngRenderer;
use PHPdot\QrCode\Renderer\SvgRenderer;
use PHPUnit\Framework\TestCase;

final class QrCodeBuilderTest extends TestCase
{
    private QrCodeBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new QrCodeBuilder(
            new Encoder(),
            new SvgRenderer(),
            new PngRenderer(),
            new DataUriRenderer(),
        );
    }

    public function test_render_option_setters_are_immutable(): void
    {
        $changed = $this->builder
            ->size(120)
            ->margin(2)
            ->foreground(Color::fromHex('#0b5'))
            ->background(Color::transparent());

        // Original keeps defaults.
        self::assertSame(300, $this->builder->options()->size);
        self::assertSame(4, $this->builder->options()->margin);
        self::assertSame('#000000', $this->builder->options()->foreground->toHex());

        self::assertSame(120, $changed->options()->size);
        self::assertSame(2, $changed->options()->margin);
        self::assertSame('#00bb55', $changed->options()->foreground->toHex());
        self::assertTrue($changed->options()->background->isTransparent());
        self::assertNotSame($this->builder, $changed);
    }

    public function test_setters_preserve_unrelated_options(): void
    {
        $configured = $this->builder->size(200)->margin(1);

        // Changing the foreground keeps the previously set size and margin.
        $next = $configured->foreground(Color::white());

        self::assertSame(200, $next->options()->size);
        self::assertSame(1, $next->options()->margin);
    }
}
