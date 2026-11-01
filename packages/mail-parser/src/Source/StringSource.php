<?php

declare(strict_types=1);

/**
 * A message held in memory, its line endings made CRLF on the way in unless its bytes are to be kept as given.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Parser\Source;

use PHPdot\Mail\Parser\Contract\SourceInterface;

final class StringSource implements SourceInterface
{
    private const int WINDOW = 65_536;

    private readonly string $bytes;

    private int $windowStart = 0;

    private string $window = '';

    public function __construct(string $bytes, bool $canonical = true)
    {
        $this->bytes = $canonical ? LineEndings::canonical($bytes) : $bytes;
    }

    public function length(): int
    {
        return strlen($this->bytes);
    }

    public function read(int $offset, int $length): string
    {
        return $length <= 0 ? '' : substr($this->bytes, $offset, $length);
    }

    /**
     * Searches the bytes between `$from` and `$to` in a window kept between searches, so searching again from a near
     * offset copies nothing and each byte is copied about once.
     */
    public function find(string $needle, int $from, int $to): int|null
    {
        $width = strlen($needle);
        $to = min($to, strlen($this->bytes));
        $at = max(0, $from);

        while ($width > 0 && $at + $width <= $to) {
            if ($at < $this->windowStart || $at + $width > $this->windowStart + strlen($this->window)) {
                $this->windowStart = $at;
                $this->window = substr($this->bytes, $at, max(self::WINDOW, 2 * $width));
            }

            $found = strpos($this->window, $needle, $at - $this->windowStart);

            if ($found !== false) {
                return $this->windowStart + $found + $width <= $to ? $this->windowStart + $found : null;
            }

            $windowEnd = $this->windowStart + strlen($this->window);

            if ($windowEnd >= $to) {
                return null;
            }

            $at = max($at + 1, $windowEnd - $width + 1);
        }

        return null;
    }
}
