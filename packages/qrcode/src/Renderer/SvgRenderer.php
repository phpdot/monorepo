<?php

declare(strict_types=1);

/**
 * Renders a QR matrix to a self-contained SVG document.
 *
 * Output is a pure string with no external dependencies and no extension
 * required, so it is fully coroutine-safe. The drawing uses a `viewBox` of one
 * unit per module, so `size` is honoured exactly and the result scales without
 * loss. Dark modules are merged into horizontal runs to keep the document
 * small.
 *
 * Stateless — registered as a singleton.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\QrCode\Renderer;

use PHPdot\Container\Attribute\Singleton;
use PHPdot\QrCode\Color;
use PHPdot\QrCode\Contract\RendererInterface;
use PHPdot\QrCode\Enum\ImageFormat;
use PHPdot\QrCode\Matrix;
use PHPdot\QrCode\RenderOptions;

#[Singleton]
final class SvgRenderer implements RendererInterface
{
    public function render(Matrix $matrix, RenderOptions $options): string
    {
        $count = $matrix->size + ($options->margin * 2);

        $svg = '<?xml version="1.0" encoding="UTF-8"?>'
            . sprintf(
                '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d" shape-rendering="crispEdges">',
                $options->size,
                $options->size,
                $count,
                $count,
            );

        if ($options->background->alpha > 0) {
            $svg .= sprintf(
                '<rect width="%d" height="%d"%s/>',
                $count,
                $count,
                $this->fill($options->background),
            );
        }

        $svg .= sprintf('<g%s>', $this->fill($options->foreground));
        $svg .= $this->modules($matrix, $options->margin);
        $svg .= '</g></svg>';

        return $svg;
    }

    public function format(): ImageFormat
    {
        return ImageFormat::Svg;
    }

    /**
     * Emits the dark modules as run-length-merged, one-unit-tall `<rect>` runs.
     *
     * @param Matrix $matrix
     * @param int $margin
     *
     * @return string
     */
    private function modules(Matrix $matrix, int $margin): string
    {
        $rects = '';

        for ($y = 0; $y < $matrix->size; ++$y) {
            $runStart = null;

            for ($x = 0; $x < $matrix->size; ++$x) {
                if ($matrix->isDark($x, $y)) {
                    $runStart ??= $x;
                    continue;
                }

                if ($runStart !== null) {
                    $rects .= $this->rect($runStart + $margin, $y + $margin, $x - $runStart);
                    $runStart = null;
                }
            }

            if ($runStart !== null) {
                $rects .= $this->rect($runStart + $margin, $y + $margin, $matrix->size - $runStart);
            }
        }

        return $rects;
    }

    /**
     * Builds one `<rect>` spanning `$width` modules at the given position.
     *
     * @param int $x
     * @param int $y
     * @param int $width
     *
     * @return string
     */
    private function rect(int $x, int $y, int $width): string
    {
        return sprintf('<rect x="%d" y="%d" width="%d" height="1"/>', $x, $y, $width);
    }

    /**
     * Builds the `fill` attribute (plus `fill-opacity` when translucent) for a color.
     *
     * @param Color $color
     *
     * @return string
     */
    private function fill(Color $color): string
    {
        $fill = sprintf(' fill="%s"', $color->toHex());

        if ($color->isTransparent()) {
            $fill .= sprintf(
                ' fill-opacity="%s"',
                rtrim(rtrim(sprintf('%.3f', $color->alpha / 255), '0'), '.'),
            );
        }

        return $fill;
    }
}
