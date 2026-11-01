<?php

declare(strict_types=1);

/**
 * Decodes RFC 2047 encoded words in email headers, handling Base64/Quoted-Printable encoding,
 * charset conversion, and adjacent word concatenation.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Parser\Header;

use PHPdot\Mail\Parser\Content\CharsetConverter;

final class EncodedWordDecoder
{
    private const ENCODED_WORD_PATTERN = '/=\?([^?*]+)(?:\*([^?]*))?\?(B|Q)\?([^?]*)\?=/i';

    public function __construct(
        private readonly CharsetConverter $charsetConverter,
    ) {}

    public function decode(string $value): string
    {
        if (!str_contains($value, '=?')) {
            return $value;
        }

        $value = preg_replace(
            '/(=\?[^?]+(?:\*[^?]*)?\?[BbQq]\?[^?]*\?=)\s+(=\?[^?]+(?:\*[^?]*)?\?[BbQq]\?[^?]*\?=)/',
            '$1$2',
            $value,
        ) ?? $value;

        if (preg_match('/(=\?[^?]+(?:\*[^?]*)?\?[BbQq]\?[^?]*\?=)(=\?[^?]+(?:\*[^?]*)?\?[BbQq]\?[^?]*\?=)/', $value) === 1) {
            $value = $this->decodeAdjacentWords($value);
        }

        return preg_replace_callback(
            self::ENCODED_WORD_PATTERN,
            fn(array $m) => $this->decodeSingleWord($m),
            $value,
        ) ?? $value;
    }

    private function decodeAdjacentWords(string $value): string
    {
        $pattern = '/((?:=\?([^?*]+)(?:\*[^?]*)?\?([BbQq])\?[^?]*\?=)+)/';

        return preg_replace_callback($pattern, function (array $match): string {
            $run = $match[0];

            preg_match_all(self::ENCODED_WORD_PATTERN, $run, $words, PREG_SET_ORDER);

            if ($words === [] || count($words) === 1) {
                return $this->decodeSingleWord($words[0] ?? [$run, 'utf-8', null, 'B', '']);
            }

            $groups = [];
            $currentCharset = null;
            $currentBytes = '';

            foreach ($words as $word) {
                $charset = strtolower($word[1]);
                $encoding = strtoupper($word[3]);
                $rawBytes = $this->decodeBytes($word[4], $encoding);

                if ($charset === $currentCharset) {
                    $currentBytes .= $rawBytes;
                } else {
                    if ($currentCharset !== null) {
                        $groups[] = ['charset' => $currentCharset, 'bytes' => $currentBytes];
                    }
                    $currentCharset = $charset;
                    $currentBytes = $rawBytes;
                }
            }

            $groups[] = ['charset' => $currentCharset, 'bytes' => $currentBytes];

            $result = '';
            foreach ($groups as $group) {
                $result .= $this->charsetConverter->toUtf8($group['bytes'], $group['charset']);
            }

            return $result;
        }, $value) ?? $value;
    }

    /**
     * @param array<int, null|string> $match
     */
    private function decodeSingleWord(array $match): string
    {
        $encoding = strtoupper((string) $match[3]);
        $rawBytes = $this->decodeBytes((string) $match[4], $encoding);

        return $this->charsetConverter->toUtf8($rawBytes, (string) $match[1]);
    }

    private function decodeBytes(string $text, string $encoding): string
    {
        if ($encoding !== 'B') {
            return $this->decodeQEncoding($text);
        }

        $decoded = base64_decode($text, true);

        return $decoded === false ? '' : $decoded;
    }

    private function decodeQEncoding(string $text): string
    {
        $text = str_replace('_', ' ', $text);

        return preg_replace_callback(
            '/=([0-9A-Fa-f]{2})/',
            fn(array $m) => chr((int) hexdec($m[1]) & 0xFF),
            $text,
        ) ?? $text;
    }
}
