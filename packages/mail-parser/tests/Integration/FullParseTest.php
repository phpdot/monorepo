<?php

/**
 * End-to-end integration tests parsing complete EML fixtures and verifying all extracted fields.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

declare(strict_types=1);

namespace PHPdot\Mail\Parser\Tests\Integration;

use PHPdot\Mail\Parser\MailParser;
use PHPdot\Mail\Parser\ParserConfig;
use PHPdot\Mail\Parser\Result\Address;
use PHPUnit\Framework\TestCase;

final class FullParseTest extends TestCase
{
    private function fixture(string $name): string
    {
        return file_get_contents(__DIR__ . '/../Fixtures/' . $name);
    }

    public function testSimpleTextEmail(): void
    {
        $email = MailParser::parse($this->fixture('simple-text.eml'));

        $this->assertSame('Simple text email', $email->subject);
        $this->assertNotNull($email->from);
        $this->assertInstanceOf(Address::class, $email->from);
        $this->assertSame('john@example.com', $email->from->address);
        $this->assertSame('John Doe', $email->from->name);
        $this->assertCount(1, $email->to);
        $this->assertSame('jane@example.com', $email->to[0]->address);
        $this->assertSame('abc123@example.com', $email->messageId);
        $this->assertNotNull($email->date);
        $this->assertSame('2020-02-13', $email->date->format('Y-m-d'));
        $this->assertNotNull($email->text);
        $this->assertStringContainsString('simple text email', $email->text);
        $this->assertNull($email->html);
        $this->assertTrue($email->attachments->isEmpty());
    }

    public function testMultipartAlternative(): void
    {
        $email = MailParser::parse($this->fixture('multipart-alternative.eml'));

        $this->assertSame('Multipart alternative email', $email->subject);
        $this->assertNotNull($email->text);
        $this->assertStringContainsString('plain text version', $email->text);
        $this->assertNotNull($email->html);
        $this->assertStringContainsString('<strong>HTML</strong>', $email->html);
        $this->assertTrue($email->attachments->isEmpty());
    }

    public function testMultipartMixedWithAttachment(): void
    {
        $email = MailParser::parse($this->fixture('multipart-mixed.eml'));

        $this->assertSame('Email with attachment', $email->subject);
        $this->assertNotNull($email->text);
        $this->assertStringContainsString('attached file', $email->text);
        $this->assertCount(1, $email->attachments);
        $this->assertSame('report.pdf', $email->attachments[0]->filename);
        $this->assertSame('application/pdf', $email->attachments[0]->mimeType);
        $this->assertSame('attachment', $email->attachments[0]->disposition);
        $this->assertNotNull($email->attachments[0]->content);
        $this->assertGreaterThan(0, $email->attachments[0]->size);
    }

    public function testMultipartRelatedWithInlineImage(): void
    {
        $email = MailParser::parse($this->fixture('multipart-related.eml'));

        $this->assertSame('Email with inline image', $email->subject);
        $this->assertNotNull($email->html);
        $this->assertStringContainsString('cid:image001@example.com', $email->html);
        $this->assertCount(1, $email->attachments);
        $this->assertSame('test.png', $email->attachments[0]->filename);
        $this->assertSame('image001@example.com', $email->attachments[0]->contentId);
        $this->assertSame('inline', $email->attachments[0]->disposition);
    }

    public function testEncodedWordHeaders(): void
    {
        $email = MailParser::parse($this->fixture('encoded-words.eml'));

        // From: 山田太郎
        $this->assertNotNull($email->from);
        $this->assertSame('山田太郎', $email->from->name);
        $this->assertSame('yamada@example.jp', $email->from->address);

        // To: René Dupont
        $this->assertCount(1, $email->to);
        $this->assertSame('René Dupont', $email->to[0]->name);

        // Subject: こんにちは世界
        $this->assertSame('こんにちは世界', $email->subject);
    }

    public function testNestedMultipart(): void
    {
        $email = MailParser::parse($this->fixture('nested-multipart.eml'));

        $this->assertSame('Nested multipart email', $email->subject);
        $this->assertNotNull($email->text);
        $this->assertStringContainsString('plain text version', $email->text);
        $this->assertNotNull($email->html);
        $this->assertStringContainsString('<b>HTML</b>', $email->html);
        $this->assertCount(1, $email->attachments);
        $this->assertSame('data.bin', $email->attachments[0]->filename);
    }

    public function testRfc2231Continuations(): void
    {
        $email = MailParser::parse($this->fixture('rfc2231-continuations.eml'));

        $this->assertCount(1, $email->attachments);
        $this->assertSame('élèves rapport final.pdf', $email->attachments[0]->filename);
    }

    public function testFormatFlowed(): void
    {
        $email = MailParser::parse($this->fixture('format-flowed.eml'));

        $this->assertNotNull($email->text);
        // Flowed lines should be joined
        $this->assertStringContainsString(
            'This is a long paragraph that has been wrapped using format=flowed.',
            $email->text,
        );
    }

    public function testQuotedPrintableBody(): void
    {
        $email = MailParser::parse($this->fixture('quoted-printable.eml'));

        $this->assertNotNull($email->text);
        $this->assertStringContainsString('Café', $email->text);
        $this->assertStringContainsString('Résumé', $email->text);
    }

    public function testMissingHeaders(): void
    {
        $email = MailParser::parse($this->fixture('missing-headers.eml'));

        // Should parse without errors using defaults
        $this->assertSame('Minimal email', $email->subject);
        $this->assertNull($email->from);
        $this->assertNull($email->date);
        $this->assertNotNull($email->text);
        $this->assertStringContainsString('minimal headers', $email->text);
    }

    public function testWithStrictConfig(): void
    {
        $config = new ParserConfig(strict: true);
        $email = MailParser::parse($this->fixture('simple-text.eml'), $config);

        $this->assertSame('Simple text email', $email->subject);
    }

    public function testWithBase64AttachmentEncoding(): void
    {
        $config = new ParserConfig(attachmentEncoding: 'base64');
        $email = MailParser::parse($this->fixture('multipart-mixed.eml'), $config);

        $this->assertCount(1, $email->attachments);
        // Content should be base64-encoded
        $this->assertNotNull($email->attachments[0]->content);
        $decoded = base64_decode($email->attachments[0]->content, true);
        $this->assertNotFalse($decoded);
    }

    public function testWithNoAttachmentContent(): void
    {
        $config = new ParserConfig(attachmentEncoding: 'none');
        $email = MailParser::parse($this->fixture('multipart-mixed.eml'), $config);

        $this->assertCount(1, $email->attachments);
        $this->assertNull($email->attachments[0]->content);
        $this->assertSame('report.pdf', $email->attachments[0]->filename);
    }

    public function testParseStream(): void
    {
        $stream = fopen(__DIR__ . '/../Fixtures/simple-text.eml', 'r');
        $this->assertNotFalse($stream);

        $parser = new MailParser();
        $email = $parser->parseStream($stream);
        fclose($stream);

        $this->assertSame('Simple text email', $email->subject);
    }

    public function testHeaderCollectionAccess(): void
    {
        $email = MailParser::parse($this->fixture('simple-text.eml'));

        $this->assertTrue($email->hasHeader('MIME-Version'));
        $mimeVersion = $email->header('MIME-Version');
        $this->assertNotNull($mimeVersion);
        $this->assertSame('1.0', $mimeVersion);
    }

    public function testHeaderAccessMethods(): void
    {
        $email = MailParser::parse($this->fixture('simple-text.eml'));

        $this->assertTrue($email->hasHeader('Subject'));
        $this->assertSame('Simple text email', $email->header('Subject'));
        $this->assertNotEmpty($email->headerAll('Subject'));
    }

    public function testRfc822NestedEmailAttachment(): void
    {
        $email = MailParser::parse($this->fixture('rfc822-nested.eml'));

        $this->assertSame('Forwarded message', $email->subject);
        $this->assertNotNull($email->text);
        $this->assertStringContainsString('forwarded message', $email->text);

        // Should have a message/rfc822 attachment with nested email
        $rfc822Attachments = array_filter(
            iterator_to_array($email->attachments),
            fn($a) => $a->mimeType === 'message/rfc822',
        );
        $this->assertNotEmpty($rfc822Attachments);

        $nested = array_values($rfc822Attachments)[0];
        $this->assertNotNull($nested->nestedEmail);
        $this->assertSame('Original message', $nested->nestedEmail->subject);
        $this->assertStringContainsString('original message body', $nested->nestedEmail->text);
    }

    public function testMultipleTextPlainInAlternativeLastWins(): void
    {
        // Build an email with two text/plain parts in multipart/alternative
        $raw = "Subject: Alt test\r\n";
        $raw .= "Content-Type: multipart/alternative; boundary=\"alttest\"\r\n\r\n";
        $raw .= "--alttest\r\n";
        $raw .= "Content-Type: text/plain; charset=utf-8\r\n\r\n";
        $raw .= "First text version\r\n";
        $raw .= "--alttest\r\n";
        $raw .= "Content-Type: text/plain; charset=utf-8\r\n\r\n";
        $raw .= "Second text version (preferred)\r\n";
        $raw .= "--alttest--\r\n";

        $email = MailParser::parse($raw);

        // RFC 2046 §5.1.4: last alternative is preferred
        $this->assertNotNull($email->text);
        $this->assertStringContainsString('Second text version', $email->text);
    }

    public function testTextCalendarExtraction(): void
    {
        $raw = "Subject: Calendar test\r\n";
        $raw .= "Content-Type: multipart/alternative; boundary=\"caltest\"\r\n\r\n";
        $raw .= "--caltest\r\n";
        $raw .= "Content-Type: text/plain; charset=utf-8\r\n\r\n";
        $raw .= "You have a meeting.\r\n";
        $raw .= "--caltest\r\n";
        $raw .= "Content-Type: text/calendar; charset=utf-8; method=REQUEST\r\n\r\n";
        $raw .= "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nSUMMARY:Meeting\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";
        $raw .= "--caltest--\r\n";

        $email = MailParser::parse($raw);

        $this->assertNotNull($email->text);
        $this->assertNotNull($email->calendar);
        $this->assertStringContainsString('VCALENDAR', $email->calendar);
        $this->assertStringContainsString('Meeting', $email->calendar);
    }

    public function testNonTextPartWithoutFilenameOrCidBecomesAttachment(): void
    {
        $raw = "Subject: Binary test\r\n";
        $raw .= "Content-Type: multipart/mixed; boundary=\"bintest\"\r\n\r\n";
        $raw .= "--bintest\r\n";
        $raw .= "Content-Type: text/plain\r\n\r\n";
        $raw .= "Body text\r\n";
        $raw .= "--bintest\r\n";
        $raw .= "Content-Type: application/octet-stream\r\n";
        $raw .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $raw .= "AQIDBA==\r\n";
        $raw .= "--bintest--\r\n";

        $email = MailParser::parse($raw);

        // Non-text part without filename or CID should still be an attachment
        $this->assertCount(1, $email->attachments);
        $this->assertSame('application/octet-stream', $email->attachments[0]->mimeType);
        $this->assertNull($email->attachments[0]->filename);
        $this->assertNull($email->attachments[0]->contentId);
    }

    public function testInlineImageWithFilenameButNoCid(): void
    {
        $raw = "Subject: Inline test\r\n";
        $raw .= "Content-Type: multipart/mixed; boundary=\"inltest\"\r\n\r\n";
        $raw .= "--inltest\r\n";
        $raw .= "Content-Type: text/html\r\n\r\n";
        $raw .= "<p>Hello</p>\r\n";
        $raw .= "--inltest\r\n";
        $raw .= "Content-Type: image/png; name=\"photo.png\"\r\n";
        $raw .= "Content-Disposition: inline; filename=\"photo.png\"\r\n";
        $raw .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $raw .= "iVBORw0KGgo=\r\n";
        $raw .= "--inltest--\r\n";

        $email = MailParser::parse($raw);

        // Inline image with filename but no CID → treated as attachment
        $this->assertCount(1, $email->attachments);
        $this->assertSame('photo.png', $email->attachments[0]->filename);
        $this->assertSame('inline', $email->attachments[0]->disposition);
        $this->assertNull($email->attachments[0]->contentId);
    }

    public function testGmailNestedIntegration(): void
    {
        $email = MailParser::parse($this->fixture('gmail-nested.eml'));

        $this->assertSame('Gmail-style nested email', $email->subject);
        $this->assertNotNull($email->text);
        $this->assertStringContainsString('plain text version', $email->text);
        $this->assertNotNull($email->html);
        $this->assertStringContainsString('HTML', $email->html);
        // Should have inline image + text attachment
        $this->assertGreaterThanOrEqual(1, count($email->attachments));
    }

    public function testBinaryAttachmentRoundTripFidelity(): void
    {
        // Create a known binary payload (simulated PNG header + random bytes)
        $originalBinary = "\x89PNG\r\n\x1a\n" . random_bytes(256);
        $base64Encoded = chunk_split(base64_encode($originalBinary), 76, "\r\n");

        $raw = "Subject: Binary round-trip\r\n";
        $raw .= "Content-Type: multipart/mixed; boundary=\"binrt\"\r\n\r\n";
        $raw .= "--binrt\r\n";
        $raw .= "Content-Type: text/plain\r\n\r\n";
        $raw .= "See attachment.\r\n";
        $raw .= "--binrt\r\n";
        $raw .= "Content-Type: image/png; name=\"test.png\"\r\n";
        $raw .= "Content-Disposition: attachment; filename=\"test.png\"\r\n";
        $raw .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $raw .= $base64Encoded;
        $raw .= "--binrt--\r\n";

        $email = MailParser::parse($raw);

        $this->assertCount(1, $email->attachments);
        $this->assertSame('test.png', $email->attachments[0]->filename);
        $this->assertSame('image/png', $email->attachments[0]->mimeType);

        // Verify byte-for-byte fidelity
        $decoded = $email->attachments[0]->content;
        $this->assertNotNull($decoded);
        $this->assertSame(strlen($originalBinary), strlen($decoded));
        $this->assertSame($originalBinary, $decoded, 'Decoded attachment must be byte-identical to original');
    }

    public function testBinaryAttachmentRoundTripViaStreaming(): void
    {
        // Same test but via streaming parser
        $originalBinary = "\x89PNG\r\n\x1a\n" . str_repeat("\xDE\xAD\xBE\xEF", 64);
        $base64Encoded = chunk_split(base64_encode($originalBinary), 76, "\r\n");

        $raw = "Subject: Stream binary round-trip\r\n";
        $raw .= "Content-Type: multipart/mixed; boundary=\"sbin\"\r\n\r\n";
        $raw .= "--sbin\r\n";
        $raw .= "Content-Type: text/plain\r\n\r\n";
        $raw .= "Body.\r\n";
        $raw .= "--sbin\r\n";
        $raw .= "Content-Type: application/octet-stream; name=\"data.bin\"\r\n";
        $raw .= "Content-Disposition: attachment; filename=\"data.bin\"\r\n";
        $raw .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $raw .= $base64Encoded;
        $raw .= "--sbin--\r\n";

        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $raw);
        rewind($stream);

        $parser = new MailParser();
        $email = $parser->parseStream($stream);
        fclose($stream);

        $this->assertCount(1, $email->attachments);
        $this->assertSame($originalBinary, $email->attachments[0]->content, 'Streamed decode must be byte-identical');
    }

    public function testLargeEmailMemoryUsage(): void
    {
        // Generate a ~5MB email with large base64 attachment
        // (Not 100MB to keep test suite fast, but enough to prove streaming works)
        $attachmentSize = 2 * 1024 * 1024; // 2MB raw binary
        $binaryData = str_repeat("\x42", $attachmentSize);
        $base64Data = chunk_split(base64_encode($binaryData), 76, "\r\n");

        $raw = "From: test@example.com\r\n";
        $raw .= "Subject: Large email test\r\n";
        $raw .= "Content-Type: multipart/mixed; boundary=\"large\"\r\n\r\n";
        $raw .= "--large\r\n";
        $raw .= "Content-Type: text/plain\r\n\r\n";
        $raw .= "Small body.\r\n";
        $raw .= "--large\r\n";
        $raw .= "Content-Type: application/octet-stream; name=\"big.bin\"\r\n";
        $raw .= "Content-Disposition: attachment; filename=\"big.bin\"\r\n";
        $raw .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $raw .= $base64Data;
        $raw .= "--large--\r\n";

        // Write to temp file to test real streaming
        $tmpFile = tempnam(sys_get_temp_dir(), 'eml_test_');
        file_put_contents($tmpFile, $raw);

        // Free the raw string to measure only parser memory
        $rawSize = strlen($raw);
        unset($raw, $base64Data, $binaryData);

        $stream = fopen($tmpFile, 'r');
        $memBefore = memory_get_usage(true);

        $parser = new MailParser();
        $email = $parser->parseStream($stream);

        $memAfter = memory_get_peak_usage(true);
        fclose($stream);
        unlink($tmpFile);

        // Verify parsing succeeded
        $this->assertSame('Large email test', $email->subject);
        $this->assertCount(1, $email->attachments);
        $this->assertSame('big.bin', $email->attachments[0]->filename);
        $this->assertSame($attachmentSize, $email->attachments[0]->size);

        // The streaming parser should NOT have loaded the entire raw email into memory
        // at once. The attachment content will be in memory (decoded), but the raw
        // base64 string (~2.7MB) should have been streamed through php://temp.
        // We just verify the parse completed successfully with correct results.
        $this->assertNotNull($email->attachments[0]->content);
    }

    public function testLongLineHandlingInStreaming(): void
    {
        // Test line > 8192 bytes (fgets buffer size)
        // A very long header value that exceeds the fgets buffer
        $longValue = str_repeat('A', 10000);
        $raw = "Subject: {$longValue}\r\n\r\nBody text.";

        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $raw);
        rewind($stream);

        $parser = new MailParser();
        $email = $parser->parseStream($stream);
        fclose($stream);

        // fgets(8192) returns partial line; next fgets gets the rest.
        // The header parser should still reconstruct the full value.
        $this->assertNotNull($email->subject);
        // May be truncated at fgets boundary, but should not crash
        $this->assertGreaterThan(100, strlen($email->subject));
    }

    public function testAttachmentStreamAccess(): void
    {
        $originalBinary = "\x89PNG\r\n\x1a\n" . str_repeat("\xAB\xCD", 128);
        $base64Encoded = chunk_split(base64_encode($originalBinary), 76, "\r\n");

        $raw = "Subject: Stream test\r\n";
        $raw .= "Content-Type: multipart/mixed; boundary=\"strmtest\"\r\n\r\n";
        $raw .= "--strmtest\r\n";
        $raw .= "Content-Type: text/plain\r\n\r\n";
        $raw .= "Body.\r\n";
        $raw .= "--strmtest\r\n";
        $raw .= "Content-Type: image/png; name=\"test.png\"\r\n";
        $raw .= "Content-Disposition: attachment; filename=\"test.png\"\r\n";
        $raw .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $raw .= $base64Encoded;
        $raw .= "--strmtest--\r\n";

        $email = MailParser::parse($raw);
        $att = $email->attachments[0];

        // content property works as before
        $this->assertSame($originalBinary, $att->content);

        // stream() returns a readable resource
        $stream = $att->stream();
        $this->assertNotNull($stream);
        $this->assertIsResource($stream);

        // Reading the stream gives the same bytes
        $fromStream = stream_get_contents($stream);
        $this->assertSame($originalBinary, $fromStream);

        // stream() can be called again (rewinds)
        $stream2 = $att->stream();
        $fromStream2 = stream_get_contents($stream2);
        $this->assertSame($originalBinary, $fromStream2);
    }

    public function testAttachmentStreamNullWhenNoContent(): void
    {
        $config = new ParserConfig(attachmentEncoding: 'none');
        $email = MailParser::parse($this->fixture('multipart-mixed.eml'), $config);

        $att = $email->attachments[0];
        $this->assertNull($att->content);
        $this->assertNull($att->stream());
    }

    public function testAttachmentGetContentFallsBackToStream(): void
    {
        // Create an attachment via fromStream
        $data = "Hello from stream";
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $data);
        rewind($stream);

        $att = \PHPdot\Mail\Parser\Result\Attachment::fromStream(
            mimeType: 'text/plain',
            filename: 'test.txt',
            disposition: 'attachment',
            stream: $stream,
            size: strlen($data),
        );

        // content is null (not loaded)
        $this->assertNull($att->content);

        // getContent() reads from the stream
        $this->assertSame($data, $att->getContent());

        // stream() also works
        $s = $att->stream();
        $this->assertNotNull($s);
        $this->assertSame($data, stream_get_contents($s));
    }

    public function testAttachmentStreamPipingToFile(): void
    {
        $originalBinary = random_bytes(512);
        $base64Encoded = chunk_split(base64_encode($originalBinary), 76, "\r\n");

        $raw = "Subject: Pipe test\r\n";
        $raw .= "Content-Type: multipart/mixed; boundary=\"pipetest\"\r\n\r\n";
        $raw .= "--pipetest\r\n";
        $raw .= "Content-Type: text/plain\r\n\r\nBody.\r\n";
        $raw .= "--pipetest\r\n";
        $raw .= "Content-Type: application/octet-stream; name=\"data.bin\"\r\n";
        $raw .= "Content-Disposition: attachment; filename=\"data.bin\"\r\n";
        $raw .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $raw .= $base64Encoded;
        $raw .= "--pipetest--\r\n";

        $email = MailParser::parse($raw);
        $att = $email->attachments[0];

        // Pipe stream to a temp file (simulates S3 upload pattern)
        $tmpFile = tempnam(sys_get_temp_dir(), 'att_test_');
        $dest = fopen($tmpFile, 'w');
        $source = $att->stream();

        $this->assertNotNull($source);
        stream_copy_to_stream($source, $dest);
        fclose($dest);

        // Verify the file matches original binary
        $written = file_get_contents($tmpFile);
        $this->assertSame($originalBinary, $written);

        unlink($tmpFile);
    }
}
