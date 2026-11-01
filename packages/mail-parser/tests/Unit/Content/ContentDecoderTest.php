<?php

/**
 * Unit tests for Base64, Quoted-Printable, and identity transfer encoding decoding.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

declare(strict_types=1);

namespace PHPdot\Mail\Parser\Tests\Unit\Content;

use PHPdot\Mail\Parser\Content\ContentDecoder;
use PHPdot\Mail\Parser\Exception\InvalidMimeStructureException;
use PHPUnit\Framework\TestCase;

final class ContentDecoderTest extends TestCase
{
    private ContentDecoder $decoder;

    protected function setUp(): void
    {
        $this->decoder = new ContentDecoder();
    }

    public function testDecodeBase64(): void
    {
        $encoded = base64_encode('Hello World');
        $this->assertSame('Hello World', $this->decoder->decode($encoded, 'base64'));
    }

    public function testDecodeBase64WithLineBreaks(): void
    {
        $encoded = "SGVs\r\nbG8g\r\nV29y\r\nbGQ=";
        $this->assertSame('Hello World', $this->decoder->decode($encoded, 'base64'));
    }

    public function testDecodeBase64CaseInsensitive(): void
    {
        $encoded = base64_encode('test');
        $this->assertSame('test', $this->decoder->decode($encoded, 'BASE64'));
        $this->assertSame('test', $this->decoder->decode($encoded, 'Base64'));
    }

    public function testDecodeQuotedPrintable(): void
    {
        // =E9 is 0xE9, the raw byte for 'é' in ISO-8859-1
        $this->assertSame("caf\xE9", $this->decoder->decode("caf=E9", 'quoted-printable'));
    }

    public function testDecodeQuotedPrintableSoftLineBreak(): void
    {
        $encoded = "This is a long line that has been split using a soft=\r\n line break.";
        $result = $this->decoder->decode($encoded, 'quoted-printable');
        $this->assertSame("This is a long line that has been split using a soft line break.", $result);
    }

    public function testDecode7BitPassThrough(): void
    {
        $text = 'Hello World';
        $this->assertSame($text, $this->decoder->decode($text, '7bit'));
    }

    public function testDecode8BitPassThrough(): void
    {
        $text = 'Héllo Wörld';
        $this->assertSame($text, $this->decoder->decode($text, '8bit'));
    }

    public function testDecodeBinaryPassThrough(): void
    {
        $binary = "\x00\x01\x02\x03";
        $this->assertSame($binary, $this->decoder->decode($binary, 'binary'));
    }

    public function testDecodeEmptyEncodingPassThrough(): void
    {
        $text = 'Hello';
        $this->assertSame($text, $this->decoder->decode($text, ''));
    }

    public function testDecodeUnknownEncodingLenient(): void
    {
        $text = 'Hello';
        $this->assertSame($text, $this->decoder->decode($text, 'x-unknown', strict: false));
    }

    public function testDecodeUnknownEncodingStrict(): void
    {
        $this->expectException(InvalidMimeStructureException::class);
        $this->decoder->decode('Hello', 'x-unknown', strict: true);
    }

    public function testBase64WithBinaryGarbage(): void
    {
        // SGVsbG8= encodes "Hello", V29ybGQ= encodes "World"
        // Binary garbage between them — base64 decode strips non-base64 chars
        $input = "SGVsbG8=\x00\xFF\xFEV29ybGQ=";
        $result = $this->decoder->decode($input, 'base64');
        // Should decode what it can (garbage chars stripped, remaining base64 decoded)
        $this->assertNotEmpty($result);
        $this->assertStringContainsString('Hello', $result);
    }

    public function testBase64WithMissingPadding(): void
    {
        // "SGVsbG8" is "Hello" without the trailing = padding
        $result = $this->decoder->decode('SGVsbG8', 'base64');
        $this->assertSame('Hello', $result);
    }

    public function testBase64EmptyAfterWhitespaceStrip(): void
    {
        // Only whitespace — after stripping, empty base64 input
        $result = $this->decoder->decode("   \r\n  ", 'base64');
        $this->assertSame('', $result);
    }

    public function testQpIncompleteSequence(): void
    {
        // Trailing = is a soft line break in QP
        $result = $this->decoder->decode("test=", 'quoted-printable');
        $this->assertSame('test', $result);
    }

    public function testQpInvalidHex(): void
    {
        // =GZ is not valid hex — QP decode should preserve it or handle gracefully
        $result = $this->decoder->decode("test=GZ", 'quoted-printable');
        $this->assertStringContainsString('test', $result);
    }

    public function testEncodingNameWithWhitespace(): void
    {
        // Spaces around encoding name should still decode correctly
        $encoded = base64_encode('Hello');
        $result = $this->decoder->decode($encoded, ' base64 ');
        $this->assertSame('Hello', $result);
    }
}
