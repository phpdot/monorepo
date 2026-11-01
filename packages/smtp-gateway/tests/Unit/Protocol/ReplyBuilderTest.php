<?php

declare(strict_types=1);

namespace PHPdot\Mail\Gateway\Tests\Unit\Protocol;

use PHPdot\Mail\Gateway\DataType\Enum\ReplyCode;
use PHPdot\Mail\Gateway\Protocol\ReplyBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ReplyBuilderTest extends TestCase
{
    private ReplyBuilder $rb;

    protected function setUp(): void
    {
        $this->rb = new ReplyBuilder('mail.example.com');
    }

    #[Test]
    public function singleLineReply(): void
    {
        $reply = $this->rb->reply(ReplyCode::Ok, 'OK');
        self::assertSame("250 OK\r\n", $reply);
    }

    #[Test]
    public function multiLineReply(): void
    {
        $reply = $this->rb->multiLine(ReplyCode::Ok, ['Hello', 'SIZE 25000000', 'STARTTLS']);
        self::assertSame("250-Hello\r\n250-SIZE 25000000\r\n250 STARTTLS\r\n", $reply);
    }

    #[Test]
    public function greeting(): void
    {
        $reply = $this->rb->greeting();
        self::assertStringStartsWith('220 mail.example.com ESMTP', $reply);
        self::assertStringEndsWith("\r\n", $reply);
    }

    #[Test]
    public function ehloResponse(): void
    {
        $reply = $this->rb->ehlo(['STARTTLS', 'AUTH PLAIN LOGIN', 'SIZE 25000000', '8BITMIME']);
        self::assertStringStartsWith('250-mail.example.com Hello', $reply);
        self::assertStringContainsString("250-STARTTLS\r\n", $reply);
        self::assertStringContainsString("250-AUTH PLAIN LOGIN\r\n", $reply);
        self::assertStringContainsString("250 8BITMIME\r\n", $reply);
    }

    #[Test]
    public function readyForTls(): void
    {
        self::assertSame("220 Ready to start TLS\r\n", $this->rb->readyForTls());
    }

    #[Test]
    public function authSuccess(): void
    {
        self::assertSame("235 Authentication successful\r\n", $this->rb->authSuccess());
    }

    #[Test]
    public function authChallenge(): void
    {
        $reply = $this->rb->authChallenge(base64_encode('Username:'));
        self::assertStringStartsWith('334 ', $reply);
    }

    #[Test]
    public function startData(): void
    {
        $reply = $this->rb->startData();
        self::assertStringStartsWith('354 ', $reply);
    }

    #[Test]
    public function bye(): void
    {
        $reply = $this->rb->bye();
        self::assertStringStartsWith('221 ', $reply);
    }

    #[Test]
    public function syntaxError(): void
    {
        $reply = $this->rb->syntaxError();
        self::assertStringStartsWith('500 ', $reply);
    }

    #[Test]
    public function badSequence(): void
    {
        $reply = $this->rb->badSequence();
        self::assertStringStartsWith('503 ', $reply);
    }

    #[Test]
    public function authRequired(): void
    {
        $reply = $this->rb->authRequired();
        self::assertStringStartsWith('530 ', $reply);
    }

    #[Test]
    public function messageTooLarge(): void
    {
        $reply = $this->rb->messageTooLarge(25000000);
        self::assertStringStartsWith('552 ', $reply);
        self::assertStringContainsString('25000000', $reply);
    }

    #[Test]
    public function queued(): void
    {
        $reply = $this->rb->queued('abc123');
        self::assertSame("250 OK queued as abc123\r\n", $reply);
    }
}
