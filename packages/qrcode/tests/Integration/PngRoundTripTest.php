<?php

declare(strict_types=1);

namespace PHPdot\QrCode\Tests\Integration;

use PHPdot\QrCode\Color;
use PHPdot\QrCode\Matrix;
use PHPdot\QrCode\QrCodeFactory;
use PHPdot\QrCode\Renderer\PngRenderer;
use PHPdot\QrCode\RenderOptions;
use PHPUnit\Framework\TestCase;

/**
 * Proves the PNG renderer faithfully paints every module: render to PNG, read
 * the pixels back, reconstruct the matrix, and assert it equals the source.
 * A renderer that drew the wrong cells would be caught here even though the
 * encoder (Bacon) is trusted for scannability.
 */
final class PngRoundTripTest extends TestCase
{
    private const int SIZE = 330;
    private const int MARGIN = 4;

    public function test_rendered_png_reproduces_every_module(): void
    {
        $matrix = (new QrCodeFactory())->make('PHPDOT ROUND-TRIP 0123456789')->matrix;

        $options = new RenderOptions(size: self::SIZE, margin: self::MARGIN);
        $png = (new PngRenderer())->render($matrix, $options);

        $reconstructed = $this->readModules($png, $matrix->size);

        self::assertSame($matrix->toArray(), $reconstructed);
    }

    public function test_transparent_background_yields_an_alpha_channel(): void
    {
        $matrix = (new QrCodeFactory())->make('alpha')->matrix;
        $options = (new RenderOptions(size: 120, margin: self::MARGIN))
            ->withBackground(Color::transparent());

        $png = (new PngRenderer())->render($matrix, $options);
        $image = imagecreatefromstring($png);
        self::assertNotFalse($image);

        // The top-left pixel sits in the quiet zone, so it must be fully transparent.
        $alpha = (imagecolorat($image, 0, 0) >> 24) & 0x7F;

        self::assertSame(127, $alpha);
    }

    /**
     * Sample the centre of each module and rebuild the boolean grid.
     *
     * @return list<list<bool>>
     */
    private function readModules(string $png, int $size): array
    {
        $image = imagecreatefromstring($png);
        self::assertNotFalse($image);

        $count = $size + (self::MARGIN * 2);
        $modulePx = intdiv(self::SIZE, $count);
        $offset = intdiv($modulePx, 2);

        $rows = [];
        for ($y = 0; $y < $size; ++$y) {
            $row = [];
            for ($x = 0; $x < $size; ++$x) {
                $px = (($x + self::MARGIN) * $modulePx) + $offset;
                $py = (($y + self::MARGIN) * $modulePx) + $offset;
                $row[] = (imagecolorat($image, $px, $py) & 0xFFFFFF) === 0;
            }
            $rows[] = $row;
        }

        return $rows;
    }
}
