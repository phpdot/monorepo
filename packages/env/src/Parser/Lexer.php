<?php

declare(strict_types=1);

/**
 * Lexer
 *
 * Character-by-character tokenizer for .env file content.
 * Stateless — all state lives in local variables.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Env\Parser;

use PHPdot\Env\Exception\EncodingException;
use PHPdot\Env\Exception\ParseException;

final class Lexer
{
    /**
     * Tokenize .env file content into entries.
     *
     * @param string $content Raw file content.
     * @param string $file File path for error messages.
     *
     * @throws EncodingException On BOM or non-UTF-8.
     * @throws ParseException On syntax errors.
     *
     * @return list<Entry> Parsed entries.
     */
    public function tokenize(string $content, string $file = ''): array
    {
        $this->guardEncoding($content, $file);

        $content = str_replace("\r\n", "\n", $content);

        $pos = 0;
        $len = strlen($content);
        $line = 1;
        $entries = [];

        while ($pos < $len) {
            $this->skipWhitespaceAndBlankLines($content, $pos, $len, $line);

            if ($pos >= $len) {
                break;
            }

            if ($content[$pos] === '#') {
                $this->skipToNextLine($content, $pos, $len, $line);
                continue;
            }

            if ($this->matchExportPrefix($content, $pos, $len)) {
                $pos += 7;
            }

            $lineStart = $line;

            $key = $this->readKey($content, $pos, $len, $line, $file);

            $this->skipHorizontalWhitespace($content, $pos, $len);

            if ($pos >= $len || $content[$pos] !== '=') {
                throw new ParseException(
                    "Expected '=' after key '{$key}'" . ($file !== '' ? " in {$file}" : ''),
                    $lineStart,
                );
            }

            $pos++;

            $this->skipHorizontalWhitespace($content, $pos, $len);

            [$value, $interpolate] = $this->readValue($content, $pos, $len, $line, $lineStart, $file);

            $entries[] = new Entry($key, $value, $lineStart, $interpolate);
        }

        return $entries;
    }

    /**
     * Guard against BOM and non-UTF-8 content.
     *
     * @param string $content Raw file content.
     * @param string $file File path for error messages.
     *
     * @throws EncodingException On BOM or non-UTF-8.
     *
     * @return void
     */
    private function guardEncoding(string $content, string $file): void
    {
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            throw new EncodingException(
                'UTF-8 BOM detected' . ($file !== '' ? " in {$file}" : ''),
            );
        }

        if ($content !== '' && !mb_check_encoding($content, 'UTF-8')) {
            throw new EncodingException(
                'Content is not valid UTF-8' . ($file !== '' ? " in {$file}" : ''),
            );
        }
    }

    /**
     * Skip whitespace characters and blank lines, tracking line number.
     *
     * @param string $content File content.
     * @param int $pos Current position (modified by reference).
     * @param int $len Content length.
     * @param int $line Current line number (modified by reference).
     *
     * @return void
     */
    private function skipWhitespaceAndBlankLines(string $content, int &$pos, int $len, int &$line): void
    {
        while ($pos < $len && ($content[$pos] === ' ' || $content[$pos] === "\t" || $content[$pos] === "\n")) {
            if ($content[$pos] === "\n") {
                $line++;
            }
            $pos++;
        }
    }

    /**
     * Skip horizontal whitespace (spaces and tabs only).
     *
     * @param string $content File content.
     * @param int $pos Current position (modified by reference).
     * @param int $len Content length.
     *
     * @return void
     */
    private function skipHorizontalWhitespace(string $content, int &$pos, int $len): void
    {
        while ($pos < $len && ($content[$pos] === ' ' || $content[$pos] === "\t")) {
            $pos++;
        }
    }

    /**
     * Skip to the next line.
     *
     * @param string $content File content.
     * @param int $pos Current position (modified by reference).
     * @param int $len Content length.
     * @param int $line Current line number (modified by reference).
     *
     * @return void
     */
    private function skipToNextLine(string $content, int &$pos, int $len, int &$line): void
    {
        while ($pos < $len && $content[$pos] !== "\n") {
            $pos++;
        }

        if ($pos < $len) {
            $pos++;
            $line++;
        }
    }

    /**
     * Check if the current position starts with "export " prefix.
     *
     * @param string $content File content.
     * @param int $pos Current position.
     * @param int $len Content length.
     *
     * @return bool True if "export " prefix is found.
     */
    private function matchExportPrefix(string $content, int $pos, int $len): bool
    {
        return $pos + 7 <= $len && substr($content, $pos, 7) === 'export ';
    }

    /**
     * Read an environment variable key.
     *
     * @param string $content File content.
     * @param int $pos Current position (modified by reference).
     * @param int $len Content length.
     * @param int $line Current line number.
     * @param string $file File path for error messages.
     *
     * @throws ParseException If the key is empty or contains invalid characters.
     *
     * @return string The parsed key.
     */
    private function readKey(string $content, int &$pos, int $len, int $line, string $file): string
    {
        $start = $pos;

        if ($pos < $len && preg_match('/[A-Za-z_]/', $content[$pos]) === 1) {
            $pos++;
            while ($pos < $len && preg_match('/[A-Za-z0-9_]/', $content[$pos]) === 1) {
                $pos++;
            }
        }

        $key = substr($content, $start, $pos - $start);

        if ($key === '') {
            throw new ParseException(
                'Expected variable name' . ($file !== '' ? " in {$file}" : ''),
                $line,
            );
        }

        return $key;
    }

    /**
     * Read a value from the current position.
     *
     * @param string $content File content.
     * @param int $pos Current position (modified by reference).
     * @param int $len Content length.
     * @param int $line Current line number (modified by reference).
     * @param int $lineStart The line where this entry started.
     * @param string $file File path for error messages.
     *
     * @throws ParseException On unclosed quotes.
     *
     * @return array{string, bool} The parsed value and whether interpolation is active.
     */
    private function readValue(string $content, int &$pos, int $len, int &$line, int $lineStart, string $file): array
    {
        if ($pos >= $len || $content[$pos] === "\n") {
            if ($pos < $len) {
                $pos++;
                $line++;
            }
            return ['', true];
        }

        if ($content[$pos] === '"') {
            return $this->readDoubleQuotedValue($content, $pos, $len, $line, $lineStart, $file);
        }

        if ($content[$pos] === "'") {
            return $this->readSingleQuotedValue($content, $pos, $len, $line, $lineStart, $file);
        }

        return $this->readUnquotedValue($content, $pos, $len, $line);
    }

    /**
     * Read a double-quoted value with escape sequence processing.
     *
     * @param string $content File content.
     * @param int $pos Current position (modified by reference).
     * @param int $len Content length.
     * @param int $line Current line number (modified by reference).
     * @param int $lineStart The line where this entry started.
     * @param string $file File path for error messages.
     *
     * @throws ParseException On unclosed double quote.
     *
     * @return array{string, bool} The parsed value and interpolation flag (true).
     */
    private function readDoubleQuotedValue(string $content, int &$pos, int $len, int &$line, int $lineStart, string $file): array
    {
        $pos++;
        $value = '';

        while ($pos < $len) {
            $char = $content[$pos];

            if ($char === '\\' && $pos + 1 < $len) {
                $next = $content[$pos + 1];
                $pos += 2;

                $value .= match ($next) {
                    'n' => "\n",
                    'r' => "\r",
                    't' => "\t",
                    '"' => '"',
                    '\\' => '\\',
                    '$' => '$',
                    default => '\\' . $next,
                };

                if ($next === "\n") {
                    $line++;
                }

                continue;
            }

            if ($char === '"') {
                $pos++;
                $this->skipInlineComment($content, $pos, $len, $line);
                return [$value, true];
            }

            if ($char === "\n") {
                $line++;
            }

            $value .= $char;
            $pos++;
        }

        throw new ParseException(
            'Unclosed double quote' . ($file !== '' ? " in {$file}" : ''),
            $lineStart,
        );
    }

    /**
     * Read a single-quoted value with no escape processing.
     *
     * @param string $content File content.
     * @param int $pos Current position (modified by reference).
     * @param int $len Content length.
     * @param int $line Current line number (modified by reference).
     * @param int $lineStart The line where this entry started.
     * @param string $file File path for error messages.
     *
     * @throws ParseException On unclosed single quote.
     *
     * @return array{string, bool} The parsed value and interpolation flag (false).
     */
    private function readSingleQuotedValue(string $content, int &$pos, int $len, int &$line, int $lineStart, string $file): array
    {
        $pos++;
        $value = '';

        while ($pos < $len) {
            $char = $content[$pos];

            if ($char === "'") {
                $pos++;
                $this->skipInlineComment($content, $pos, $len, $line);
                return [$value, false];
            }

            if ($char === "\n") {
                $line++;
            }

            $value .= $char;
            $pos++;
        }

        throw new ParseException(
            'Unclosed single quote' . ($file !== '' ? " in {$file}" : ''),
            $lineStart,
        );
    }

    /**
     * Read an unquoted value until end of line or inline comment.
     *
     * @param string $content File content.
     * @param int $pos Current position (modified by reference).
     * @param int $len Content length.
     * @param int $line Current line number (modified by reference).
     *
     * @return array{string, bool} The parsed value and interpolation flag (true).
     */
    private function readUnquotedValue(string $content, int &$pos, int $len, int &$line): array
    {
        $value = '';

        while ($pos < $len && $content[$pos] !== "\n") {
            if ($content[$pos] === ' ' && $pos + 1 < $len && $content[$pos + 1] === '#') {
                break;
            }

            $value .= $content[$pos];
            $pos++;
        }

        if ($pos < $len && $content[$pos] === "\n") {
            $pos++;
            $line++;
        }

        return [rtrim($value), true];
    }

    /**
     * Skip an optional inline comment after a quoted value.
     *
     * @param string $content File content.
     * @param int $pos Current position (modified by reference).
     * @param int $len Content length.
     * @param int $line Current line number (modified by reference).
     *
     * @return void
     */
    private function skipInlineComment(string $content, int &$pos, int $len, int &$line): void
    {
        while ($pos < $len && $content[$pos] !== "\n") {
            $pos++;
        }

        if ($pos < $len) {
            $pos++;
            $line++;
        }
    }
}
