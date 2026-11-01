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
     * @return array<string, string>
     */
    private function parseParams(string $paramString): array
    {
        $params = [];
        $length = strlen($paramString);
        $i = 0;

        while (true) {
            while ($i < $length && strpos(" \t\r\n;", $paramString[$i]) !== false) {
                $i++;
            }

            if ($i >= $length) {
                break;
            }

            $eqPos = strpos($paramString, '=', $i);
            if ($eqPos === false) {
                break;
            }

            $key = strtolower(trim(substr($paramString, $i, $eqPos - $i)));
            $i = $eqPos + 1;

            while ($i < $length && strpos(" \t", $paramString[$i]) !== false) {
                $i++;
            }

            if ($i >= $length) {
                $params[$key] = '';
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

                $params[$key] = $value;
            } else {
                $start = $i;

                while ($i < $length && strpos(" \t\r\n\x0B\x0C;", $paramString[$i]) === false) {
                    $i++;
                }

                if ($i === $start) {
                    break;
                }

                $params[$key] = substr($paramString, $start, $i - $start);
            }
        }

        return $params;
    }
}
