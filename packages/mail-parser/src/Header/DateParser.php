<?php

declare(strict_types=1);

/**
 * Parses RFC 5322 date-time strings including obsolete formats, military timezone codes,
 * and two-digit years.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Parser\Header;

use DateTimeImmutable;
use Exception;

final class DateParser
{
    private const OBSOLETE_ZONES = [
        'UT' => '+0000', 'UTC' => '+0000', 'GMT' => '+0000',
        'EST' => '-0500', 'EDT' => '-0400',
        'CST' => '-0600', 'CDT' => '-0500',
        'MST' => '-0700', 'MDT' => '-0600',
        'PST' => '-0800', 'PDT' => '-0700',

        'A' => '+0100', 'B' => '+0200', 'C' => '+0300', 'D' => '+0400',
        'E' => '+0500', 'F' => '+0600', 'G' => '+0700', 'H' => '+0800',
        'I' => '+0900', 'K' => '+1000', 'L' => '+1100', 'M' => '+1200',
        'N' => '-0100', 'O' => '-0200', 'P' => '-0300', 'Q' => '-0400',
        'R' => '-0500', 'S' => '-0600', 'T' => '-0700', 'U' => '-0800',
        'V' => '-0900', 'W' => '-1000', 'X' => '-1100', 'Y' => '-1200',
        'Z' => '+0000',
    ];

    public function parse(string $value): DateTimeImmutable|null
    {
        $value = $this->cleanValue($value);

        if ($value === '') {
            return null;
        }

        $value = $this->expand2DigitYear($value);

        $value = $this->replaceObsoleteTimezones($value);

        $result = $this->tryStandardParse($value);
        if ($result !== null) {
            return $result;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Exception) {
            return null;
        }
    }

    private function cleanValue(string $value): string
    {

        $value = $this->stripComments($value);

        $value = preg_replace('/\s+/', ' ', trim($value)) ?? '';

        return $value;
    }

    private function tryStandardParse(string $value): DateTimeImmutable|null
    {

        $formats = [
            'D, d M Y H:i:s O',
            'D, d M Y H:i:s P',
            'd M Y H:i:s O',
            'd M Y H:i:s P',
            'D, d M Y H:i O',
            'd M Y H:i O',
            DateTimeImmutable::RFC2822,
        ];

        foreach ($formats as $format) {
            $result = DateTimeImmutable::createFromFormat($format, $value);
            if ($result !== false) {
                return $result;
            }
        }

        return null;
    }

    private function expand2DigitYear(string $value): string
    {

        return preg_replace_callback(
            '/(\d{1,2}\s+[A-Za-z]+\s+)(\d{2})(?=\s+\d{1,2}:)/',
            function (array $m) {

                if (strlen($m[2]) !== 2) {
                    return $m[0];
                }
                $year = (int) $m[2];
                $fullYear = $year < 50 ? 2000 + $year : 1900 + $year;
                return $m[1] . $fullYear;
            },
            $value,
        ) ?? $value;
    }

    private function replaceObsoleteTimezones(string $value): string
    {
        return preg_replace_callback(
            '/\b([A-Z]{1,3})\s*$/',
            function (array $m) {
                $zone = strtoupper($m[1]);
                return self::OBSOLETE_ZONES[$zone] ?? $m[0];
            },
            $value,
        ) ?? $value;
    }

    private function stripComments(string $value): string
    {
        $result = '';
        $depth = 0;
        $escaped = false;

        for ($i = 0, $len = strlen($value); $i < $len; $i++) {
            $char = $value[$i];

            if ($escaped) {
                if ($depth === 0) {
                    $result .= $char;
                }
                $escaped = false;
                continue;
            }

            if ($char === '\\') {
                $escaped = true;
                if ($depth === 0) {
                    $result .= $char;
                }
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
}
