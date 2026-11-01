<?php

declare(strict_types=1);

/**
 * Converts email content between character sets using a two-tier strategy with mbstring and iconv,
 * supporting 60+ charset aliases.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Parser\Content;

use Closure;
use ValueError;

final class CharsetConverter
{
    /** @var array<string, bool> */
    private array $convertible = [];

    private const CHARSET_ALIASES = [

        'us-ascii' => 'ASCII',
        'ascii' => 'ASCII',
        'ansi_x3.4-1968' => 'ASCII',
        'ansi_x3.4-1986' => 'ASCII',
        'iso-ir-6' => 'ASCII',
        'iso_646.irv:1991' => 'ASCII',
        'iso646-us' => 'ASCII',
        'csascii' => 'ASCII',

        'utf-8' => 'UTF-8',
        'utf8' => 'UTF-8',
        'csutf8' => 'UTF-8',

        'utf-16' => 'UTF-16',
        'utf16' => 'UTF-16',
        'utf-16be' => 'UTF-16BE',
        'utf-16le' => 'UTF-16LE',

        'utf-32' => 'UTF-32',
        'utf-32be' => 'UTF-32BE',
        'utf-32le' => 'UTF-32LE',

        'iso-8859-8-i' => 'ISO-8859-8',
        'x-gbk' => 'GB18030',
        'windows-874' => 'CP874',
        'iso-8859-1' => 'Windows-1252',
        'iso8859-1' => 'Windows-1252',
        'iso_8859-1' => 'Windows-1252',
        'iso_8859-1:1987' => 'Windows-1252',
        'latin1' => 'Windows-1252',
        'latin-1' => 'Windows-1252',
        'l1' => 'Windows-1252',
        'csisolatin1' => 'Windows-1252',
        'iso-ir-100' => 'Windows-1252',
        'ibm819' => 'Windows-1252',
        'cp819' => 'Windows-1252',

        'iso-8859-2' => 'ISO-8859-2',
        'iso8859-2' => 'ISO-8859-2',
        'iso_8859-2' => 'ISO-8859-2',
        'latin2' => 'ISO-8859-2',
        'l2' => 'ISO-8859-2',
        'csisolatin2' => 'ISO-8859-2',

        'iso-8859-3' => 'ISO-8859-3',
        'iso8859-3' => 'ISO-8859-3',
        'iso_8859-3' => 'ISO-8859-3',
        'latin3' => 'ISO-8859-3',
        'l3' => 'ISO-8859-3',

        'iso-8859-4' => 'ISO-8859-4',
        'iso8859-4' => 'ISO-8859-4',
        'iso_8859-4' => 'ISO-8859-4',
        'latin4' => 'ISO-8859-4',
        'l4' => 'ISO-8859-4',

        'iso-8859-5' => 'ISO-8859-5',
        'iso8859-5' => 'ISO-8859-5',
        'iso_8859-5' => 'ISO-8859-5',
        'cyrillic' => 'ISO-8859-5',

        'iso-8859-6' => 'ISO-8859-6',
        'iso8859-6' => 'ISO-8859-6',
        'iso_8859-6' => 'ISO-8859-6',
        'arabic' => 'ISO-8859-6',

        'iso-8859-7' => 'ISO-8859-7',
        'iso8859-7' => 'ISO-8859-7',
        'iso_8859-7' => 'ISO-8859-7',
        'greek' => 'ISO-8859-7',

        'iso-8859-8' => 'ISO-8859-8',
        'iso8859-8' => 'ISO-8859-8',
        'iso_8859-8' => 'ISO-8859-8',
        'hebrew' => 'ISO-8859-8',

        'iso-8859-9' => 'Windows-1254',
        'iso8859-9' => 'Windows-1254',
        'iso_8859-9' => 'Windows-1254',
        'latin5' => 'Windows-1254',
        'l5' => 'Windows-1254',

        'iso-8859-10' => 'ISO-8859-10',
        'iso8859-10' => 'ISO-8859-10',
        'iso_8859-10' => 'ISO-8859-10',
        'latin6' => 'ISO-8859-10',

        'iso-8859-11' => 'CP874',
        'iso8859-11' => 'CP874',
        'tis-620' => 'CP874',
        'thai' => 'TIS-620',

        'iso-8859-13' => 'ISO-8859-13',
        'iso8859-13' => 'ISO-8859-13',
        'iso_8859-13' => 'ISO-8859-13',
        'latin7' => 'ISO-8859-13',

        'iso-8859-14' => 'ISO-8859-14',
        'iso8859-14' => 'ISO-8859-14',
        'iso_8859-14' => 'ISO-8859-14',
        'latin8' => 'ISO-8859-14',

        'iso-8859-15' => 'ISO-8859-15',
        'iso8859-15' => 'ISO-8859-15',
        'iso_8859-15' => 'ISO-8859-15',
        'latin9' => 'ISO-8859-15',
        'latin-9' => 'ISO-8859-15',

        'iso-8859-16' => 'ISO-8859-16',
        'iso8859-16' => 'ISO-8859-16',
        'iso_8859-16' => 'ISO-8859-16',
        'latin10' => 'ISO-8859-16',

        'windows-1250' => 'Windows-1250',
        'cp1250' => 'Windows-1250',
        'x-cp1250' => 'Windows-1250',

        'windows-1251' => 'Windows-1251',
        'cp1251' => 'Windows-1251',
        'x-cp1251' => 'Windows-1251',

        'windows-1252' => 'Windows-1252',
        'cp1252' => 'Windows-1252',
        'x-cp1252' => 'Windows-1252',

        'windows-1253' => 'Windows-1253',
        'cp1253' => 'Windows-1253',

        'windows-1254' => 'Windows-1254',
        'cp1254' => 'Windows-1254',

        'windows-1255' => 'Windows-1255',
        'cp1255' => 'Windows-1255',

        'windows-1256' => 'Windows-1256',
        'cp1256' => 'Windows-1256',

        'windows-1257' => 'Windows-1257',
        'cp1257' => 'Windows-1257',

        'windows-1258' => 'Windows-1258',
        'cp1258' => 'Windows-1258',

        'gb2312' => 'GB18030',
        'gb_2312-80' => 'GB18030',
        'gbk' => 'GB18030',
        'gb18030' => 'GB18030',
        'csgb2312' => 'GB18030',
        'csiso58gb231280' => 'GB18030',
        'chinese' => 'GB18030',

        'big5' => 'BIG5-HKSCS',
        'big-5' => 'BIG5-HKSCS',
        'csbig5' => 'BIG5-HKSCS',
        'big5-hkscs' => 'BIG5-HKSCS',
        'cn-big5' => 'BIG5-HKSCS',

        'euc-kr' => 'UHC',
        'euckr' => 'EUC-KR',
        'cseuckr' => 'EUC-KR',
        'ks_c_5601-1987' => 'UHC',
        'ks_c_5601-1989' => 'UHC',
        'ksc5601' => 'UHC',
        'ksc_5601' => 'UHC',
        'korean' => 'UHC',

        'shift_jis' => 'SJIS-win',
        'shift-jis' => 'SJIS-win',
        'sjis' => 'SJIS-win',
        'x-sjis' => 'SJIS-win',
        'csshiftjis' => 'SJIS-win',
        'ms_kanji' => 'SJIS-win',
        'ms932' => 'SJIS-win',
        'cp932' => 'SJIS-win',
        'windows-31j' => 'SJIS-win',

        'iso-2022-jp' => 'ISO-2022-JP',
        'csiso2022jp' => 'ISO-2022-JP',
        'iso-2022-jp-2' => 'ISO-2022-JP',

        'euc-jp' => 'EUC-JP',
        'eucjp' => 'EUC-JP',
        'cseucpkdfmtjapanese' => 'EUC-JP',
        'x-euc-jp' => 'EUC-JP',

        'koi8-r' => 'KOI8-R',
        'koi8r' => 'KOI8-R',
        'cskoi8r' => 'KOI8-R',

        'koi8-u' => 'KOI8-U',
        'koi8u' => 'KOI8-U',

        'macintosh' => 'macintosh',
        'mac-roman' => 'macintosh',
        'macroman' => 'macintosh',
        'x-mac-roman' => 'macintosh',

        'ibm866' => 'CP866',
        'cp866' => 'CP866',
        'csibm866' => 'CP866',
        '866' => 'CP866',

        'ibm850' => 'CP850',
        'cp850' => 'CP850',
        '850' => 'CP850',

        'x-unknown' => 'x-unknown',
        'unknown' => 'x-unknown',
        'default' => 'x-unknown',
        'dos-874' => 'CP874',
        'cp874' => 'CP874',
        'windows-949' => 'UHC',
        'cp949' => 'UHC',
        'uhc' => 'UHC',
        'windows-936' => 'GB18030',
        'ms936' => 'GB18030',
        'cp936' => 'GB18030',
    ];

    private const MB_CHARSETS = [
        'ASCII', 'UTF-8', 'UTF-16', 'UTF-16BE', 'UTF-16LE', 'UTF-32', 'UTF-32BE', 'UTF-32LE',
        'ISO-8859-1', 'ISO-8859-2', 'ISO-8859-3', 'ISO-8859-4', 'ISO-8859-5',
        'ISO-8859-6', 'ISO-8859-7', 'ISO-8859-8', 'ISO-8859-9', 'ISO-8859-10',
        'ISO-8859-13', 'ISO-8859-14', 'ISO-8859-15', 'ISO-8859-16',
        'Windows-1251', 'Windows-1252', 'Windows-1254',
        'GB18030', 'BIG-5', 'EUC-KR',
        'SJIS', 'SJIS-win', 'ISO-2022-JP', 'EUC-JP',
        'KOI8-R', 'KOI8-U',
        'CP866', 'CP850',
    ];

    /**
     * Decodes content in the charset it names to valid UTF-8; bytes beyond ASCII under an ASCII or missing label are
     * kept when they are UTF-8 and read as Windows-1252 when not, as browsers read them.
     */
    public function toUtf8(string $content, string $charset): string
    {
        $converted = $this->convert($content, $charset);

        return mb_check_encoding($converted, 'UTF-8') ? $converted : self::replacing(static fn(): string => mb_scrub($converted, 'UTF-8'));
    }

    private function convert(string $content, string $charset): string
    {
        if ($content === '') {
            return '';
        }

        $canonical = $this->normalizeCharset($charset);

        if ($canonical === 'ASCII') {
            return mb_check_encoding($content, 'UTF-8') ? $content : $this->convertWithMb($content, 'Windows-1252');
        }

        if ($canonical === 'UTF-8') {
            if (mb_check_encoding($content, 'UTF-8')) {
                return $content;
            }

            return self::replacing(static fn(): string => mb_convert_encoding($content, 'UTF-8', 'UTF-8'));
        }

        if ($canonical === 'x-unknown') {
            if (mb_check_encoding($content, 'UTF-8')) {
                return $content;
            }
            return $this->convertWithMb($content, 'Windows-1252');
        }

        if (in_array($canonical, self::MB_CHARSETS, true)) {
            return $this->convertWithMb($content, $canonical);
        }

        if (!array_key_exists($canonical, $this->convertible)) {
            $probe = @iconv($canonical, 'UTF-8', '');
            $this->convertible[$canonical] = $probe !== false;
        }

        return $this->convertible[$canonical]
            ? $this->convertWithIconv($content, $canonical)
            : $this->convertWithMb($content, 'Windows-1252');
    }

    public function normalizeCharset(string $charset): string
    {
        $lower = strtolower(trim($charset));

        if ($lower === '' || $lower === 'x-unknown' || $lower === 'unknown' || $lower === 'default') {
            return 'x-unknown';
        }

        return self::CHARSET_ALIASES[$lower] ?? $this->guessCanonical($lower);
    }

    private function guessCanonical(string $lower): string
    {
        foreach (self::MB_CHARSETS as $mbCharset) {
            if (strtolower($mbCharset) === $lower) {
                return $mbCharset;
            }
        }

        return $lower;
    }

    /**
     * Runs a conversion that writes U+FFFD for each byte it cannot read, as the WHATWG decoders do, not mbstring's `?`.
     *
     * @template T
     *
     * @param Closure(): T $convert
     *
     * @return T
     */
    private static function replacing(Closure $convert): mixed
    {
        $previous = mb_substitute_character();
        mb_substitute_character(0xFFFD);

        try {
            return $convert();
        } finally {
            mb_substitute_character($previous);
        }
    }

    private function convertWithMb(string $content, string $fromCharset): string
    {
        try {
            $converted = self::replacing(static fn(): string|false => mb_convert_encoding($content, 'UTF-8', $fromCharset));
        } catch (ValueError) {
            return $this->convertWithIconv($content, $fromCharset);
        }

        return $converted === false ? $this->convertWithIconv($content, $fromCharset) : $converted;
    }

    private function convertWithIconv(string $content, string $fromCharset): string
    {
        $result = @iconv($fromCharset, 'UTF-8//IGNORE', $content);

        if ($result === false) {
            if (mb_check_encoding($content, 'UTF-8')) {
                return $content;
            }

            return $this->convertWithMb($content, 'Windows-1252');
        }

        return $result;
    }
}
