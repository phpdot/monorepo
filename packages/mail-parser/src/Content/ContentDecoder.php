<?php

declare(strict_types=1);

/**
 * Decodes email body content from Base64, Quoted-Printable, and other transfer encodings
 * defined in RFC 2045.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Parser\Content;

use PHPdot\Mail\Parser\Exception\InvalidMimeStructureException;
use PHPdot\Mail\Parser\Source\StringSource;

final class ContentDecoder
{
    public function decode(string $content, string $encoding, bool $strict = false): string
    {
        return match (strtolower(trim($encoding))) {
            'base64', 'quoted-printable' => self::decoded($content, $encoding),
            '7bit', '8bit', 'binary', '' => $content,
            default => $strict
                ? throw new InvalidMimeStructureException(
                    sprintf('Unknown Content-Transfer-Encoding: "%s"', $encoding),
                )
                : $content,
        };
    }

    private static function decoded(string $content, string $encoding): string
    {
        $decoded = '';
        TransferDecoder::decode(new StringSource($content, false), 0, strlen($content), $encoding, static function (string $bytes) use (&$decoded): void {
            $decoded .= $bytes;
        });

        return $decoded;
    }
}
