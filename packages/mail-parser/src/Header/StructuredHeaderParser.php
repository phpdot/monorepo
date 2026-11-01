<?php

declare(strict_types=1);

/**
 * Parses structured MIME headers (Content-Type, Content-Disposition) into value and parameter pairs
 * with RFC 2231 support.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Parser\Header;

use PHPdot\Mail\Parser\Result\MimeHeader;
use PHPdot\Mail\Parser\Rfc2231\ParameterDecoder;

final class StructuredHeaderParser
{
    /**
     * The most parameters one header reads, kept or not; a long filename's RFC 2231 sections number a few dozen.
     */
    private const int MAX_PARAMETERS = 1000;

    public function __construct(
        private readonly ParameterDecoder $parameterDecoder,
    ) {}

    public function parse(string $headerValue): MimeHeader
    {
        $headerValue = $this->stripComments($headerValue);
        $headerValue = trim($headerValue);

        if ($headerValue === '') {
            return new MimeHeader('');
        }

        $semicolonPos = $this->findUnquotedSemicolon($headerValue);

        if ($semicolonPos === false) {
            return new MimeHeader(strtolower(trim($headerValue)));
        }

        $value = strtolower(trim(substr($headerValue, 0, $semicolonPos)));
        $paramString = substr($headerValue, $semicolonPos + 1);

        $rawParams = $this->parseParams($paramString);
        $decodedParams = $this->parameterDecoder->decode($rawParams);

        return new MimeHeader($value, $decodedParams);
    }

    private function stripComments(string $value): string
    {
        $result = '';
        $depth = 0;
        $escaped = false;
        $inQuotes = false;

        for ($i = 0, $len = strlen($value); $i < $len; $i++) {
            $char = $value[$i];

            if ($escaped) {
                if ($inQuotes || $depth === 0) {
                    $result .= $char;
                }
                $escaped = false;
                continue;
            }

            if ($char === '\\') {
                if ($inQuotes) {
                    $result .= $char;
                    $escaped = true;
                    continue;
                }
                $escaped = true;
                if ($depth === 0) {
                    $result .= $char;
                }
                continue;
            }

            if ($char === '"') {
                $inQuotes = !$inQuotes;
                $result .= $char;
                continue;
            }

            if ($inQuotes) {
                $result .= $char;
                continue;
            }

            if ($char === '(') {
                $depth++;
                continue;
            }

            if ($char === ')' && $depth > 0) {
                $depth--;
                continue;
            }

            if ($depth === 0) {
                $result .= $char;
            }
        }

        return $result;
    }

    private function findUnquotedSemicolon(string $value): int|false
    {
        $inQuotes = false;
        $escaped = false;

        for ($i = 0, $len = strlen($value); $i < $len; $i++) {
            $char = $value[$i];

            if ($escaped) {
                $escaped = false;
                continue;
            }

            if ($char === '\\') {
                $escaped = true;
                continue;
            }

            if ($char === '"') {
                $inQuotes = !$inQuotes;
                continue;
            }

            if ($char === ';' && !$inQuotes) {
                return $i;
            }
        }

        return false;
    }

    /**
     * Keeps a parameter at its first occurrence; a name of digits alone is dropped, as PHP would make it an integer key.
     *
     * @param array<string, string> $params
     */
    private static function keep(array &$params, string $key, string $value): void
    {
        if (preg_match('/^-?[0-9]+(\*|$)/', $key) !== 1 && !array_key_exists($key, $params)) {
            $params[$key] = $value;
        }
    }

    /**
     * @return array<string, string>
     */
    private function parseParams(string $paramString): array
    {
        $params = [];
        $length = strlen($paramString);
        $i = 0;

        for ($read = 0; $read < self::MAX_PARAMETERS; $read++) {
            while ($i < $length && strpos(" \t\r\n;", $paramString[$i]) !== false) {
                $i++;
            }

            if ($i >= $length) {
                break;
            }

            $eqPos = $i + strcspn($paramString, '=;', $i);

            if ($eqPos >= $length) {
                break;
            }

            if ($paramString[$eqPos] === ';') {
                $i = $eqPos + 1;
                continue;
            }

            $key = strtolower(trim(substr($paramString, $i, $eqPos - $i)));
            $i = $eqPos + 1;

            while ($i < $length && strpos(" \t", $paramString[$i]) !== false) {
                $i++;
            }

            if ($i >= $length) {
                self::keep($params, $key, '');
                break;
            }

            if ($paramString[$i] === '"') {
                $i++;
                $value = '';
                $escaped = false;

                for (; $i < $length; $i++) {
                    $char = $paramString[$i];

                    if ($escaped) {
                        $value .= $char;
                        $escaped = false;
                        continue;
                    }

                    if ($char === '\\') {
                        $escaped = true;
                        continue;
                    }

                    if ($char === '"') {
                        $i++;
                        break;
                    }

                    $value .= $char;
                }

                self::keep($params, $key, $value);
            } else {
                $start = $i;

                while ($i < $length && strpos(" \t\r\n\x0B\x0C;", $paramString[$i]) === false) {
                    $i++;
                }

                if ($i === $start) {
                    break;
                }

                self::keep($params, $key, substr($paramString, $start, $i - $start));
            }
        }

        return $params;
    }
}
