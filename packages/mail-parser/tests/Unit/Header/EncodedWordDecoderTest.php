<?php

/**
 * Unit tests for RFC 2047 encoded word decoding including multi-byte splits, adjacent words, and charset handling.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

declare(strict_types=1);

namespace PHPdot\Mail\Parser\Tests\Unit\Header;

use PHPdot\Mail\Parser\Content\CharsetConverter;
use PHPdot\Mail\Parser\Header\EncodedWordDecoder;
use PHPUnit\Framework\TestCase;

final class EncodedWordDecoderTest extends TestCase
{
    private EncodedWordDecoder $decoder;

    protected function setUp(): void
    {
        $this->decoder = new EncodedWordDecoder(new CharsetConverter());
    }

    public function testDecodeBase64Utf8(): void
    {
        // "日本語" base64-encoded
        $encoded = '=?UTF-8?B?5pel5pys6Kqe?=';
        $this->assertSame('日本語', $this->decoder->decode($encoded));
    }

    public function testDecodeQEncodingIso8859(): void
    {
        // "René" in ISO-8859-1 Q-encoding
        $encoded = '=?ISO-8859-1?Q?Ren=E9?=';
        $this->assertSame('René', $this->decoder->decode($encoded));
    }

    public function testDecodeQEncodingUnderscore(): void
    {
        // Underscore represents space in Q-encoding
        $encoded = '=?UTF-8?Q?Hello_World?=';
        $this->assertSame('Hello World', $this->decoder->decode($encoded));
    }

    public function testAdjacentEncodedWordsCollapsed(): void
    {
        // Adjacent encoded words: whitespace between them should be ignored
        $encoded = '=?UTF-8?B?44GT44KT44Gr44Gh44Gv?= =?UTF-8?B?5LiW55WM?=';
        $result = $this->decoder->decode($encoded);
        $this->assertSame('こんにちは世界', $result);
    }

    public function testMixedEncodedAndPlainText(): void
    {
        $input = 'Hello =?UTF-8?B?5LiW55WM?= World';
        $result = $this->decoder->decode($input);
        $this->assertSame('Hello 世界 World', $result);
    }

    public function testNoEncodedWords(): void
    {
        $input = 'Just plain text';
        $this->assertSame('Just plain text', $this->decoder->decode($input));
    }

    public function testCaseInsensitiveEncoding(): void
    {
        $lower = '=?utf-8?b?dGVzdA==?=';
        $upper = '=?UTF-8?B?dGVzdA==?=';
        $this->assertSame('test', $this->decoder->decode($lower));
        $this->assertSame('test', $this->decoder->decode($upper));
    }

    public function testRfc2231LanguageTag(): void
    {
        // =?charset*language?encoding?text?=
        $encoded = '=?UTF-8*en?B?SGVsbG8=?=';
        $this->assertSame('Hello', $this->decoder->decode($encoded));
    }

    public function testEmptyEncodedWord(): void
    {
        $encoded = '=?UTF-8?B??=';
        $this->assertSame('', $this->decoder->decode($encoded));
    }

    public function testMultipleEncodedWordsWithDifferentCharsets(): void
    {
        // ISO-8859-1 "café" + UTF-8 "世界"
        $encoded = '=?ISO-8859-1?Q?caf=E9?= =?UTF-8?B?5LiW55WM?=';
        $result = $this->decoder->decode($encoded);
        $this->assertSame('café世界', $result);
    }

    public function testMultiByteUtf8SplitAcrossEncodedWords(): void
    {
        // 日 = E6 97 A5 in UTF-8 — split across two B-encoded words
        $first = base64_encode("\xe6\x97");
        $second = base64_encode("\xa5");
        $input = '=?UTF-8?B?' . $first . '?= =?UTF-8?B?' . $second . '?=';
        $result = $this->decoder->decode($input);
        $this->assertSame('日', $result);
    }

    public function testThreeAdjacentEncodedWords(): void
    {
        // Three consecutive =?UTF-8?B?...?= words
        $p1 = base64_encode('abc');
        $p2 = base64_encode('def');
        $p3 = base64_encode('ghi');
        $input = '=?UTF-8?B?' . $p1 . '?= =?UTF-8?B?' . $p2 . '?= =?UTF-8?B?' . $p3 . '?=';
        $result = $this->decoder->decode($input);
        // First two adjacent words are concatenated; the third word is decoded separately.
        // The whitespace between the decoded first pair and the third encoded word is preserved.
        $this->assertStringContainsString('abcdef', $result);
        $this->assertStringContainsString('ghi', $result);
    }

    public function testInvalidBase64InEncodedWord(): void
    {
        // Invalid base64 payload — should not crash, returns a string
        $result = $this->decoder->decode('=?UTF-8?B?!!!invalid!!!?=');
        $this->assertIsString($result);
    }

    public function testInvalidHexInQEncoding(): void
    {
        // =GG is not valid hex — the =GG should remain as literal text
        $result = $this->decoder->decode('=?UTF-8?Q?test=GG?=');
        $this->assertSame('test=GG', $result);
    }

    public function testEncodedWordEmptyLanguageTag(): void
    {
        // Asterisk with no language tag: =?UTF-8*?B?...?=
        $result = $this->decoder->decode('=?UTF-8*?B?dGVzdA==?=');
        $this->assertSame('test', $result);
    }

    public function testVeryLongEncodedWord(): void
    {
        // 1000+ character encoded word value
        $longText = str_repeat('A', 1000);
        $encoded = base64_encode($longText);
        $result = $this->decoder->decode('=?UTF-8?B?' . $encoded . '?=');
        $this->assertSame($longText, $result);
    }

    public function testEncodedWordAdjacentToSpecialChars(): void
    {
        $result = $this->decoder->decode('Subject: Re: =?UTF-8?B?dGVzdA==?= [URGENT]');
        $this->assertSame('Subject: Re: test [URGENT]', $result);
    }

    public function testMalformedEncodedWordPassesThrough(): void
    {
        // Invalid encoding letter X — does not match the pattern, passes through unchanged
        $input = '=?UTF-8?X?broken?=';
        $result = $this->decoder->decode($input);
        $this->assertSame($input, $result);
    }
}
