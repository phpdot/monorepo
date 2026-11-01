<?php

declare(strict_types=1);

/**
 * Finds where a header block ends: at its first blank line, however far, stray lines before it ignored as Stalwart and
 * Thunderbird read them; with no blank line at all, after the field and folded lines at its start, as Python reads it.
 *
 * @internal
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Parser\Header;

use PHPdot\Mail\Parser\Contract\SourceInterface;

final class HeaderBlock
{
    private const string FIELD = '/^[^\x00-\x20\x7f:]+[ \t]*:/';

    /**
     * The end of the header block starting at `$start` and the start of the body after it, reading at most `$limit`
     * bytes for the block (0 reads to `$end`).
     *
     * @return array{int, int}
     */
    public static function bounds(SourceInterface $source, int $start, int $end, int $limit): array
    {
        $window = $source->read($start, $limit > 0 ? min($end - $start, $limit + 4) : $end - $start);

        if (str_starts_with($window, "\r\n")) {
            return [$start, $start + 2];
        }

        $blank = strpos($window, "\r\n\r\n");

        if ($blank !== false) {
            return [$start + $blank + 2, $start + $blank + 4];
        }

        $beyond = strlen($window) < $end - $start ? $source->find("\r\n\r\n", $start + strlen($window) - 3, $end) : null;

        if ($beyond !== null) {
            return [$beyond + 2, $beyond + 4];
        }

        $at = 0;
        $length = strlen($window);
        $folds = false;

        while ($at < $length) {
            $lineEnd = strpos($window, "\r\n", $at);
            $line = substr($window, $at, ($lineEnd === false ? $length : $lineEnd) - $at);

            if ($line === '') {
                return [$start + $at, min($end, $start + $at + 2)];
            }

            $field = preg_match(self::FIELD, $line) === 1;

            if (!$field && !($folds && ($line[0] === ' ' || $line[0] === "\t"))) {
                return [$start + $at, $start + $at];
            }

            $folds = true;

            if ($lineEnd === false) {
                break;
            }

            $at = $lineEnd + 2;
        }

        return [$start + $length, $start + $length];
    }

    /**
     * How much of a header block is read: one byte past `maxHeaderSize` at most, so an oversized block is still seen
     * as one without being held whole.
     */
    public static function readable(int $length, int $limit): int
    {
        return $limit > 0 ? min($length, $limit + 1) : $length;
    }
}
