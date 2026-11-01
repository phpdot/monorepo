<?php

/**
 * Unit tests for character set conversion covering CJK, Cyrillic, Windows codepages, ISO charsets, and fallback behavior.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

declare(strict_types=1);

namespace PHPdot\Mail\Parser\Tests\Unit\Content;

use PHPdot\Mail\Parser\Content\CharsetConverter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CharsetConverterTest extends TestCase
{
    private CharsetConverter $converter;

    protected function setUp(): void
    {
        $this->converter = new CharsetConverter();
    }

    public function testUtf8PassThrough(): void
    {
        $this->assertSame('Hello World', $this->converter->toUtf8('Hello World', 'UTF-8'));
    }

    public function testUtf8WithBom(): void
    {
        $result = $this->converter->toUtf8('Hello', 'utf-8');
        $this->assertSame('Hello', $result);
    }

    public function testAsciiPassThrough(): void
    {
        $this->assertSame('Hello', $this->converter->toUtf8('Hello', 'us-ascii'));
        $this->assertSame('Hello', $this->converter->toUtf8('Hello', 'ASCII'));
    }

    public function testIso88591ToUtf8(): void
    {
        // "café" in ISO-8859-1
        $iso = "caf\xe9";
        $result = $this->converter->toUtf8($iso, 'ISO-8859-1');
        $this->assertSame('café', $result);
    }

    public function testWindows1252ToUtf8(): void
    {
        // Smart quotes in Windows-1252: \x93 = left double, \x94 = right double
        $win = "\x93Hello\x94";
        $result = $this->converter->toUtf8($win, 'Windows-1252');
        $this->assertSame("\u{201C}Hello\u{201D}", $result);
    }

    #[DataProvider('charsetAliasProvider')]
    public function testCharsetAliases(string $alias, string $expected): void
    {
        $canonical = $this->converter->normalizeCharset($alias);
        $this->assertSame($expected, $canonical);
    }

    public static function charsetAliasProvider(): array
    {
        return [
            ['latin1', 'ISO-8859-1'],
            ['latin-1', 'ISO-8859-1'],
            ['iso-8859-1', 'ISO-8859-1'],
            ['cp1252', 'Windows-1252'],
            ['x-cp1252', 'Windows-1252'],
            ['gb2312', 'GB18030'],
            ['gbk', 'GB18030'],
            ['shift_jis', 'SJIS'],
            ['euc-kr', 'EUC-KR'],
            ['ks_c_5601-1987', 'EUC-KR'],
            ['koi8-r', 'KOI8-R'],
            ['utf-8', 'UTF-8'],
            ['utf8', 'UTF-8'],
        ];
    }

    public function testUnknownCharsetFallsBackToUtf8(): void
    {
        // Valid UTF-8 with unknown charset should pass through
        $result = $this->converter->toUtf8('Hello', 'x-unknown');
        $this->assertSame('Hello', $result);
    }

    public function testEmptyCharsetTreatedAsUnknown(): void
    {
        $result = $this->converter->toUtf8('Hello', '');
        $this->assertSame('Hello', $result);
    }

    public function testEmptyStringReturnsEmpty(): void
    {
        $this->assertSame('', $this->converter->toUtf8('', 'UTF-8'));
    }

    public function testIso2022JpToUtf8(): void
    {
        // "日本語" in ISO-2022-JP
        $iso2022 = mb_convert_encoding('日本語', 'ISO-2022-JP', 'UTF-8');
        $result = $this->converter->toUtf8($iso2022, 'ISO-2022-JP');
        $this->assertSame('日本語', $result);
    }

    public function testBig5ToUtf8(): void
    {
        $big5 = mb_convert_encoding('中文', 'BIG-5', 'UTF-8');
        $result = $this->converter->toUtf8($big5, 'big5');
        $this->assertSame('中文', $result);
    }

    #[DataProvider('cjkCharsetProvider')]
    public function testCjkCharsetConversions(string $charset, string $utf8Expected, string $rawBytes): void
    {
        $result = $this->converter->toUtf8($rawBytes, $charset);
        $this->assertSame($utf8Expected, $result);
    }

    public static function cjkCharsetProvider(): array
    {
        return [
            'GB18030 中文' => ['GB18030', '中文', "\xd6\xd0\xce\xc4"],
            'EUC-KR 한국어' => ['EUC-KR', '한국어', "\xc7\xd1\xb1\xb9\xbe\xee"],
            'SJIS 日本語' => ['SJIS', '日本語', "\x93\xfa\x96\x7b\x8c\xea"],
            'EUC-JP 日本語' => ['EUC-JP', '日本語', "\xc6\xfc\xcb\xdc\xb8\xec"],
        ];
    }

    #[DataProvider('cyrillicCharsetProvider')]
    public function testCyrillicCharsets(string $charset, string $utf8Expected, string $rawBytes): void
    {
        $result = $this->converter->toUtf8($rawBytes, $charset);
        $this->assertSame($utf8Expected, $result);
    }

    public static function cyrillicCharsetProvider(): array
    {
        return [
            'KOI8-R Привет' => ['KOI8-R', 'Привет', "\xf0\xd2\xc9\xd7\xc5\xd4"],
            'KOI8-U Привіт' => ['KOI8-U', 'Привіт', "\xf0\xd2\xc9\xd7\xa6\xd4"],
            'Windows-1251 Привет' => ['Windows-1251', 'Привет', "\xcf\xf0\xe8\xe2\xe5\xf2"],
            'ISO-8859-5 Привет' => ['ISO-8859-5', 'Привет', "\xbf\xe0\xd8\xd2\xd5\xe2"],
        ];
    }

    #[DataProvider('windowsCodepageProvider')]
    public function testWindowsCodepages(string $charset, string $utf8Expected, string $rawBytes): void
    {
        $result = $this->converter->toUtf8($rawBytes, $charset);
        $this->assertSame($utf8Expected, $result);
    }

    public static function windowsCodepageProvider(): array
    {
        return [
            'Windows-1250 ě' => ['Windows-1250', 'ě', "\xec"],
            'Windows-1253 Ω' => ['Windows-1253', 'Ω', "\xd9"],
            'Windows-1256 ع' => ['Windows-1256', 'ع', "\xda"],
            'Windows-1257 ą' => ['Windows-1257', 'ą', "\xe0"],
        ];
    }

    #[DataProvider('isoCharsetProvider')]
    public function testIsoCharsets(string $charset, string $utf8Expected, string $rawBytes): void
    {
        $result = $this->converter->toUtf8($rawBytes, $charset);
        $this->assertSame($utf8Expected, $result);
    }

    public static function isoCharsetProvider(): array
    {
        return [
            'ISO-8859-2 ě' => ['ISO-8859-2', 'ě', "\xec"],
            'ISO-8859-7 Ω' => ['ISO-8859-7', 'Ω', "\xd9"],
            'ISO-8859-9 ş' => ['ISO-8859-9', 'ş', "\xfe"],
            'ISO-8859-15 €' => ['ISO-8859-15', '€', "\xa4"],
        ];
    }

    public function testUtf16BeToUtf8(): void
    {
        $utf16be = "\x00\x48\x00\x65\x00\x6c\x00\x6c\x00\x6f\x00\x20\x65\xe5\x67\x2c";
        $result = $this->converter->toUtf8($utf16be, 'UTF-16BE');
        $this->assertSame('Hello 日本', $result);
    }

    public function testUtf16LeToUtf8(): void
    {
        $utf16le = "\x48\x00\x65\x00\x6c\x00\x6c\x00\x6f\x00\x20\x00\xe5\x65\x2c\x67";
        $result = $this->converter->toUtf8($utf16le, 'UTF-16LE');
        $this->assertSame('Hello 日本', $result);
    }

    public function testInvalidUtf8Sanitization(): void
    {
        // \xC3\x28 is an invalid UTF-8 sequence — should not crash
        $result = $this->converter->toUtf8("\xC3\x28", 'UTF-8');
        $this->assertIsString($result);
        // The ( character (0x28) should survive in some form
        $this->assertStringContainsString('(', $result);
    }

    public function testUnknownCharsetWithBinaryFallsBackToWindows1252(): void
    {
        // \x93 = left double quote, \x94 = right double quote in Windows-1252
        $result = $this->converter->toUtf8("\x93\x94", 'x-unknown');
        // These bytes are NOT valid UTF-8, so the fallback to Windows-1252 kicks in
        $this->assertSame("\u{201C}\u{201D}", $result);
    }
}
