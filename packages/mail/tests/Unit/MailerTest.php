<?php

declare(strict_types=1);

namespace PHPdot\Mail\Tests\Unit;

use PHPdot\Mail\Exception\MailException;
use PHPdot\Mail\MailConfig;
use PHPdot\Mail\Mailer;
use PHPdot\Mail\Message\Message;
use PHPdot\Mail\Transport\EmailFactory;
use PHPdot\Mail\Transport\Transport;
use PHPUnit\Framework\TestCase;

final class MailerTest extends TestCase
{
    private function mailer(string $dsn = 'null://null'): Mailer
    {
        return new Mailer(new MailConfig(dsn: $dsn), new Transport(new EmailFactory()));
    }

    public function testDeliversAComposedChainThroughANullTransport(): void
    {
        $receipt = $this->mailer()
            ->from('no-reply@example.com', 'App')
            ->to('alice@example.com', 'Alice')
            ->cc('cc@example.com')
            ->replyTo('reply@example.com')
            ->subject('Welcome')
            ->text('Hello')
            ->html('<p>Hello</p>')
            ->priority(2)
            ->header('X-Campaign', 'welcome')
            ->send();

        self::assertMatchesRegularExpression('/^[^\s@]+@[^\s@]+$/', $receipt->messageId);
        self::assertSame('', $receipt->debug);
    }

    public function testDeliversAStandaloneMessageViaMailerSend(): void
    {
        $message = (new Message())
            ->from('no-reply@example.com')
            ->to('alice@example.com')
            ->subject('standalone')
            ->text('sent without a mailer-started chain');

        $receipt = $this->mailer()->send($message);

        self::assertMatchesRegularExpression('/^[^\s@]+@[^\s@]+$/', $receipt->messageId);
        self::assertSame('', $receipt->debug);
    }

    public function testRejectsAMessageWithNoRecipient(): void
    {
        $this->expectException(MailException::class);

        $this->mailer()->from('a@example.com')->subject('Hi')->text('x')->send();
    }
}
