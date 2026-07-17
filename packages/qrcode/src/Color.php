<?php

declare(strict_types=1);

/**
 * An immutable 8-bit-per-channel RGBA color.
 *
 * Channels are `0`–`255`; `alpha` is `255` (opaque) by default and `0` is fully
 * transparent. The value object converts to the representations each renderer
 * needs: a CSS `rgb()`/`rgba()` string for SVG and a GD-style alpha (`0`–`127`)
 * for PNG.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\QrCode;

use InvalidArgumentException;

final readonly class Color
{
    /**
     * Creates an RGBA color, validating that every channel is within 0–255.
     *
     * @param int<0, 255> $red
     * @param int<0, 255> $green
     * @param int<0, 255> $blue
     * @param int<0, 255> $alpha
     */
    public function __construct(
        public int $red = 0,
        public int $green = 0,
        public int $blue = 0,
        public int $alpha = 255,
    ) {
        $this->assertChannel($red, 'red');
        $this->assertChannel($green, 'green');
        $this->assertChannel($blue, 'blue');
        $this->assertChannel($alpha, 'alpha');
    }

    /**
     * Opaque black (`#000000`) — the default foreground.
     *
     * @return self
     */
    public static function black(): self
    {
        return new self(0, 0, 0);
    }

    /**
     * Opaque white (`#ffffff`) — the default background.
     *
     * @return Color
     */
    public static function white(): self
    {
        return new self(255, 255, 255);
    }

    /**
     * Fully transparent — useful as a background for overlaying on artwork.
     *
     * @return Color
     */
    public static function transparent(): self
    {
        return new self(255, 255, 255, 0);
    }

    /**
     * Build a color from a hex string: `#rgb`, `#rrggbb`, or `#rrggbbaa`
     * (the leading `#` is optional).
     *
     * @param string $hex
     *
     * @throws InvalidArgumentException if the string is not a valid hex color
     *
     * @return Color
     */
    public static function fromHex(string $hex): self
    {
        $hex = ltrim($hex, '#');

        $expanded = match (strlen($hex)) {
            3 => $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2] . 'ff',
            6 => $hex . 'ff',
            8 => $hex,
            default => throw new InvalidArgumentException("Invalid hex color: '{$hex}'."),
        };

        if (ctype_xdigit($expanded) === false) {
            throw new InvalidArgumentException("Invalid hex color: '{$hex}'.");
        }

        return new self(
            self::channel(substr($expanded, 0, 2)),
            self::channel(substr($expanded, 2, 2)),
            self::channel(substr($expanded, 4, 2)),
            self::channel(substr($expanded, 6, 2)),
        );
    }

    /**
     * Whether this color has any transparency (`alpha` below `255`).
     *
     * @return bool
     */
    public function isTransparent(): bool
    {
        return $this->alpha < 255;
    }

    /**
     * The `#rrggbb` representation (alpha is not encoded).
     *
     * @return string
     */
    public function toHex(): string
    {
        return sprintf('#%02x%02x%02x', $this->red, $this->green, $this->blue);
    }

    /**
     * A CSS color string: `rgb(...)` when opaque, `rgba(...)` otherwise.
     *
     * @return string
     */
    public function toCss(): string
    {
        if ($this->isTransparent() === false) {
            return sprintf('rgb(%d,%d,%d)', $this->red, $this->green, $this->blue);
        }

        return sprintf(
            'rgba(%d,%d,%d,%s)',
            $this->red,
            $this->green,
            $this->blue,
            rtrim(rtrim(sprintf('%.3f', $this->alpha / 255), '0'), '.'),
        );
    }

    /**
     * The alpha channel mapped to GD's inverted `0` (opaque)–`127` (transparent)
     * range.
     *
     * @return int<0, 127>
     */
    public function gdAlpha(): int
    {
        return max(0, min(127, intdiv((255 - $this->alpha) * 127, 255)));
    }

    /**
     * Parse a two-character hex pair into a byte channel value.
     *
     * @param string $hex
     *
     * @return int<0, 255>
     */
    private static function channel(string $hex): int
    {
        return max(0, min(255, (int) hexdec($hex)));
    }

    /**
     * Guards that a channel value is within the 0–255 byte range.
     *
     * @param int $value
     * @param string $name
     *
     * @return void
     */
    private function assertChannel(int $value, string $name): void
    {
        if ($value < 0 || $value > 255) {
            throw new InvalidArgumentException(
                "Color channel '{$name}' must be between 0 and 255, got {$value}.",
            );
        }
    }
}
