<?php

/**
 * Integration tests for resilient parsing of malformed, non-standard, and edge-case email messages.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

declare(strict_types=1);

namespace PHPdot\Mail\Parser\Tests\Integration;

use PHPdot\Mail\Parser\MailParser;
use PHPUnit\Framework\TestCase;

final class MalformedEmailTest extends TestCase
{
    private function fixture(string $name): string
    {
        return file_get_contents(__DIR__ . '/../Fixtures/' . $name);
    }

    public function testGmailNestedMultipart(): void
    {
        $email = MailParser::parse($this->fixture('gmail-nested.eml'));

        $this->assertSame('Gmail-style nested email', $email->subject);
        $this->assertNotNull($email->text);
        $this->assertStringContainsString('plain text version from Gmail', $email->text);
        $this->assertNotNull($email->html);
        $this->assertStringContainsString('cid:img1', $email->html);

        // Should have inline image + attachment
        $this->assertCount(2, $email->attachments);

        $filenames = array_map(fn($a) => $a->filename, iterator_to_array($email->attachments));
        $this->assertContains('pixel.gif', $filenames);
        $this->assertContains('notes.txt', $filenames);

        // Check dispositions
        $inlineCount = 0;
        $attachmentCount = 0;
        foreach ($email->attachments as $att) {
            if ($att->disposition === 'inline') {
                $inlineCount++;
            } elseif ($att->disposition === 'attachment') {
                $attachmentCount++;
            }
        }
        $this->assertSame(1, $inlineCount, 'Expected 1 inline attachment');
        $this->assertSame(1, $attachmentCount, 'Expected 1 regular attachment');
    }

    public function testOutlookQuirks(): void
    {
        $email = MailParser::parse($this->fixture('outlook-quirks.eml'));

        $this->assertSame('Outlook quirks test', $email->subject);
        $this->assertNotNull($email->text);
        $this->assertStringContainsString('Outlook email body with quirks', $email->text);
        $this->assertCount(1, $email->attachments);
        $this->assertSame('file.dat', $email->attachments[0]->filename);
    }

    public function testWrongCharsetLabel(): void
    {
        $email = MailParser::parse($this->fixture('wrong-charset.eml'));

        $this->assertSame('Wrong charset label', $email->subject);
        $this->assertNotNull($email->text);
        // QP decodes =93 and =94 to bytes; body should contain something
        $this->assertStringContainsString('smart quotes', $email->text);
    }

    public function testPathTraversalFilename(): void
    {
        $email = MailParser::parse($this->fixture('path-traversal-filename.eml'));

        $this->assertSame('Path traversal test', $email->subject);
        $this->assertNotNull($email->text);
        $this->assertStringContainsString('See attachment', $email->text);
        $this->assertCount(1, $email->attachments);
        // The parser keeps only the final path segment; a filename is display data
        $this->assertSame('passwd', $email->attachments[0]->filename);
    }

    public function testManyReceivedHeaders(): void
    {
        $email = MailParser::parse($this->fixture('many-received-headers.eml'));

        $this->assertSame('Many received headers', $email->subject);
        $this->assertNotNull($email->text);
        $this->assertStringContainsString('Body after many headers', $email->text);

        // Should have 50+ Received headers
        $receivedCount = count($email->received);
        $this->assertGreaterThanOrEqual(50, $receivedCount);
    }

    public function testBare8BitHeaders(): void
    {
        $email = MailParser::parse($this->fixture('bare-8bit-headers.eml'));

        $this->assertNotNull($email->from);
        $this->assertStringContainsString('Jos', $email->from->name);
        $this->assertSame('jose@example.com', $email->from->address);
        $this->assertNotNull($email->text);
        $this->assertStringContainsString('Body with normal UTF-8 content', $email->text);
    }

    public function testMixedLineEndings(): void
    {
        // Construct email with mixed \r\n and \n line endings
        $raw = "From: test@example.com\r\n"
            . "Subject: Mixed endings\n"
            . "Date: Thu, 13 Feb 2020 15:47:25 +0000\r\n"
            . "Content-Type: text/plain\n"
            . "\r\n"
            . "Body line 1\r\n"
            . "Body line 2\n"
            . "Body line 3\r\n";

        $email = MailParser::parse($raw);

        $this->assertSame('Mixed endings', $email->subject);
        $this->assertNotNull($email->text);
        $this->assertStringContainsString('Body line 1', $email->text);
        $this->assertStringContainsString('Body line 2', $email->text);
        $this->assertStringContainsString('Body line 3', $email->text);
    }

    public function testNoContentTypeMultipart(): void
    {
        // Email with no Content-Type header — should default to text/plain
        $raw = "From: test@example.com\r\n"
            . "Subject: No content type\r\n"
            . "\r\n"
            . "This is a body without Content-Type header.\r\n";

        $email = MailParser::parse($raw);

        $this->assertSame('No content type', $email->subject);
        $this->assertNotNull($email->text);
        $this->assertStringContainsString('body without Content-Type', $email->text);
    }

    public function testTruncatedEmail(): void
    {
        // Email that ends abruptly mid-body
        $raw = "From: test@example.com\r\n"
            . "Subject: Truncated\r\n"
            . "Content-Type: multipart/mixed; boundary=\"trunc\"\r\n"
            . "\r\n"
            . "--trunc\r\n"
            . "Content-Type: text/plain\r\n"
            . "\r\n"
            . "This is some text that gets cut off mid-sen";

        // Should not crash
        $email = MailParser::parse($raw);

        $this->assertSame('Truncated', $email->subject);
        $this->assertNotNull($email->text);
        $this->assertStringContainsString('cut off', $email->text);
    }

    public function testEmptyBody(): void
    {
        // Headers only, no body
        $raw = "From: test@example.com\r\n"
            . "Subject: Empty body\r\n";

        $email = MailParser::parse($raw);

        $this->assertSame('Empty body', $email->subject);
        // Text should be null or empty
        $this->assertTrue($email->text === null || $email->text === '');
    }

    public function testNullBytesInBody(): void
    {
        // Body containing \x00 bytes
        $raw = "From: test@example.com\r\n"
            . "Subject: Null bytes\r\n"
            . "Content-Type: text/plain\r\n"
            . "\r\n"
            . "Before null\x00After null\r\n";

        // Should parse without crashing
        $email = MailParser::parse($raw);

        $this->assertSame('Null bytes', $email->subject);
        $this->assertNotNull($email->text);
        $this->assertStringContainsString('Before null', $email->text);
    }
}
