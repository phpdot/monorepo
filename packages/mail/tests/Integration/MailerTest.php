<?php

declare(strict_types=1);

namespace PHPdot\Mail\Tests\Integration;

use PHPdot\Mail\Exception\MailException;
use PHPdot\Mail\MailConfig;
use PHPdot\Mail\Mailer;
use PHPdot\Mail\Transport\EmailFactory;
use PHPdot\Mail\Transport\Transport;
use PHPUnit\Framework\TestCase;

final class MailerTest extends TestCase
{
    private function mailer(string $dsn = 'null://null'): Mailer
    {
        return new Mailer(new MailConfig(dsn: $dsn), new Transport(new EmailFactory()));
    }

    public function testSendsAHeldMessage(): void
    {
        $message = $this->mailer()->message()
            ->from('no-reply@example.com', 'App')
            ->to('alice@example.com', 'Alice')
            ->cc('cc@example.com')
            ->replyTo('reply@example.com')
            ->subject('Welcome')
            ->text('Hello')
            ->html('<p>Hello</p>')
            ->priority(2)
            ->header('X-Campaign', 'welcome');

        self::assertNotSame('', $message->send()->messageId);
    }

    public function testSendsThroughTheFluentChain(): void
    {
        $receipt = $this->mailer()
            ->from('no-reply@example.com', 'App')
            ->to('alice@example.com', 'Alice')
            ->cc('cc@example.com')
            ->subject('Welcome')
            ->text('Hello')
            ->html('<p>Hello</p>')
            ->send();

        self::assertNotSame('', $receipt->messageId);
    }

    public function testRejectsAMessageWithNoRecipient(): void
    {
        $this->expectException(MailException::class);

        $this->mailer()->from('a@example.com')->subject('Hi')->text('x')->send();
    }

    public function testRejectsAnUnknownTransportScheme(): void
    {
        $this->expectException(MailException::class);

        $this->mailer('bogus://host')->from('a@example.com')->to('b@example.com')->subject('Hi')->text('x')->send();
    }
}
