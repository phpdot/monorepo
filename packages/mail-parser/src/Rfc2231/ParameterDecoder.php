<?php

declare(strict_types=1);

/**
 * Decodes RFC 2231 extended MIME parameters with charset encoding, language tags,
 * and multi-section continuations.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Parser\Rfc2231;

use PHPdot\Mail\Parser\Content\CharsetConverter;

final class ParameterDecoder
{
    public function __construct(
        private readonly CharsetConverter $charsetConverter,
    ) {}

    /**
     * Decode RFC 2231 extended/continued parameters.
     *
     * Handles:
     * - Simple: filename="report.pdf"
     * - Extended: filename*=utf-8''report%20%C3%A9.pdf
     * - Continued: filename*0="report"; filename*1=".pdf"
     * - Extended + continued: filename*0*=utf-8''report%20; filename*1*=%C3%A9.pdf
     *
     * @param array<string, string> $params
     *
     * @return array<string, string>
     */
    public function decode(array $params): array
    {
        $regular = [];
        $extended = [];

        foreach ($params as $key => $value) {
            $parsed = $this->parseParameterKey($key);

            if ($parsed === null) {

                $regular[$key] = $value;
                continue;
            }

            [$baseName, $section, $isEncoded] = $parsed;

            $extended[$baseName][$section] = [
                'value' => $value,
                'encoded' => $isEncoded,
            ];
        }

        foreach ($extended as $baseName => $sections) {

            ksort($sections, SORT_NUMERIC);

            $charset = null;
            $concatenated = '';

            foreach ($sections as $sectionNum => $info) {
                $value = $info['value'];

                if ($info['encoded']) {
                    if ($sectionNum === 0 || $charset === null) {

                        $charsetParsed = $this->parseCharsetValue($value);
                        if ($charsetParsed !== null) {
                            $charset = $charsetParsed['charset'];
                            $concatenated .= $this->percentDecode($charsetParsed['value']);
                        } else {
                            $concatenated .= $this->percentDecode($value);
                        }
                    } else {
                        $concatenated .= $this->percentDecode($value);
                    }
                } else {
                    $concatenated .= $value;
                }
            }

            if ($charset !== null && $charset !== '') {
                $regular[$baseName] = $this->charsetConverter->toUtf8($concatenated, $charset);
            } else {
                $regular[$baseName] = $concatenated;
            }
        }

        return $regular;
    }

    /**
     * Parse a parameter key to detect RFC 2231 extensions.
     *
     * Returns [baseName, sectionNumber, isEncoded] or null for regular params.
     *
     * Examples:
     *   "filename" -> null (regular)
     *   "filename*" -> ["filename", 0, true] (extended, no continuation)
     *   "filename*0" -> ["filename", 0, false] (continuation, not encoded)
     *   "filename*0*" -> ["filename", 0, true] (continuation, encoded)
     *
     * @return array{string, int, bool}|null
     */
    private function parseParameterKey(string $key): array|null
    {

        if (preg_match('/^([^*]+)\*$/', $key, $m) === 1) {
            return [$m[1], 0, true];
        }

        if (preg_match('/^([^*]+)\*(\d+)\*$/', $key, $m) === 1) {
            return [$m[1], (int) $m[2], true];
        }

        if (preg_match('/^([^*]+)\*(\d+)$/', $key, $m) === 1) {
            return [$m[1], (int) $m[2], false];
        }

        return null;
    }

    /**
     * Parse charset'language'value format.
     *
     * @return array{charset: string, language: string, value: string}|null
     */
    private function parseCharsetValue(string $value): array|null
    {
        $firstQuote = strpos($value, "'");
        if ($firstQuote === false) {
            return null;
        }

        $secondQuote = strpos($value, "'", $firstQuote + 1);
        if ($secondQuote === false) {
            return null;
        }

        return [
            'charset' => substr($value, 0, $firstQuote),
            'language' => substr($value, $firstQuote + 1, $secondQuote - $firstQuote - 1),
            'value' => substr($value, $secondQuote + 1),
        ];
    }

    private function percentDecode(string $value): string
    {
        return rawurldecode($value);
    }
}
