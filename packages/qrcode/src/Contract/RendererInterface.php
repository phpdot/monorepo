<?php

declare(strict_types=1);

/**
 * Turns a QR {@see Matrix} into the raw bytes of an image format.
 *
 * Implementations are stateless and return the encoded document (an SVG string,
 * PNG binary, etc.) for the given matrix and rendering options.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\QrCode\Contract;

use PHPdot\QrCode\Enum\ImageFormat;
use PHPdot\QrCode\Matrix;
use PHPdot\QrCode\RenderOptions;

interface RendererInterface
{
    /**
     * Render `$matrix` to this renderer's output format and return the raw bytes.
     *
     * @param Matrix $matrix
     * @param RenderOptions $options
     *
     * @return string
     */
    public function render(Matrix $matrix, RenderOptions $options): string;

    /**
     * The format this renderer produces.
     *
     * @return ImageFormat
     */
    public function format(): ImageFormat;
}
