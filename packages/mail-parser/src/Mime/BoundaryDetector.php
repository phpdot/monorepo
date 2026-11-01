<?php

declare(strict_types=1);

/**
 * Finds the parts of a multipart body between its delimiter lines (RFC 2046 §5.1.1), by offset into its source.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Parser\Mime;

use Generator;
use PHPdot\Mail\Parser\Contract\SourceInterface;
use PHPdot\Mail\Parser\Source\StringSource;

final class BoundaryDetector
{
    /**
     * Splits a body held in memory into its preamble, parts and epilogue, each with CRLF line endings.
     *
     * @return array{preamble: string, parts: list<string>, epilogue: string}
     */
    public function split(string $body, string $boundary): array
    {
        $source = new StringSource($body);
        $end = $source->length();
        $parts = [];
        $after = null;

        foreach ($this->partRanges($source, $boundary, 0, $end) as [$start, $stop]) {
            $parts[] = $source->read($start, $stop - $start);
            $after = $stop;
        }

        $opening = $this->findBoundaryLine($source, '--' . $boundary, 0, $end);

        if ($opening === null) {
            return ['preamble' => '', 'parts' => $parts, 'epilogue' => ''];
        }

        $close = $this->closingLine($source, $boundary, $after ?? $opening, $end);

        return [
            'preamble' => rtrim($source->read(0, $opening), "\r\n"),
            'parts' => $parts,
            'epilogue' => $close === null ? '' : $source->read($close, $end - $close),
        ];
    }

    /**
     * The offsets of each part between `$start` and `$end` of `$source`, found one at a time, so a caller reads only
     * the parts it keeps; with no delimiter line at all the whole range is one part.
     *
     * @return Generator<int, array{int, int}>
     */
    public function partRanges(SourceInterface $source, string $boundary, int $start, int $end): Generator
    {
        $delimiter = '--' . $boundary;
        $first = $this->findBoundaryLine($source, $delimiter, $start, $end);

        if ($first === null) {
            yield [$start, $end];

            return;
        }

        [$contentStart, $closes] = $this->afterBoundaryLine($source, $first, $end, $delimiter);

        while (!$closes && $contentStart < $end) {
            $next = $this->findBoundaryLine($source, $delimiter, $contentStart, $end);

            if ($next === null) {
                yield [$contentStart, $end];

                return;
            }

            yield [$contentStart, $this->trimLineBreaks($source, $contentStart, $next)];
            [$contentStart, $closes] = $this->afterBoundaryLine($source, $next, $end, $delimiter);
        }
    }

    /**
     * Where the content after the delimiter line at `$at` starts, and whether that line closes the multipart.
     *
     * @return array{int, bool}
     */
    private function afterBoundaryLine(SourceInterface $source, int $at, int $end, string $delimiter): array
    {
        $lineEnd = $source->find("\n", $at, $end) ?? $end;
        $closes = $source->read($at + strlen($delimiter), 2) === '--';

        return [min($lineEnd + 1, $end), $closes];
    }

    /**
     * The end of a part's content without the one line break that belongs to the delimiter after it (RFC 2046 §5.1.1).
     */
    private function trimLineBreaks(SourceInterface $source, int $start, int $end): int
    {
        return $end - $start >= 2 && $source->read($end - 2, 2) === "\r\n" ? $end - 2 : $end;
    }

    private function closingLine(SourceInterface $source, string $boundary, int $from, int $end): int|null
    {
        $at = $this->findBoundaryLine($source, '--' . $boundary, $from, $end);

        while ($at !== null && $source->read($at + strlen($boundary) + 2, 2) !== '--') {
            $at = $this->findBoundaryLine($source, '--' . $boundary, $at + 1, $end);
        }

        return $at === null ? null : $this->afterBoundaryLine($source, $at, $end, '--' . $boundary)[0];
    }

    /**
     * The next delimiter line at or after `$offset` and before `$end`: one that starts a line and holds the delimiter,
     * an optional closing `--` and transport padding only.
     */
    private function findBoundaryLine(SourceInterface $source, string $delimiter, int $offset, int $end): int|null
    {
        $pos = $offset;

        while ($pos < $end) {
            $found = $source->find($delimiter, $pos, $end);

            if ($found === null) {
                return null;
            }

            if (($found === 0 || $source->read($found - 1, 1) === "\n") && $this->endsDelimiterLine($source, $found + strlen($delimiter), $end)) {
                return $found;
            }

            $pos = $found + 1;
        }

        return null;
    }

    /**
     * Whether only an optional closing `--` and transport padding of any length follow the delimiter to its line's end.
     */
    private function endsDelimiterLine(SourceInterface $source, int $at, int $end): bool
    {
        if ($source->read($at, min(2, $end - $at)) === '--') {
            $at += 2;
        }

        while ($at < $end) {
            $tail = $source->read($at, min(256, $end - $at));
            $rest = ltrim($tail, " \t");

            if ($rest !== '') {
                return str_starts_with($rest, "\r\n") || str_starts_with($rest, "\n");
            }

            $at += strlen($tail);
        }

        return true;
    }
}
