<?php

declare(strict_types=1);

/**
 * Parsed stack trace with code snippets for each frame.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\ErrorHandler\Context;

final readonly class StackTrace
{
    /**
     * A parsed stack trace: an ordered list of frames with code snippets.
     *
     * @param list<Frame> $frames Stack frames ordered from most recent to oldest
     */
    public function __construct(
        public array $frames,
    ) {}

    /**
     * Build a StackTrace from a Throwable, extracting code snippets.
     *
     * @param \Throwable $exception The exception to parse
     * @param int $contextLines Number of lines to show above and below the error line
     *
     * @return self
     */
    public static function fromException(\Throwable $exception, int $contextLines = 9): self
    {
        $frames = [];

        $frames[] = self::buildFrame(
            $exception->getFile(),
            $exception->getLine(),
            null,
            null,
            $contextLines,
        );

        foreach ($exception->getTrace() as $trace) {
            $file = $trace['file'] ?? 'unknown';
            $line = $trace['line'] ?? 0;

            if ($file === $exception->getFile() && $line === $exception->getLine()) {
                continue;
            }

            $frames[] = self::buildFrame(
                $file,
                $line,
                $trace['class'] ?? null,
                $trace['function'],
                $contextLines,
            );
        }

        return new self($frames);
    }

    /**
     * Build a single Frame with its surrounding code snippet.
     *
     * @param string $file Absolute path of the frame's file
     * @param int $line Line number within that file
     * @param ?string $class Declaring class name, or null for a free function
     * @param ?string $function Function or method name
     * @param int $contextLines Number of lines to show above and below the frame line
     *
     * @return Frame
     */
    private static function buildFrame(
        string $file,
        int $line,
        ?string $class,
        ?string $function,
        int $contextLines,
    ): Frame {
        $snippet = self::extractCodeSnippet($file, $line, $contextLines);
        $isApplication = !str_contains($file, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR);

        return new Frame(
            file: $file,
            line: $line,
            class: $class,
            function: $function,
            codeSnippet: $snippet,
            isApplication: $isApplication,
        );
    }

    /**
     * Extract lines of code around a given line number.
     *
     * @param string $file Absolute path of the file to read
     * @param int $line Line number to centre the snippet on
     * @param int $contextLines Number of lines to include above and below
     *
     * @return list<CodeLine>
     */
    private static function extractCodeSnippet(string $file, int $line, int $contextLines): array
    {
        if (!is_file($file) || !is_readable($file)) {
            return [];
        }

        $lines = file($file);
        if ($lines === false) {
            return [];
        }

        $start = max(0, $line - $contextLines - 1);
        $end = min(count($lines), $line + $contextLines);

        $snippet = [];
        for ($i = $start; $i < $end; $i++) {
            $lineNumber = $i + 1;
            $snippet[] = new CodeLine(
                lineNumber: $lineNumber,
                code: rtrim($lines[$i], "\r\n"),
                isHighlighted: $lineNumber === $line,
            );
        }

        return $snippet;
    }
}
