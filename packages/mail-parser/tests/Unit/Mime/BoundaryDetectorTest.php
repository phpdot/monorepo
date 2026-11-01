<?php

/**
 * Unit tests for MIME boundary detection and splitting including edge cases with whitespace, long boundaries, and mixed line endings.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

declare(strict_types=1);

namespace PHPdot\Mail\Parser\Tests\Unit\Mime;

use PHPdot\Mail\Parser\Mime\BoundaryDetector;
use PHPUnit\Framework\TestCase;

final class BoundaryDetectorTest extends TestCase
{
    private BoundaryDetector $detector;

    protected function setUp(): void
    {
        $this->detector = new BoundaryDetector();
    }

    public function testSplitStandard(): void
    {
        $body = "preamble\r\n--boundary\r\nPart 1\r\n--boundary\r\nPart 2\r\n--boundary--\r\nepilogue";
        $result = $this->detector->split($body, 'boundary');

        $this->assertCount(2, $result['parts']);
        $this->assertSame("Part 1", $result['parts'][0]);
        $this->assertSame("Part 2", $result['parts'][1]);
    }

    public function testSplitWithPreambleAndEpilogue(): void
    {
        $body = "This is the preamble.\r\n--bound\r\nPart 1\r\n--bound--\r\nThis is the epilogue.";
        $result = $this->detector->split($body, 'bound');

        $this->assertStringContainsString('preamble', $result['preamble']);
        $this->assertCount(1, $result['parts']);
        $this->assertStringContainsString('epilogue', $result['epilogue']);
    }

    public function testSplitMissingCloseDelimiter(): void
    {
        // Lenient: treat remaining content as the last part
        $body = "preamble\r\n--boundary\r\nPart 1\r\n--boundary\r\nPart 2 without close";
        $result = $this->detector->split($body, 'boundary');

        $this->assertCount(2, $result['parts']);
        $this->assertStringContainsString('Part 2', $result['parts'][1]);
    }

    public function testSplitNoBoundaryFound(): void
    {
        $body = "Just some text with no boundaries";
        $result = $this->detector->split($body, 'boundary');

        $this->assertCount(1, $result['parts']);
        $this->assertSame($body, $result['parts'][0]);
    }

    public function testSplitEmptyParts(): void
    {
        $body = "--boundary\r\n\r\n--boundary\r\n\r\n--boundary--";
        $result = $this->detector->split($body, 'boundary');

        $this->assertCount(2, $result['parts']);
    }

    public function testSplitBareLfLineEndings(): void
    {
        $body = "preamble\n--boundary\nPart 1\n--boundary\nPart 2\n--boundary--";
        $result = $this->detector->split($body, 'boundary');

        $this->assertCount(2, $result['parts']);
    }

    public function testBoundaryWithSpecialChars(): void
    {
        $boundary = '----=_Part_123.456';
        $body = "preamble\r\n--{$boundary}\r\nPart 1\r\n--{$boundary}--";
        $result = $this->detector->split($body, $boundary);

        $this->assertCount(1, $result['parts']);
    }

    public function testBoundaryNotAtLineStart(): void
    {
        // Boundary in middle of line should be ignored
        $body = "--boundary\r\nContent has --boundary in it\r\n--boundary--";
        $result = $this->detector->split($body, 'boundary');

        $this->assertCount(1, $result['parts']);
        $this->assertStringContainsString('--boundary', $result['parts'][0]);
    }

    public function testBoundaryWithTrailingWhitespace(): void
    {
        $body = "--boundary   \r\nContent\r\n--boundary--";
        $result = $this->detector->split($body, 'boundary');

        $this->assertCount(1, $result['parts']);
        $this->assertStringContainsString('Content', $result['parts'][0]);
    }

    public function testVeryLongBoundary(): void
    {
        $boundary = str_repeat('a', 500) . '_end';
        $body = "--{$boundary}\r\nPart content\r\n--{$boundary}--";
        $result = $this->detector->split($body, $boundary);

        $this->assertCount(1, $result['parts']);
        $this->assertStringContainsString('Part content', $result['parts'][0]);
    }

    public function testBoundaryAtPositionZero(): void
    {
        // Body starts immediately with boundary — no preamble, no leading newline
        $body = "--boundary\r\nContent\r\n--boundary--";
        $result = $this->detector->split($body, 'boundary');

        $this->assertCount(1, $result['parts']);
        $this->assertStringContainsString('Content', $result['parts'][0]);
        $this->assertSame('', $result['preamble']);
    }

    public function testMixedCrlfAndLfInBody(): void
    {
        // Some lines end with \r\n, some with \n
        $body = "--boundary\r\nLine1\r\nLine2\n--boundary\nLine3\r\n--boundary--";
        $result = $this->detector->split($body, 'boundary');

        $this->assertCount(2, $result['parts']);
        $this->assertStringContainsString('Line1', $result['parts'][0]);
        $this->assertStringContainsString('Line3', $result['parts'][1]);
    }
}
