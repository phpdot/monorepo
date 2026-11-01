<?php

/**
 * Security tests verifying enforcement of nesting depth, header size, and message size limits.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

declare(strict_types=1);

namespace PHPdot\Mail\Parser\Tests\Security;

use PHPdot\Mail\Parser\Exception\SecurityLimitException;
use PHPdot\Mail\Parser\MailParser;
use PHPdot\Mail\Parser\ParserConfig;
use PHPUnit\Framework\TestCase;

final class SecurityLimitsTest extends TestCase
{
    public function testMessageSizeLimitStrict(): void
    {
        $config = new ParserConfig(strict: true, maxMessageSize: 100);
        $raw = "Subject: Test\r\n\r\n" . str_repeat('A', 200);

        $this->expectException(SecurityLimitException::class);
        MailParser::parse($raw, $config);
    }

    public function testMessageSizeLimitLenient(): void
    {
        $config = new ParserConfig(strict: false, maxMessageSize: 100);
        $raw = "Subject: Test\r\n\r\n" . str_repeat('A', 200);

        // Should not throw - truncates instead
        $email = MailParser::parse($raw, $config);
        $this->assertSame('Test', $email->subject);
    }

    public function testHeaderSizeLimitStrict(): void
    {
        $config = new ParserConfig(strict: true, maxHeaderSize: 50);
        $raw = "Subject: " . str_repeat('A', 100) . "\r\n\r\nBody";

        $this->expectException(SecurityLimitException::class);
        MailParser::parse($raw, $config);
    }

    public function testNestingDepthLimitStrict(): void
    {
        $config = new ParserConfig(strict: true, maxNestingDepth: 2);

        // Build deeply nested multipart
        $raw = "Content-Type: multipart/mixed; boundary=\"b1\"\r\n\r\n";
        $raw .= "--b1\r\n";
        $raw .= "Content-Type: multipart/mixed; boundary=\"b2\"\r\n\r\n";
        $raw .= "--b2\r\n";
        $raw .= "Content-Type: multipart/mixed; boundary=\"b3\"\r\n\r\n";
        $raw .= "--b3\r\n";
        $raw .= "Content-Type: text/plain\r\n\r\nDeep content\r\n";
        $raw .= "--b3--\r\n";
        $raw .= "--b2--\r\n";
        $raw .= "--b1--\r\n";

        $this->expectException(SecurityLimitException::class);
        MailParser::parse($raw, $config);
    }

    public function testNestingDepthLimitLenient(): void
    {
        $config = new ParserConfig(strict: false, maxNestingDepth: 2);

        $raw = "Content-Type: multipart/mixed; boundary=\"b1\"\r\n\r\n";
        $raw .= "--b1\r\n";
        $raw .= "Content-Type: multipart/mixed; boundary=\"b2\"\r\n\r\n";
        $raw .= "--b2\r\n";
        $raw .= "Content-Type: multipart/mixed; boundary=\"b3\"\r\n\r\n";
        $raw .= "--b3\r\n";
        $raw .= "Content-Type: text/plain\r\n\r\nDeep content\r\n";
        $raw .= "--b3--\r\n";
        $raw .= "--b2--\r\n";
        $raw .= "--b1--\r\n";

        // Should not throw in lenient mode
        $email = MailParser::parse($raw, $config);
        $this->assertNotNull($email);
    }

    public function testDefaultConfigParsesSafely(): void
    {
        $raw = "Subject: Test\r\n\r\nSimple body";
        $email = MailParser::parse($raw);
        $this->assertSame('Test', $email->subject);
    }

    public function testLargeNumberOfHeaders(): void
    {
        $headers = '';
        for ($i = 0; $i < 1000; $i++) {
            $headers .= "X-Custom-{$i}: value-{$i}\r\n";
        }
        $raw = $headers . "\r\nBody";

        // Should parse without hanging
        $email = MailParser::parse($raw);
        $this->assertNotNull($email->text);
    }

    public function testBoundaryGuessingIsLinear(): void
    {
        // A multipart body that names no boundary, every line a distinct delimiter-shaped token: guessed by counting
        // each candidate over the whole body, this took seconds; counted in one pass it takes next to nothing.
        $body = '';

        for ($i = 0; $i < 20000; $i++) {
            $body .= sprintf("--%06d\r\n", $i);
        }

        $raw = "Subject: Test\r\nMIME-Version: 1.0\r\nContent-Type: multipart/mixed\r\n\r\n" . $body;
        $started = microtime(true);
        $email = MailParser::parse($raw);

        $this->assertSame('Test', $email->subject);
        $this->assertLessThan(1.0, microtime(true) - $started);
    }

    public function testBoundaryIsGuessedFromRepeatedDelimiters(): void
    {
        $raw = "Subject: Guess\r\nMIME-Version: 1.0\r\nContent-Type: multipart/mixed\r\n\r\n"
            . "--edge\r\nContent-Type: text/plain\r\n\r\nfirst\r\n--edge\r\nContent-Type: text/plain\r\n\r\nsecond\r\n--edge--\r\n";
        $email = MailParser::parse($raw);

        $this->assertStringContainsString('first', (string) $email->text);
    }

    public function testEncodedDisplayNameNeverBecomesAnAddress(): void
    {
        // An encoded word is a phrase token: decoded only after the list is split, it stays the name it spells.
        $email = MailParser::parse("From: =?UTF-8?Q?Bank_=3Cceo=40bank.example=3E=2C?= <a@attacker.example>\r\nSubject: t\r\n\r\nx");

        $this->assertSame('a@attacker.example', $email->from?->address);
        $this->assertSame('Bank <ceo@bank.example>,', $email->from?->name);
    }

    public function testEncodedDisplayNameWithSpecialsKeepsItsSender(): void
    {
        $email = MailParser::parse("From: =?UTF-8?q?S=C3=A9bastien_Almiron_=5B_Netim_=5D?=\t<newsletter@netim.com>\r\nSubject: t\r\n\r\nx");

        $this->assertSame('newsletter@netim.com', $email->from?->address);
        $this->assertSame('Sébastien Almiron [ Netim ]', $email->from?->name);
    }
}
