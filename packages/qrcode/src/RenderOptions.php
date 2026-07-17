<?php

declare(strict_types=1);

/**
 * Immutable rendering parameters shared by every renderer.
 *
 * `size` is the target edge length of the square output in pixels, including
 * the quiet zone. `margin` is the quiet zone width measured in modules (the QR
 * specification recommends 4). Vector output (SVG) honours `size` exactly;
 * raster output (PNG) snaps each module to a whole number of pixels, so the
 * final image may be slightly smaller than `size`.
 *
 * Every mutator returns a new instance — the object never changes in place.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\QrCode;

use InvalidArgumentException;

final readonly class RenderOptions
{
    /**
     * Builds the rendering parameters, rejecting a sub-pixel size or negative margin.
     *
     * @param int $size
     * @param int $margin
     * @param Color $foreground
     * @param Color $background
     */
    public function __construct(
        public int $size = 300,
        public int $margin = 4,
        public Color $foreground = new Color(0, 0, 0),
        public Color $background = new Color(255, 255, 255),
    ) {
        if ($size < 1) {
            throw new InvalidArgumentException("Render size must be at least 1 pixel, got {$size}.");
        }

        if ($margin < 0) {
            throw new InvalidArgumentException("Render margin cannot be negative, got {$margin}.");
        }
    }

    /**
     * Returns a copy with the output edge length replaced.
     *
     * @param int $size
     *
     * @return self
     */
    public function withSize(int $size): self
    {
        return new self($size, $this->margin, $this->foreground, $this->background);
    }

    /**
     * Returns a copy with the quiet-zone width (in modules) replaced.
     *
     * @param int $margin
     *
     * @return self
     */
    public function withMargin(int $margin): self
    {
        return new self($this->size, $margin, $this->foreground, $this->background);
    }

    /**
     * Returns a copy with the module (foreground) color replaced.
     *
     * @param Color $foreground
     *
     * @return self
     */
    public function withForeground(Color $foreground): self
    {
        return new self($this->size, $this->margin, $foreground, $this->background);
    }

    /**
     * Returns a copy with the backdrop (background) color replaced.
     *
     * @param Color $background
     *
     * @return self
     */
    public function withBackground(Color $background): self
    {
        return new self($this->size, $this->margin, $this->foreground, $background);
    }
}
