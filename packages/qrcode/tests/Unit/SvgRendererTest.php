<?php

declare(strict_types=1);

namespace PHPdot\QrCode\Tests\Unit;

use PHPdot\QrCode\Color;
use PHPdot\QrCode\Enum\ImageFormat;
use PHPdot\QrCode\Matrix;
use PHPdot\QrCode\Renderer\SvgRenderer;
use PHPdot\QrCode\RenderOptions;
use PHPUnit\Framework\TestCase;

final class SvgRendererTest extends TestCase
{
    private SvgRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new SvgRenderer();
    }

    public function test_format(): void
    {
        self::assertSame(ImageFormat::Svg, $this->renderer->format());
    }

    public function test_produces_well_formed_svg(): void
    {
        $svg = $this->renderer->render($this->matrix(), new RenderOptions(size: 40, margin: 1));

        self::assertNotFalse(simplexml_load_string($svg));
        self::assertStringContainsString('width="40" height="40"', $svg);
        self::assertStringContainsString('viewBox="0 0 4 4"', $svg);
    }

    public function test_dark_modules_are_offset_by_the_margin(): void
    {
        $svg = $this->renderer->render($this->matrix(), new RenderOptions(size: 40, margin: 1));

        // Dark module at (0,0) shifted by margin 1 -> x=1,y=1.
        self::assertStringContainsString('<rect x="1" y="1" width="1" height="1"/>', $svg);
        // Dark module at (1,1) shifted by margin 1 -> x=2,y=2.
        self::assertStringContainsString('<rect x="2" y="2" width="1" height="1"/>', $svg);
    }

    public function test_horizontal_runs_are_merged(): void
    {
        // A full dark top row of three becomes a single width-3 rect; the
        // transparent background drops the backdrop rect, leaving exactly one.
        $matrix = new Matrix([
            [true, true, true],
            [false, false, false],
            [false, false, false],
        ], 3);

        $options = (new RenderOptions(size: 30, margin: 0))->withBackground(Color::transparent());
        $svg = $this->renderer->render($matrix, $options);

        self::assertStringContainsString('<rect x="0" y="0" width="3" height="1"/>', $svg);
        self::assertSame(1, substr_count($svg, '<rect'));
    }

    public function test_opaque_background_is_drawn(): void
    {
        $svg = $this->renderer->render($this->matrix(), new RenderOptions(size: 40, margin: 1));

        self::assertStringContainsString('<rect width="4" height="4" fill="#ffffff"/>', $svg);
    }

    public function test_transparent_background_is_omitted(): void
    {
        $options = (new RenderOptions(size: 40, margin: 1))->withBackground(Color::transparent());

        $svg = $this->renderer->render($this->matrix(), $options);

        self::assertStringNotContainsString('<rect width="4" height="4"', $svg);
    }

    private function matrix(): Matrix
    {
        return new Matrix([
            [true, false],
            [false, true],
        ], 2);
    }
}
