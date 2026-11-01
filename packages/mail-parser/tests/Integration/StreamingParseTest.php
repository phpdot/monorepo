<?php

/**
 * Streaming parser parity tests verifying string and stream parsing produce identical results for all fixtures.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

declare(strict_types=1);

namespace PHPdot\Mail\Parser\Tests\Integration;

use PHPdot\Mail\Parser\MailParser;
use PHPdot\Mail\Parser\ParserConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Parity tests: verify streaming parser produces identical results to string parser.
 */
final class StreamingParseTest extends TestCase
{
    #[DataProvider('fixtureProvider')]
    public function testStreamingParityWithStringParse(string $fixture): void
    {
        $path = __DIR__ . '/../Fixtures/' . $fixture;
        $raw = file_get_contents($path);

        // String-based parse
        $stringEmail = MailParser::parse($raw);

        // Stream-based parse
        $stream = fopen($path, 'r');
        $this->assertNotFalse($stream);
        $parser = new MailParser();
        $streamEmail = $parser->parseStream($stream);
        fclose($stream);

        // Compare all fields
        $this->assertSame($stringEmail->subject, $streamEmail->subject, "Subject mismatch for {$fixture}");
        $this->assertSame($stringEmail->messageId, $streamEmail->messageId, "Message-ID mismatch for {$fixture}");
        $this->assertSame($stringEmail->inReplyTo, $streamEmail->inReplyTo, "In-Reply-To mismatch for {$fixture}");

        // Compare from address
        $this->assertSame(
            $stringEmail->from === null,
            $streamEmail->from === null,
            "From null mismatch for {$fixture}",
        );
        if ($stringEmail->from !== null) {
            $this->assertSame(
                (string) $stringEmail->from,
                (string) $streamEmail->from,
                "From mismatch for {$fixture}",
            );
        }

        // Compare to addresses
        $this->assertSame(
            count($stringEmail->to),
            count($streamEmail->to),
            "To count mismatch for {$fixture}",
        );

        // Compare text body
        $this->assertSame($stringEmail->text, $streamEmail->text, "Text body mismatch for {$fixture}");

        // Compare HTML body
        $this->assertSame($stringEmail->html, $streamEmail->html, "HTML body mismatch for {$fixture}");

        // Compare calendar
        $this->assertSame($stringEmail->calendar, $streamEmail->calendar, "Calendar mismatch for {$fixture}");

        // Compare attachment count
        $this->assertSame(
            count($stringEmail->attachments),
            count($streamEmail->attachments),
            "Attachment count mismatch for {$fixture}",
        );

        // Compare attachment metadata
        for ($i = 0; $i < count($stringEmail->attachments); $i++) {
            $sAtt = $stringEmail->attachments[$i];
            $tAtt = $streamEmail->attachments[$i];
            $this->assertSame($sAtt->filename, $tAtt->filename, "Attachment[$i] filename mismatch for {$fixture}");
            $this->assertSame($sAtt->mimeType, $tAtt->mimeType, "Attachment[$i] mimeType mismatch for {$fixture}");
            $this->assertSame($sAtt->disposition, $tAtt->disposition, "Attachment[$i] disposition mismatch for {$fixture}");
            $this->assertSame($sAtt->contentId, $tAtt->contentId, "Attachment[$i] contentId mismatch for {$fixture}");
            $this->assertSame($sAtt->size, $tAtt->size, "Attachment[$i] size mismatch for {$fixture}");
            $this->assertSame($sAtt->content, $tAtt->content, "Attachment[$i] content mismatch for {$fixture}");
        }
    }

    public static function fixtureProvider(): array
    {
        return [
            'simple-text' => ['simple-text.eml'],
            'multipart-alternative' => ['multipart-alternative.eml'],
            'multipart-mixed' => ['multipart-mixed.eml'],
            'multipart-related' => ['multipart-related.eml'],
            'encoded-words' => ['encoded-words.eml'],
            'nested-multipart' => ['nested-multipart.eml'],
            'rfc2231-continuations' => ['rfc2231-continuations.eml'],
            'format-flowed' => ['format-flowed.eml'],
            'quoted-printable' => ['quoted-printable.eml'],
            'missing-headers' => ['missing-headers.eml'],
            'gmail-nested' => ['gmail-nested.eml'],
            'outlook-quirks' => ['outlook-quirks.eml'],
            'wrong-charset' => ['wrong-charset.eml'],
            'path-traversal-filename' => ['path-traversal-filename.eml'],
            'many-received-headers' => ['many-received-headers.eml'],
            'bare-8bit-headers' => ['bare-8bit-headers.eml'],
            'rfc822-nested' => ['rfc822-nested.eml'],
            'auth-headers' => ['auth-headers.eml'],
        ];
    }

    public function testStreamingSecurityNestingDepth(): void
    {
        $config = new ParserConfig(strict: true, maxNestingDepth: 2);

        $raw = "Content-Type: multipart/mixed; boundary=\"b1\"\r\n\r\n";
        $raw .= "--b1\r\n";
        $raw .= "Content-Type: multipart/mixed; boundary=\"b2\"\r\n\r\n";
        $raw .= "--b2\r\n";
        $raw .= "Content-Type: multipart/mixed; boundary=\"b3\"\r\n\r\n";
        $raw .= "--b3\r\n";
        $raw .= "Content-Type: text/plain\r\n\r\nDeep\r\n";
        $raw .= "--b3--\r\n--b2--\r\n--b1--\r\n";

        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $raw);
        rewind($stream);

        $this->expectException(\PHPdot\Mail\Parser\Exception\SecurityLimitException::class);
        $parser = new MailParser($config);
        $parser->parseStream($stream);
        fclose($stream);
    }

    public function testStreamingSecurityMessageSize(): void
    {
        $config = new ParserConfig(strict: true, maxMessageSize: 100);

        $raw = "Subject: Test\r\n\r\n" . str_repeat('A', 200);

        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $raw);
        rewind($stream);

        $this->expectException(\PHPdot\Mail\Parser\Exception\SecurityLimitException::class);
        $parser = new MailParser($config);
        $parser->parseStream($stream);
        fclose($stream);
    }

    public function testStreamingEmptyStream(): void
    {
        $stream = fopen('php://memory', 'r+');

        $parser = new MailParser();
        $email = $parser->parseStream($stream);
        fclose($stream);

        $this->assertNull($email->subject);
        $this->assertNull($email->from);
    }

    public function testStreamingMemoryBaseline(): void
    {
        // Generate a moderate email with base64 attachment
        $body = str_repeat("Line of text content for testing.\r\n", 100);
        $attachment = base64_encode(str_repeat("\x00\x01\x02\x03", 10000));
        $attachmentLines = chunk_split($attachment, 76, "\r\n");

        $raw = "From: test@example.com\r\n";
        $raw .= "Subject: Memory test\r\n";
        $raw .= "Content-Type: multipart/mixed; boundary=\"memtest\"\r\n\r\n";
        $raw .= "--memtest\r\n";
        $raw .= "Content-Type: text/plain\r\n\r\n";
        $raw .= $body;
        $raw .= "\r\n--memtest\r\n";
        $raw .= "Content-Type: application/octet-stream; name=\"test.bin\"\r\n";
        $raw .= "Content-Disposition: attachment; filename=\"test.bin\"\r\n";
        $raw .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $raw .= $attachmentLines;
        $raw .= "--memtest--\r\n";

        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $raw);
        rewind($stream);

        $parser = new MailParser();
        $email = $parser->parseStream($stream);
        fclose($stream);

        $this->assertSame('Memory test', $email->subject);
        $this->assertNotNull($email->text);
        $this->assertCount(1, $email->attachments);
        $this->assertSame('test.bin', $email->attachments[0]->filename);
        $this->assertGreaterThan(0, $email->attachments[0]->size);
    }
}
