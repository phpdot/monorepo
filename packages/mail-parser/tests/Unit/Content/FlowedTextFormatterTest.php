<?php

/**
 * Unit tests for RFC 3676 format=flowed text processing including CJK, quote depths, and signature handling.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

declare(strict_types=1);

namespace PHPdot\Mail\Parser\Tests\Unit\Content;

use PHPdot\Mail\Parser\Content\FlowedTextFormatter;
use PHPUnit\Framework\TestCase;

final class FlowedTextFormatterTest extends TestCase
{
    private FlowedTextFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new FlowedTextFormatter();
    }

    public function testFixedLinesNotJoined(): void
    {
        $input = "Line one\r\nLine two\r\nLine three";
        $result = $this->formatter->decode($input);
        $this->assertSame("Line one\r\nLine two\r\nLine three", $result);
    }

    public function testFlowedLinesJoined(): void
    {
        $input = "This is a flowed \r\nline that should \r\nbe joined.";
        $result = $this->formatter->decode($input);
        $this->assertSame("This is a flowed line that should be joined.", $result);
    }

    public function testFlowedWithDelSp(): void
    {
        $input = "This is a flowed \r\nline with delsp.";
        $result = $this->formatter->decode($input, delSp: true);
        $this->assertSame("This is a flowedline with delsp.", $result);
    }

    public function testQuotedTextFlowed(): void
    {
        $input = "> Quoted flowed \r\n> text joined.";
        $result = $this->formatter->decode($input);
        $this->assertSame("> Quoted flowed text joined.", $result);
    }

    public function testQuoteDepthChange(): void
    {
        $input = "> Level one\r\n>> Level two\r\n> Back to one";
        $result = $this->formatter->decode($input);
        $this->assertSame("> Level one\r\n>> Level two\r\n> Back to one", $result);
    }

    public function testSignatureSeparator(): void
    {
        $input = "Body text \r\nmore text\r\n-- \r\nJohn Doe";
        $result = $this->formatter->decode($input);
        $this->assertSame("Body text more text\r\n-- \r\nJohn Doe", $result);
    }

    public function testCjkTextWithDelSp(): void
    {
        // CJK characters followed by trailing space with DelSp=yes
        // The trailing space should be removed when joining
        $input = "\xe4\xb8\xad\xe6\x96\x87 \r\n\xe6\xb5\x8b\xe8\xaf\x95";
        $result = $this->formatter->decode($input, delSp: true);
        $this->assertSame("\xe4\xb8\xad\xe6\x96\x87\xe6\xb5\x8b\xe8\xaf\x95", $result);
    }

    public function testEmptyLinesBetweenFlowedParagraphs(): void
    {
        // Empty line between two flowed paragraphs should separate them
        $input = "First paragraph \r\nstill first.\r\n\r\nSecond paragraph.";
        $result = $this->formatter->decode($input);
        $this->assertSame("First paragraph still first.\r\n\r\nSecond paragraph.", $result);
    }

    public function testDoubleDashNotSignatureSeparator(): void
    {
        // "--" (no trailing space) should NOT be treated as signature separator
        $input = "Some text\r\n--\r\nMore text";
        $result = $this->formatter->decode($input);
        $this->assertSame("Some text\r\n--\r\nMore text", $result);
    }

    public function testConsecutiveQuoteDepthChanges(): void
    {
        // Each quote depth change breaks a paragraph
        $input = "> text\r\n>> text\r\n>>> text";
        $result = $this->formatter->decode($input);
        $this->assertSame("> text\r\n>> text\r\n>>> text", $result);
    }

    public function testFlowedLineAtEof(): void
    {
        // Flowed line (trailing space) at end of input with no fixed terminator
        $input = "Hello world ";
        $result = $this->formatter->decode($input);
        // The flowed line should be flushed as-is at EOF
        $this->assertSame("Hello world ", $result);
    }

    public function testSpaceStuffedEmptyLineInQuote(): void
    {
        // "> " (quote + space) after space-stuffing removal = empty line
        $input = "> First line\r\n> \r\n> Third line";
        $result = $this->formatter->decode($input);
        $this->assertSame("> First line\r\n> \r\n> Third line", $result);
    }
}
