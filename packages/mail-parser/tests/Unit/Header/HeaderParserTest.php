<?php

/**
 * Unit tests for email header block parsing including unfolding, size limits, and specialized header dispatch.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

declare(strict_types=1);

namespace PHPdot\Mail\Parser\Tests\Unit\Header;

use PHPdot\Mail\Parser\Header\HeaderParser;
use PHPdot\Mail\Parser\ParserConfig;
use PHPUnit\Framework\TestCase;

final class HeaderParserTest extends TestCase
{
    private HeaderParser $parser;

    protected function setUp(): void
    {
        $this->parser = new HeaderParser(new ParserConfig());
    }

    public function testParseSimpleHeaders(): void
    {
        $block = "From: john@example.com\r\nTo: jane@example.com\r\nSubject: Hello";
        $collection = $this->parser->parse($block);

        $this->assertSame(3, $collection->count());
        $this->assertTrue($collection->has('From'));
        $this->assertTrue($collection->has('To'));
        $this->assertTrue($collection->has('Subject'));
    }

    public function testCaseInsensitiveLookup(): void
    {
        $block = "Content-Type: text/plain\r\n";
        $collection = $this->parser->parse($block);

        $this->assertTrue($collection->has('content-type'));
        $this->assertTrue($collection->has('Content-Type'));
        $this->assertTrue($collection->has('CONTENT-TYPE'));
    }

    public function testHeaderUnfolding(): void
    {
        $block = "Subject: This is a very long subject\r\n that has been folded\r\n across multiple lines";
        $collection = $this->parser->parse($block);

        $subject = $collection->get('Subject');
        $this->assertNotNull($subject);
        $this->assertSame('This is a very long subject that has been folded across multiple lines', $subject->value);
    }

    public function testHeaderUnfoldingWithTab(): void
    {
        $block = "Subject: Folded\r\n\twith tab";
        $collection = $this->parser->parse($block);

        $subject = $collection->get('Subject');
        $this->assertNotNull($subject);
        $this->assertSame('Folded with tab', $subject->value);
    }

    public function testAddressHeaderParsed(): void
    {
        $block = "From: John Doe <john@example.com>";
        $collection = $this->parser->parse($block);

        $from = $collection->get('From');
        $this->assertNotNull($from);
        $this->assertNotNull($from->addresses);
        $this->assertCount(1, $from->addresses);
        $this->assertSame('john@example.com', $from->addresses[0]->address);
        $this->assertSame('John Doe', $from->addresses[0]->name);
    }

    public function testDateHeaderParsed(): void
    {
        $block = "Date: Thu, 13 Feb 2020 15:47:25 +0000";
        $collection = $this->parser->parse($block);

        $date = $collection->get('Date');
        $this->assertNotNull($date);
        $this->assertNotNull($date->dateTime);
        $this->assertSame('2020-02-13', $date->dateTime->format('Y-m-d'));
    }

    public function testStructuredHeaderParsed(): void
    {
        $block = "Content-Type: text/plain; charset=utf-8";
        $collection = $this->parser->parse($block);

        $ct = $collection->get('Content-Type');
        $this->assertNotNull($ct);
        $this->assertNotNull($ct->structured);
        $this->assertSame('text/plain', $ct->structured->value);
        $this->assertSame('utf-8', $ct->structured->getParam('charset'));
    }

    public function testEncodedWordDecoded(): void
    {
        $block = "Subject: =?UTF-8?B?SGVsbG8gV29ybGQ=?=";
        $collection = $this->parser->parse($block);

        $subject = $collection->get('Subject');
        $this->assertNotNull($subject);
        $this->assertSame('Hello World', $subject->value);
    }

    public function testGetAllDuplicateHeaders(): void
    {
        $block = "Received: from a\r\nReceived: from b\r\nReceived: from c";
        $collection = $this->parser->parse($block);

        $all = $collection->getAll('Received');
        $this->assertCount(3, $all);
    }

    public function testEmptyHeaderBlock(): void
    {
        $collection = $this->parser->parse('');
        $this->assertSame(0, $collection->count());
    }

    public function testBareLfNormalization(): void
    {
        $block = "From: john@example.com\nSubject: Test";
        $collection = $this->parser->parse($block);

        $this->assertTrue($collection->has('From'));
        $this->assertTrue($collection->has('Subject'));
    }

    public function testMissingHeaderReturnsNull(): void
    {
        $block = "From: john@example.com";
        $collection = $this->parser->parse($block);

        $this->assertNull($collection->get('X-Missing'));
        $this->assertFalse($collection->has('X-Missing'));
    }

    public function testColonsInHeaderValue(): void
    {
        $block = "X-Custom: value: with: colons";
        $collection = $this->parser->parse($block);

        $header = $collection->get('X-Custom');
        $this->assertNotNull($header);
        $this->assertSame('value: with: colons', $header->value);
    }

    public function testHeaderNameWithWhitespace(): void
    {
        $block = "Content Type: text/plain\r\nSubject: Valid";
        $collection = $this->parser->parse($block);

        // "Content Type" has a space in the name — should be skipped
        $this->assertFalse($collection->has('Content Type'));
        // The valid header should still be parsed
        $this->assertTrue($collection->has('Subject'));
    }

    public function testWhitespaceOnlyHeaderValue(): void
    {
        $block = "Subject:   \r\nFrom: test@example.com";
        $collection = $this->parser->parse($block);

        $subject = $collection->get('Subject');
        $this->assertNotNull($subject);
        // Value should be empty or whitespace (ltrim is applied by parser)
        $this->assertSame('', trim($subject->value));
    }

    public function testHeaderSizeLimitStrict(): void
    {
        $config = new ParserConfig(strict: true, maxHeaderSize: 50);
        $parser = new HeaderParser($config);

        $block = str_repeat("X-Pad: " . str_repeat('A', 40) . "\r\n", 5);
        $this->assertGreaterThan(50, strlen($block));

        $this->expectException(\PHPdot\Mail\Parser\Exception\SecurityLimitException::class);
        $parser->parse($block);
    }

    public function testHeaderSizeLimitLenient(): void
    {
        $config = new ParserConfig(strict: false, maxHeaderSize: 50);
        $parser = new HeaderParser($config);

        $block = "Subject: Short\r\n" . str_repeat("X-Pad: " . str_repeat('A', 40) . "\r\n", 5);
        $this->assertGreaterThan(50, strlen($block));

        // Should not throw — lenient mode truncates
        $collection = $parser->parse($block);

        // At least the first header should be parseable
        $this->assertTrue($collection->has('Subject'));
    }
}
