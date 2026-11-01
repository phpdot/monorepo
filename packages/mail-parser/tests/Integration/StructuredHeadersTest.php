<?php

/**
 * Integration tests for structured header parsing including Received hops, DKIM, SPF, and Authentication-Results.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

declare(strict_types=1);

namespace PHPdot\Mail\Parser\Tests\Integration;

use PHPdot\Mail\Parser\MailParser;
use PHPdot\Mail\Parser\Result\AuthResult;
use PHPdot\Mail\Parser\Result\DkimSignature;
use PHPdot\Mail\Parser\Result\ReceivedHop;
use PHPdot\Mail\Parser\Result\SpfResult;
use PHPUnit\Framework\TestCase;

final class StructuredHeadersTest extends TestCase
{
    private function fixture(string $name): string
    {
        return file_get_contents(__DIR__ . '/../Fixtures/' . $name);
    }

    public function testReceivedHopsParsed(): void
    {
        $email = MailParser::parse($this->fixture('auth-headers.eml'));

        $this->assertCount(2, $email->received);
        $this->assertInstanceOf(ReceivedHop::class, $email->received[0]);
        $this->assertInstanceOf(ReceivedHop::class, $email->received[1]);
    }

    public function testFirstReceivedHopDetails(): void
    {
        $email = MailParser::parse($this->fixture('auth-headers.eml'));

        $hop = $email->received[0];
        $this->assertSame('mail-sor-f41.google.com', $hop->from);
        $this->assertSame('209.85.220.41', $hop->fromIp);
        $this->assertSame('mx.example.com', $hop->by);
        $this->assertSame('ESMTPS', $hop->with);
        $this->assertSame('recipient@example.com', $hop->for);
        $this->assertNotNull($hop->date);
        $this->assertSame('2024-01-15', $hop->date->format('Y-m-d'));
        $this->assertNotEmpty($hop->raw);
    }

    public function testSecondReceivedHopDetails(): void
    {
        $email = MailParser::parse($this->fixture('auth-headers.eml'));

        $hop = $email->received[1];
        $this->assertSame('sender-server.example.com', $hop->from);
        $this->assertSame('192.168.1.100', $hop->fromIp);
        $this->assertSame('mail-sor-f41.google.com', $hop->by);
        $this->assertSame('SMTP', $hop->with);
    }

    public function testDkimSignatureParsed(): void
    {
        $email = MailParser::parse($this->fixture('auth-headers.eml'));

        $this->assertCount(1, $email->dkim);
        $this->assertInstanceOf(DkimSignature::class, $email->dkim[0]);

        $dkim = $email->dkim[0];
        $this->assertSame('1', $dkim->version);
        $this->assertSame('rsa-sha256', $dkim->algorithm);
        $this->assertSame('example.com', $dkim->domain);
        $this->assertSame('selector2024', $dkim->selector);
        $this->assertSame('relaxed/relaxed', $dkim->canonicalization);
        $this->assertSame('from:to:subject:date:message-id', $dkim->headers);
        $this->assertNotNull($dkim->signature);
        $this->assertNotNull($dkim->bodyHash);
        $this->assertNotEmpty($dkim->raw);
    }

    public function testDkimSignatureWhitespaceStripped(): void
    {
        $email = MailParser::parse($this->fixture('auth-headers.eml'));

        $dkim = $email->dkim[0];
        // b= and bh= should have whitespace stripped (base64 folding)
        $this->assertStringNotContainsString(' ', $dkim->signature ?? '');
        $this->assertStringNotContainsString("\n", $dkim->signature ?? '');
        $this->assertStringNotContainsString(' ', $dkim->bodyHash ?? '');
    }

    public function testSpfResultParsed(): void
    {
        $email = MailParser::parse($this->fixture('auth-headers.eml'));

        $this->assertNotNull($email->spf);
        $this->assertInstanceOf(SpfResult::class, $email->spf);
        $this->assertSame('pass', $email->spf->result);
        $this->assertSame('209.85.220.41', $email->spf->ip);
        $this->assertSame('example.com', $email->spf->domain);
        $this->assertNotEmpty($email->spf->raw);
    }

    public function testAuthenticationResultsParsed(): void
    {
        $email = MailParser::parse($this->fixture('auth-headers.eml'));

        $this->assertCount(1, $email->authResults);
        $this->assertInstanceOf(AuthResult::class, $email->authResults[0]);

        $auth = $email->authResults[0];
        $this->assertSame('mx.example.com', $auth->server);
        $this->assertSame('pass', $auth->dkim);
        $this->assertSame('example.com', $auth->dkimDomain);
        $this->assertSame('pass', $auth->spf);
        $this->assertSame('sender@example.com', $auth->spfDomain);
        $this->assertSame('pass', $auth->dmarc);
        $this->assertSame('example.com', $auth->dmarcDomain);
        $this->assertNotEmpty($auth->raw);
    }

    public function testNoAuthHeadersReturnsEmptyDefaults(): void
    {
        $email = MailParser::parse($this->fixture('simple-text.eml'));

        $this->assertSame([], $email->dkim);
        $this->assertNull($email->spf);
        $this->assertSame([], $email->authResults);
    }

    public function testReceivedHopsFromManyReceivedFixture(): void
    {
        $email = MailParser::parse($this->fixture('many-received-headers.eml'));

        $this->assertGreaterThanOrEqual(50, count($email->received));
        $this->assertInstanceOf(ReceivedHop::class, $email->received[0]);
        $this->assertNotEmpty($email->received[0]->raw);
    }

    public function testHeaderConvenienceMethods(): void
    {
        $email = MailParser::parse($this->fixture('auth-headers.eml'));

        // header() returns single value
        $this->assertSame('Email with authentication headers', $email->header('Subject'));
        $this->assertNull($email->header('X-NonExistent'));

        // hasHeader()
        $this->assertTrue($email->hasHeader('DKIM-Signature'));
        $this->assertTrue($email->hasHeader('Received-SPF'));
        $this->assertFalse($email->hasHeader('X-NonExistent'));

        // headerAll() for multi-value
        $received = $email->headerAll('Received');
        $this->assertCount(2, $received);
        $this->assertIsString($received[0]);
        $this->assertIsString($received[1]);
    }

    public function testReferencesParseAsArray(): void
    {
        $raw = "Subject: Thread test\r\n";
        $raw .= "Message-ID: <msg3@example.com>\r\n";
        $raw .= "In-Reply-To: <msg2@example.com>\r\n";
        $raw .= "References: <msg1@example.com> <msg2@example.com>\r\n";
        $raw .= "\r\nBody.";

        $email = MailParser::parse($raw);

        $this->assertSame('msg2@example.com', $email->inReplyTo);
        $this->assertCount(2, $email->references);
        $this->assertSame('msg1@example.com', $email->references[0]);
        $this->assertSame('msg2@example.com', $email->references[1]);
    }

    public function testEmptyReferencesIsEmptyArray(): void
    {
        $email = MailParser::parse($this->fixture('simple-text.eml'));

        $this->assertSame([], $email->references);
    }

    public function testStreamingParityForAuthHeaders(): void
    {
        $path = __DIR__ . '/../Fixtures/auth-headers.eml';
        $raw = file_get_contents($path);

        $stringEmail = MailParser::parse($raw);

        $stream = fopen($path, 'r');
        $parser = new MailParser();
        $streamEmail = $parser->parseStream($stream);
        fclose($stream);

        // Verify structured headers match
        $this->assertCount(count($stringEmail->received), $streamEmail->received);
        $this->assertCount(count($stringEmail->dkim), $streamEmail->dkim);

        if ($stringEmail->spf !== null) {
            $this->assertNotNull($streamEmail->spf);
            $this->assertSame($stringEmail->spf->result, $streamEmail->spf->result);
            $this->assertSame($stringEmail->spf->ip, $streamEmail->spf->ip);
        }

        $this->assertCount(count($stringEmail->authResults), $streamEmail->authResults);
        if (count($stringEmail->authResults) > 0) {
            $this->assertSame(
                $stringEmail->authResults[0]->dkim,
                $streamEmail->authResults[0]->dkim,
            );
        }
    }
}
