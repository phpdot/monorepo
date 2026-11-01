<?php

/**
 * parseStream() read through its spool: the same results as parse() for every shape of message the old streamer met.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

declare(strict_types=1);

namespace PHPdot\Mail\Parser\Tests\Unit\Source;

use PHPdot\Mail\Parser\Exception\SecurityLimitException;
use PHPdot\Mail\Parser\MailParser;
use PHPdot\Mail\Parser\ParserConfig;
use PHPUnit\Framework\TestCase;

final class ParseStreamTest extends TestCase
{
    private MailParser $parser;

    protected function setUp(): void
    {
        $this->parser = new MailParser();
    }

    /**
     * @return resource
     */
    private function createStream(string $content)
    {
        $stream = fopen('php://memory', 'r+');
        $this->assertNotFalse($stream);
        fwrite($stream, $content);
        rewind($stream);

        return $stream;
    }

    public function testSimpleNonMultipartStream(): void
    {
        $raw = "From: sender@example.com\r\nSubject: Hello World\r\n\r\nThis is the body text.";

        $stream = $this->createStream($raw);
        $email = $this->parser->parseStream($stream);
        fclose($stream);

        $this->assertSame('Hello World', $email->subject);
        $this->assertSame('This is the body text.', $email->text);
    }

    public function testMultipartStateTransitions(): void
    {
        $raw = "Content-Type: multipart/alternative; boundary=\"alt\"\r\n\r\n";
        $raw .= "--alt\r\n";
        $raw .= "Content-Type: text/plain\r\n\r\n";
        $raw .= "Plain text body\r\n";
        $raw .= "--alt\r\n";
        $raw .= "Content-Type: text/html\r\n\r\n";
        $raw .= "<p>HTML body</p>\r\n";
        $raw .= "--alt--\r\n";

        $stream = $this->createStream($raw);
        $email = $this->parser->parseStream($stream);
        fclose($stream);

        $this->assertSame('Plain text body', $email->text);
        $this->assertSame('<p>HTML body</p>', $email->html);
    }

    public function testNestedMultipartStateTransitions(): void
    {
        $raw = "Content-Type: multipart/mixed; boundary=\"outer\"\r\n\r\n";
        $raw .= "--outer\r\n";
        $raw .= "Content-Type: multipart/alternative; boundary=\"inner\"\r\n\r\n";
        $raw .= "--inner\r\n";
        $raw .= "Content-Type: text/plain\r\n\r\n";
        $raw .= "Nested plain text\r\n";
        $raw .= "--inner\r\n";
        $raw .= "Content-Type: text/html\r\n\r\n";
        $raw .= "<b>Nested HTML</b>\r\n";
        $raw .= "--inner--\r\n";
        $raw .= "--outer\r\n";
        $raw .= "Content-Type: application/octet-stream; name=\"file.bin\"\r\n";
        $raw .= "Content-Disposition: attachment; filename=\"file.bin\"\r\n";
        $raw .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $raw .= base64_encode('binary data') . "\r\n";
        $raw .= "--outer--\r\n";

        $stream = $this->createStream($raw);
        $email = $this->parser->parseStream($stream);
        fclose($stream);

        $this->assertSame('Nested plain text', $email->text);
        $this->assertSame('<b>Nested HTML</b>', $email->html);
        $this->assertCount(1, $email->attachments);
        $this->assertSame('file.bin', $email->attachments[0]->filename);
    }

    public function testEofMidHeader(): void
    {
        // Stream ends in the middle of headers — no blank line separator
        $raw = "From: test@example.com\r\nSubject: Hello World";

        $stream = $this->createStream($raw);
        $email = $this->parser->parseStream($stream);
        fclose($stream);

        // Headers should still be parsed
        $this->assertSame('Hello World', $email->subject);
        $this->assertNotNull($email->from);
        $this->assertSame('test@example.com', $email->from->address);
    }

    public function testEofMidBody(): void
    {
        // Multipart stream ends without closing boundary
        $raw = "Content-Type: multipart/mixed; boundary=\"b1\"\r\n\r\n";
        $raw .= "--b1\r\n";
        $raw .= "Content-Type: text/plain\r\n\r\n";
        $raw .= "Body text without closing boundary";

        $stream = $this->createStream($raw);
        $email = $this->parser->parseStream($stream);
        fclose($stream);

        $this->assertSame('Body text without closing boundary', $email->text);
    }

    public function testEofInPreamble(): void
    {
        // Multipart stream ends before the first boundary
        $raw = "Content-Type: multipart/mixed; boundary=\"b1\"\r\n\r\n";
        $raw .= "This is the preamble, and the stream ends here.";

        $stream = $this->createStream($raw);
        $email = $this->parser->parseStream($stream);
        fclose($stream);

        // No delimiter line at all: the body is read as text, exactly as parse() reads it
        $this->assertSame(MailParser::parse($raw)->text, $email->text);
        $this->assertSame('This is the preamble, and the stream ends here.', $email->text);
    }

    public function testHeaderSizeLimitMidStream(): void
    {
        $config = new ParserConfig(strict: true, maxHeaderSize: 50);
        $parser = new MailParser($config);

        $raw = "From: test@example.com\r\nSubject: " . str_repeat('A', 100) . "\r\n\r\nBody text";

        $stream = $this->createStream($raw);

        $this->expectException(SecurityLimitException::class);
        $parser->parseStream($stream);
        fclose($stream);
    }

    public function testEmptyMultipartNoParts(): void
    {
        // Multipart with boundaries but no content between them
        $raw = "Content-Type: multipart/mixed; boundary=\"b1\"\r\n\r\n";
        $raw .= "--b1\r\n";
        $raw .= "--b1--\r\n";

        $stream = $this->createStream($raw);
        $email = $this->parser->parseStream($stream);
        fclose($stream);

        // An empty part is created between the boundaries
        $this->assertTrue($email->attachments->isEmpty());
    }

    public function testMultipartWithPreambleAndEpilogue(): void
    {
        $raw = "Content-Type: multipart/mixed; boundary=\"b1\"\r\n\r\n";
        $raw .= "This is the preamble — it should be discarded.\r\n";
        $raw .= "--b1\r\n";
        $raw .= "Content-Type: text/plain\r\n\r\n";
        $raw .= "Actual body content\r\n";
        $raw .= "--b1--\r\n";
        $raw .= "This is the epilogue — it should also be discarded.\r\n";

        $stream = $this->createStream($raw);
        $email = $this->parser->parseStream($stream);
        fclose($stream);

        $this->assertSame('Actual body content', $email->text);
        // Preamble and epilogue should not appear in the parsed output
        $this->assertStringNotContainsString('preamble', $email->text ?? '');
        $this->assertStringNotContainsString('epilogue', $email->text ?? '');
    }

    public function testAttachmentViaTempStream(): void
    {
        // Non-text parts use temp stream for buffering
        $binaryData = "\x89PNG\r\n\x1a\n" . str_repeat("\x00", 100);

        $raw = "Content-Type: multipart/mixed; boundary=\"b1\"\r\n\r\n";
        $raw .= "--b1\r\n";
        $raw .= "Content-Type: text/plain\r\n\r\n";
        $raw .= "Some text\r\n";
        $raw .= "--b1\r\n";
        $raw .= "Content-Type: image/png; name=\"image.png\"\r\n";
        $raw .= "Content-Disposition: attachment; filename=\"image.png\"\r\n";
        $raw .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $raw .= base64_encode($binaryData) . "\r\n";
        $raw .= "--b1--\r\n";

        $stream = $this->createStream($raw);
        $email = $this->parser->parseStream($stream);
        fclose($stream);

        $this->assertSame('Some text', $email->text);
        $this->assertCount(1, $email->attachments);

        $att = $email->attachments[0];
        $this->assertSame('image.png', $att->filename);
        $this->assertSame('image/png', $att->mimeType);
        $this->assertSame(strlen($binaryData), $att->size);
        $this->assertSame($binaryData, $att->content());
    }
}
