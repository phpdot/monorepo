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

final class ContentDecoder
{
    public function decode(string $content, string $encoding, bool $strict = false): string
    {
        return match (strtolower(trim($encoding))) {
            'base64' => $this->decodeBase64($content),
            'quoted-printable' => $this->decodeQuotedPrintable($content),
            '7bit', '8bit', 'binary', '' => $content,
            default => $strict
                ? throw new InvalidMimeStructureException(
                    sprintf('Unknown Content-Transfer-Encoding: "%s"', $encoding),
                )
                : $content,
        };
    }

    private function decodeBase64(string $content): string
    {

        $cleaned = preg_replace('/[^A-Za-z0-9+\/=]/', '', $content) ?? $content;
        $result = base64_decode($cleaned, true);

        if ($result !== false) {
            return $result;
        }

        $decoded = '';
        foreach (explode('=', $cleaned) as $segment) {
            if ($segment === '') {
                continue;
            }
            $padded = str_pad($segment, strlen($segment) + (4 - strlen($segment) % 4) % 4, '=');
            $part = base64_decode($padded, true);
            $decoded .= $part === false ? '' : $part;
        }

        return $decoded;
    }

    private function decodeQuotedPrintable(string $content): string
    {
        return quoted_printable_decode($content);
    }
}
