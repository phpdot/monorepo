<?php

/**
 * Unit tests for MIME structured header parsing including parameters, quotes, comments, and RFC 2231 encoding.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

declare(strict_types=1);

namespace PHPdot\Mail\Parser\Tests\Unit\Header;

use PHPdot\Mail\Parser\Content\CharsetConverter;
use PHPdot\Mail\Parser\Header\StructuredHeaderParser;
use PHPdot\Mail\Parser\Rfc2231\ParameterDecoder;
use PHPUnit\Framework\TestCase;

final class StructuredHeaderParserTest extends TestCase
{
    private StructuredHeaderParser $parser;

    protected function setUp(): void
    {
        $this->parser = new StructuredHeaderParser(
            new ParameterDecoder(new CharsetConverter()),
        );
    }

    public function testSimpleContentType(): void
    {
        $result = $this->parser->parse('text/plain');
        $this->assertSame('text/plain', $result->value);
        $this->assertSame([], $result->params);
    }

    public function testContentTypeWithCharset(): void
    {
        $result = $this->parser->parse('text/plain; charset=utf-8');
        $this->assertSame('text/plain', $result->value);
        $this->assertSame('utf-8', $result->getParam('charset'));
    }

    public function testContentTypeWithMultipleParams(): void
    {
        $result = $this->parser->parse('text/plain; charset="utf-8"; format=flowed; delsp=yes');
        $this->assertSame('text/plain', $result->value);
        $this->assertSame('utf-8', $result->getParam('charset'));
        $this->assertSame('flowed', $result->getParam('format'));
        $this->assertSame('yes', $result->getParam('delsp'));
    }

    public function testContentTypeWithQuotedBoundary(): void
    {
        $result = $this->parser->parse('multipart/mixed; boundary="----=_Part_123"');
        $this->assertSame('multipart/mixed', $result->value);
        $this->assertSame('----=_Part_123', $result->getParam('boundary'));
    }

    public function testContentDisposition(): void
    {
        $result = $this->parser->parse('attachment; filename="report.pdf"');
        $this->assertSame('attachment', $result->value);
        $this->assertSame('report.pdf', $result->getParam('filename'));
    }

    public function testContentDispositionInline(): void
    {
        $result = $this->parser->parse('inline; filename="image.png"');
        $this->assertSame('inline', $result->value);
        $this->assertSame('image.png', $result->getParam('filename'));
    }

    public function testCaseInsensitiveParamLookup(): void
    {
        $result = $this->parser->parse('text/plain; Charset=utf-8');
        $this->assertSame('utf-8', $result->getParam('charset'));
        $this->assertSame('utf-8', $result->getParam('CHARSET'));
    }

    public function testRfc2231EncodedFilename(): void
    {
        $result = $this->parser->parse("attachment; filename*=utf-8''%C3%A9l%C3%A8ves.pdf");
        $this->assertSame('attachment', $result->value);
        $this->assertSame('élèves.pdf', $result->getParam('filename'));
    }

    public function testEmptyHeaderValue(): void
    {
        $result = $this->parser->parse('');
        $this->assertSame('', $result->value);
    }

    public function testContentTypeWithComment(): void
    {
        $result = $this->parser->parse('text/plain (Plain Text); charset=utf-8');
        $this->assertSame('text/plain', $result->value);
        $this->assertSame('utf-8', $result->getParam('charset'));
    }

    public function testParamMissReturnsNull(): void
    {
        $result = $this->parser->parse('text/plain');
        $this->assertNull($result->getParam('charset'));
    }

    public function testNestedComments(): void
    {
        $result = $this->parser->parse('text/plain (outer (inner) comment); charset=utf-8');
        $this->assertSame('text/plain', $result->value);
        $this->assertSame('utf-8', $result->getParam('charset'));
    }

    public function testEscapedCharsInQuotedValue(): void
    {
        $result = $this->parser->parse('attachment; filename="file\"name.pdf"');
        $this->assertSame('attachment', $result->value);
        $this->assertSame('file"name.pdf', $result->getParam('filename'));
    }

    public function testUnmatchedQuote(): void
    {
        $result = $this->parser->parse('attachment; filename="unclosed.pdf');
        $this->assertSame('attachment', $result->value);
        $filename = $result->getParam('filename');
        $this->assertNotNull($filename);
        $this->assertStringContainsString('unclosed.pdf', $filename);
    }

    public function testEmptyParameterValue(): void
    {
        $result = $this->parser->parse('text/plain; charset=');
        $this->assertSame('text/plain', $result->value);
        $charset = $result->getParam('charset');
        // charset should be empty string or null (skipped)
        $this->assertTrue($charset === '' || $charset === null);
    }

    public function testSemicolonInsideQuotes(): void
    {
        $result = $this->parser->parse('text/plain; charset="utf;8"; boundary=test');
        $this->assertSame('text/plain', $result->value);
        $this->assertSame('utf;8', $result->getParam('charset'));
        $this->assertSame('test', $result->getParam('boundary'));
    }

    public function testMultipleConsecutiveSemicolons(): void
    {
        $result = $this->parser->parse('text/plain;; charset=utf-8');
        $this->assertSame('text/plain', $result->value);
        $this->assertSame('utf-8', $result->getParam('charset'));
    }
}
