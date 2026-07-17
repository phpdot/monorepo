<?php

declare(strict_types=1);

/**
 * Raster/vector output format produced by a renderer.
 *
 * Used by the data-URI renderer to build the correct MIME prefix and by
 * callers that persist output to derive a file extension.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\QrCode\Enum;

enum ImageFormat: string
{
    case Svg = 'svg';
    case Png = 'png';

    /**
     * The MIME type for this format, suitable for a `Content-Type` header or a
     * `data:` URI prefix.
     *
     * @return string
     */
    public function mimeType(): string
    {
        return match ($this) {
            self::Svg => 'image/svg+xml',
            self::Png => 'image/png',
        };
    }

    /**
     * The conventional file extension (without a leading dot).
     *
     * @return string
     */
    public function extension(): string
    {
        return $this->value;
    }
}
