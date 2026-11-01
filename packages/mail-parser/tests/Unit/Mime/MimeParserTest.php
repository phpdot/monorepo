<?php

/**
 * Unit tests for recursive MIME tree parsing including multipart handling, content-type defaults, and boundary heuristics.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

declare(strict_types=1);

namespace PHPdot\Mail\Parser\Tests\Unit\Mime;

use PHPdot\Mail\Parser\Content\ContentDecoder;
use PHPdot\Mail\Parser\Exception\InvalidMimeStructureException;
use PHPdot\Mail\Parser\Header\HeaderCollection;
use PHPdot\Mail\Parser\Header\HeaderParser;
use PHPdot\Mail\Parser\Mime\MimeParser;
use PHPdot\Mail\Parser\ParserConfig;
use PHPdot\Mail\Parser\Result\MimeHeader;
use PHPUnit\Framework\TestCase;

final class MimeParserTest extends TestCase
{
    private MimeParser $parser;
    private HeaderParser $headerParser;
    private ContentDecoder $contentDecoder;

    protected function setUp(): void
    {
        $config = new ParserConfig();
        $this->headerParser = new HeaderParser($config);
        $this->contentDecoder = new ContentDecoder();
        $this->parser = new MimeParser($config, $this->headerParser);
    }

    public function testSingleTextPartParsing(): void
    {
        $body = "Hello, this is a simple text body.";
        $contentType = new MimeHeader('text/plain', ['charset' => 'utf-8']);
        $headers = new HeaderCollection();

        $node = $this->parser->parse($body, $contentType, $headers);

        $this->assertSame('text/plain', $node->getMimeType());
        $this->assertSame('text', $node->getPrimaryType());
        $this->assertSame('plain', $node->getSubType());
        $this->assertSame($body, $node->getRawBody());
        $this->assertEmpty($node->children);
    }

    public function testMultipartWithZeroParts(): void
    {
        // Boundary open then immediately close — parser sees one empty part between markers
        $body = "--boundary\r\n--boundary--\r\n";
        $contentType = new MimeHeader('multipart/mixed', ['boundary' => 'boundary']);
        $headers = new HeaderCollection();

        $node = $this->parser->parse($body, $contentType, $headers);

        $this->assertTrue($node->isMultipart());
        // The boundary detector yields one empty part between open/close markers
        $this->assertCount(1, $node->children);
        // The empty part should default to text/plain with empty body
        $this->assertSame('text/plain', $node->children[0]->getMimeType());
        $this->assertSame('', trim($node->children[0]->getRawBody()));
    }

    public function testContentTypeDefaulting(): void
    {
        // Part with no Content-Type header defaults to text/plain; charset=us-ascii
        $body = "--boundary\r\nThis is plain text without headers.\r\n--boundary--\r\n";
        $contentType = new MimeHeader('multipart/mixed', ['boundary' => 'boundary']);
        $headers = new HeaderCollection();

        $node = $this->parser->parse($body, $contentType, $headers);

        $this->assertCount(1, $node->children);
        $child = $node->children[0];
        $this->assertSame('text/plain', $child->getMimeType());
        $this->assertSame('us-ascii', $child->contentType->getParam('charset'));
    }

    public function testMixedCrlfLfSeparator(): void
    {
        // Header/body separated by \n\n (bare LF) instead of \r\n\r\n
        $partContent = "Content-Type: text/html\n\n<p>Hello</p>";
        $body = "--boundary\r\n" . $partContent . "\r\n--boundary--\r\n";
        $contentType = new MimeHeader('multipart/mixed', ['boundary' => 'boundary']);
        $headers = new HeaderCollection();

        $node = $this->parser->parse($body, $contentType, $headers);

        $this->assertCount(1, $node->children);
        $child = $node->children[0];
        $this->assertSame('text/html', $child->getMimeType());
        $this->assertStringContainsString('<p>Hello</p>', $child->getRawBody());
    }

    public function testBoundaryDetectionHeuristic(): void
    {
        // Multipart with NO boundary param in Content-Type, lenient mode
        $body = "--auto_boundary\r\nContent-Type: text/plain\r\n\r\nPart one.\r\n--auto_boundary\r\nContent-Type: text/html\r\n\r\n<p>Part two.</p>\r\n--auto_boundary--\r\n";
        $contentType = new MimeHeader('multipart/mixed');
        $headers = new HeaderCollection();

        $config = new ParserConfig(strict: false);
        $parser = new MimeParser($config, $this->headerParser);

        $node = $parser->parse($body, $contentType, $headers);

        // The heuristic should detect the boundary and parse parts
        $this->assertTrue($node->isMultipart());
        $this->assertGreaterThanOrEqual(1, count($node->children));
    }

    public function testMissingBoundaryStrictMode(): void
    {
        // Multipart with no boundary in strict mode should throw
        $body = "--some_boundary\r\nContent-Type: text/plain\r\n\r\nBody.\r\n--some_boundary--\r\n";
        $contentType = new MimeHeader('multipart/mixed');
        $headers = new HeaderCollection();

        $config = new ParserConfig(strict: true);
        $parser = new MimeParser($config, $this->headerParser);

        $this->expectException(InvalidMimeStructureException::class);
        $parser->parse($body, $contentType, $headers);
    }
}
