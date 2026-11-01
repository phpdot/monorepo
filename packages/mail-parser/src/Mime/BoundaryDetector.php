<?php

declare(strict_types=1);

/**
 * Splits MIME multipart message bodies at boundary delimiters using efficient string scanning
 * without regular expressions.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Parser\Mime;

final class BoundaryDetector
{
    /**
     * Split body text at MIME boundary markers.
     *
     * @return array{preamble: string, parts: list<string>, epilogue: string}
     */
    public function split(string $body, string $boundary): array
    {
        $delimiter = '--' . $boundary;
        $closeDelimiter = $delimiter . '--';

        $body = str_replace("\r\n", "\n", $body);

        $preamble = '';
        $parts = [];
        $epilogue = '';
        $foundFirst = false;
        $foundClose = false;

        $firstPos = $this->findBoundaryLine($body, $delimiter, 0);

        if ($firstPos === false) {

            return ['preamble' => '', 'parts' => [$body], 'epilogue' => ''];
        }

        $preamble = substr($body, 0, $firstPos);

        $lineEnd = strpos($body, "\n", $firstPos);
        $line = $lineEnd !== false
            ? substr($body, $firstPos, $lineEnd - $firstPos)
            : substr($body, $firstPos);

        if (str_starts_with(rtrim($line), $closeDelimiter)) {
            return ['preamble' => $preamble, 'parts' => [], 'epilogue' => ''];
        }

        $contentStart = $lineEnd !== false ? $lineEnd + 1 : strlen($body);
        $searchFrom = $contentStart;

        while ($searchFrom < strlen($body)) {
            $nextPos = $this->findBoundaryLine($body, $delimiter, $searchFrom);

            if ($nextPos === false) {

                $parts[] = $this->restoreLineEndings(substr($body, $contentStart));
                break;
            }

            $partContent = substr($body, $contentStart, $nextPos - $contentStart);

            $partContent = rtrim($partContent, "\n");
            $parts[] = $this->restoreLineEndings($partContent);

            $lineEnd = strpos($body, "\n", $nextPos);
            $line = $lineEnd !== false
                ? substr($body, $nextPos, $lineEnd - $nextPos)
                : substr($body, $nextPos);

            if (str_starts_with(rtrim($line), $closeDelimiter)) {

                $epilogueStart = $lineEnd !== false ? $lineEnd + 1 : strlen($body);
                $epilogue = $this->restoreLineEndings(substr($body, $epilogueStart));
                $foundClose = true;
                break;
            }

            $contentStart = $lineEnd !== false ? $lineEnd + 1 : strlen($body);
            $searchFrom = $contentStart;
        }

        return [
            'preamble' => $this->restoreLineEndings($preamble),
            'parts' => $parts,
            'epilogue' => $epilogue,
        ];
    }

    /**
     * Find a boundary line at or after the given position.
     * The boundary must appear at the beginning of a line.
     */
    private function findBoundaryLine(string $body, string $delimiter, int $offset): int|false
    {
        $pos = $offset;

        while ($pos < strlen($body)) {
            $found = strpos($body, $delimiter, $pos);

            if ($found === false) {
                return false;
            }

            if ($found === 0 || $body[$found - 1] === "\n") {
                return $found;
            }

            $pos = $found + 1;
        }

        return false;
    }

    private function restoreLineEndings(string $text): string
    {

        return str_replace("\n", "\r\n", $text);
    }
}
