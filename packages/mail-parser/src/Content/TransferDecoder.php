<?php

declare(strict_types=1);

/**
 * Undoes a transfer encoding over a stretch of a source a block at a time, so decoding any size of part holds one
 * block in memory; base64 drops what is not of its alphabet and reads each run between `=` signs on its own.
 *
 * @internal
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Parser\Content;

use Closure;
use PHPdot\Mail\Parser\Contract\SourceInterface;

final class TransferDecoder
{
    private const int BLOCK = 262_144;

    /**
     * Hands each decoded block to `$sink` and answers how many bytes were decoded.
     *
     * @param Closure(string): void $sink
     */
    public static function decode(SourceInterface $source, int $start, int $end, string $encoding, Closure $sink): int
    {
        return match (strtolower(trim($encoding))) {
            'base64' => self::base64($source, $start, $end, $sink),
            'quoted-printable' => self::quotedPrintable($source, $start, $end, $sink),
            default => self::identity($source, $start, $end, $sink),
        };
    }

    /**
     * @param Closure(string): void $sink
     */
    private static function identity(SourceInterface $source, int $start, int $end, Closure $sink): int
    {
        for ($at = $start; $at < $end; $at += self::BLOCK) {
            $sink($source->read($at, min(self::BLOCK, $end - $at)));
        }

        return max(0, $end - $start);
    }

    /**
     * @param Closure(string): void $sink
     */
    private static function base64(SourceInterface $source, int $start, int $end, Closure $sink): int
    {
        $pending = '';
        $size = 0;

        for ($at = $start; $at < $end; $at += self::BLOCK) {
            $pending .= preg_replace('/[^A-Za-z0-9+\/=]/', '', $source->read($at, min(self::BLOCK, $end - $at))) ?? '';
            $last = strrpos($pending, '=');
            $whole = $last === false ? intdiv(strlen($pending), 4) * 4 : $last + 1;
            $size += self::base64Runs(substr($pending, 0, $whole), $sink, $last !== false);
            $pending = substr($pending, $whole);
        }

        return $size + self::base64Runs($pending, $sink, true);
    }

    /**
     * Decodes runs of base64 separated by `=`; only a closed run may end in a partial block, padded as it ends.
     *
     * @param Closure(string): void $sink
     */
    private static function base64Runs(string $runs, Closure $sink, bool $closed): int
    {
        $size = 0;
        $pieces = explode('=', $runs);
        $last = count($pieces) - 1;

        foreach ($pieces as $index => $run) {
            if ($run === '') {
                continue;
            }

            $padded = $closed || $index < $last ? str_pad($run, strlen($run) + (4 - strlen($run) % 4) % 4, '=') : $run;
            $bytes = base64_decode($padded, true);

            if ($bytes !== false && $bytes !== '') {
                $sink($bytes);
                $size += strlen($bytes);
            }
        }

        return $size;
    }

    /**
     * @param Closure(string): void $sink
     */
    private static function quotedPrintable(SourceInterface $source, int $start, int $end, Closure $sink): int
    {
        $pending = '';
        $size = 0;

        for ($at = $start; $at < $end; $at += self::BLOCK) {
            $block = $source->read($at, min(self::BLOCK, $end - $at));
            $newline = strrpos($block, "\n");
            $cut = $newline !== false ? strlen($pending) + $newline + 1 : self::escapeSafeLength($pending . $block);
            $pending .= $block;
            $bytes = quoted_printable_decode(substr($pending, 0, $cut));
            $sink($bytes);
            $size += strlen($bytes);
            $pending = substr($pending, $cut);
        }

        $bytes = quoted_printable_decode($pending);
        $sink($bytes);

        return $size + strlen($bytes);
    }

    /**
     * How much of a stretch with no line break decodes on its own: all but a trailing `=` or `=X` an escape may finish.
     */
    private static function escapeSafeLength(string $text): int
    {
        $tail = strrpos(substr($text, -2), '=');

        return $tail === false ? strlen($text) : strlen($text) - 2 + $tail;
    }
}
