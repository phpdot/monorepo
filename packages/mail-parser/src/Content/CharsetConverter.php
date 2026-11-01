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

use ValueError;


final class CharsetConverter
{
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
        'iso-8859-8-i' => 'ISO-8859-8',
        'x-gbk' => 'GBK',
        'gbk' => 'GBK',
        'gb2312' => 'GBK',
        'iso-8859-11' => 'ISO-8859-11',
        'windows-874' => 'ISO-8859-11',
        'iso-8859-1' => 'ISO-8859-1',
        'iso8859-1' => 'ISO-8859-1',
        'iso_8859-1' => 'ISO-8859-1',
        'iso_8859-1:1987' => 'ISO-8859-1',
        'latin1' => 'ISO-8859-1',
        'latin-1' => 'ISO-8859-1',
        'l1' => 'ISO-8859-1',
        'csisolatin1' => 'ISO-8859-1',
        'iso-ir-100' => 'ISO-8859-1',
        'ibm819' => 'ISO-8859-1',
        'cp819' => 'ISO-8859-1',

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

        'iso-8859-9' => 'ISO-8859-9',
        'iso8859-9' => 'ISO-8859-9',
        'iso_8859-9' => 'ISO-8859-9',
        'latin5' => 'ISO-8859-9',
        'l5' => 'ISO-8859-9',

        'iso-8859-10' => 'ISO-8859-10',
        'iso8859-10' => 'ISO-8859-10',
        'iso_8859-10' => 'ISO-8859-10',
        'latin6' => 'ISO-8859-10',

        'iso-8859-11' => 'ISO-8859-11',
        'iso8859-11' => 'ISO-8859-11',
        'tis-620' => 'TIS-620',
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

        'big5' => 'BIG-5',
        'big-5' => 'BIG-5',
        'csbig5' => 'BIG-5',
        'big5-hkscs' => 'BIG-5',
        'cn-big5' => 'BIG-5',

        'euc-kr' => 'EUC-KR',
        'euckr' => 'EUC-KR',
        'cseuckr' => 'EUC-KR',
        'ks_c_5601-1987' => 'EUC-KR',
        'ks_c_5601-1989' => 'EUC-KR',
        'ksc5601' => 'EUC-KR',
        'ksc_5601' => 'EUC-KR',
        'korean' => 'EUC-KR',

        'shift_jis' => 'SJIS',
        'shift-jis' => 'SJIS',
        'sjis' => 'SJIS',
        'x-sjis' => 'SJIS',
        'csshiftjis' => 'SJIS',
        'ms_kanji' => 'SJIS',
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

    public function toUtf8(string $content, string $charset): string
    {
        if ($content === '') {
            return '';
        }

        $canonical = $this->normalizeCharset($charset);

        if ($canonical === 'ASCII') {
            return $content;
        }

        if ($canonical === 'UTF-8') {
            if (mb_check_encoding($content, 'UTF-8')) {
                return $content;
            }

            return mb_convert_encoding($content, 'UTF-8', 'UTF-8');
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

        return $this->convertWithIconv($content, $canonical);
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

    private function convertWithMb(string $content, string $fromCharset): string
    {
        try {
            return mb_convert_encoding($content, 'UTF-8', $fromCharset);
        } catch (ValueError) {

            return $this->convertWithIconv($content, $fromCharset);
        }
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
