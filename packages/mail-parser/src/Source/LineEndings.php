<?php

declare(strict_types=1);

/**
 * Makes a bare LF a CRLF so the parser reads one line ending; a bare CR stays data, as SMTP forbids it as a break.
 *
 * @internal
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Parser\Source;

final class LineEndings
{
    public static function canonical(string $bytes): string
    {
        if (preg_match('/(?<!\r)\n/', $bytes) !== 1) {
            return $bytes;
        }

        return preg_replace('/(?<!\r)\n/', "\r\n", $bytes) ?? $bytes;
    }
}
