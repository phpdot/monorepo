<?php

declare(strict_types=1);

/**
 * Renders a QR matrix to PNG binary using the GD extension.
 *
 * Each module is snapped to a whole number of pixels, so the final image edge
 * is the largest multiple of the module count that does not exceed
 * `RenderOptions::$size`. Alpha is preserved, so a transparent background
 * produces a PNG with a real alpha channel.
 *
 * GD work is CPU-bound and blocks the current coroutine for its duration;
 * prefer {@see SvgRenderer} on hot paths and reach for PNG when a raster is
 * required. Stateless — registered as a singleton.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\QrCode\Renderer;

use GdImage;
use PHPdot\Container\Attribute\Singleton;
use PHPdot\QrCode\Color;
use PHPdot\QrCode\Contract\RendererInterface;
use PHPdot\QrCode\Enum\ImageFormat;
use PHPdot\QrCode\Exception\RenderException;
use PHPdot\QrCode\Matrix;
use PHPdot\QrCode\RenderOptions;

#[Singleton]
final class PngRenderer implements RendererInterface
{
    public function render(Matrix $matrix, RenderOptions $options): string
    {
        $count = $matrix->size + ($options->margin * 2);
        $modulePx = max(1, intdiv($options->size, max(1, $count)));
        $dimension = max(1, $modulePx * $count);

        $image = imagecreatetruecolor($dimension, $dimension);
        if ($image === false) {
            throw new RenderException("Failed to allocate a {$dimension}×{$dimension} GD image.");
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);

        $foreground = $this->color($image, $options->foreground);
        $background = $this->color($image, $options->background);

        imagefilledrectangle($image, 0, 0, $dimension - 1, $dimension - 1, $background);

        $this->paint($image, $matrix, $options->margin, $modulePx, $foreground);

        return $this->encode($image);
    }

    public function format(): ImageFormat
    {
        return ImageFormat::Png;
    }

    /**
     * Fills a square of pixels for every dark module in the matrix.
     *
     * @param GdImage $image
     * @param Matrix $matrix
     * @param int $margin
     * @param int $modulePx
     * @param int $color
     *
     * @return void
     */
    private function paint(GdImage $image, Matrix $matrix, int $margin, int $modulePx, int $color): void
    {
        for ($y = 0; $y < $matrix->size; ++$y) {
            for ($x = 0; $x < $matrix->size; ++$x) {
                if ($matrix->isDark($x, $y) === false) {
                    continue;
                }

                $left = ($x + $margin) * $modulePx;
                $top = ($y + $margin) * $modulePx;

                imagefilledrectangle(
                    $image,
                    $left,
                    $top,
                    $left + $modulePx - 1,
                    $top + $modulePx - 1,
                    $color,
                );
            }
        }
    }

    /**
     * Allocates an alpha-aware color in the image palette, throwing on failure.
     *
     * @param GdImage $image
     * @param Color $color
     *
     * @return int
     */
    private function color(GdImage $image, Color $color): int
    {
        $allocated = imagecolorallocatealpha(
            $image,
            $color->red,
            $color->green,
            $color->blue,
            $color->gdAlpha(),
        );

        if ($allocated === false) {
            throw new RenderException("Failed to allocate color {$color->toHex()} in the GD palette.");
        }

        return $allocated;
    }

    /**
     * Captures GD's PNG output from the output buffer and returns it as a string.
     *
     * @param GdImage $image
     *
     * @return string
     */
    private function encode(GdImage $image): string
    {
        if (ob_start() === false) {
            throw new RenderException('GD failed to start the output buffer for PNG encoding.');
        }

        $ok = imagepng($image);
        $png = ob_get_clean();

        if ($ok === false) {
            throw new RenderException('GD failed to encode the image as PNG.');
        }

        return $png;
    }
}
