<?php

declare(strict_types=1);

namespace PHPdot\Mail\Gateway\Tests\Unit\Server;

use PHPdot\Mail\Gateway\DataType\DTO\AuthCredentials;
use PHPdot\Mail\Gateway\DataType\Enum\AuthMechanism;
use PHPdot\Mail\Gateway\DataType\Enum\ReplyCode;
use PHPdot\Mail\Gateway\Exception\RuntimeException;
use PHPdot\Mail\Gateway\Server\Event\AuthEvent;
use PHPdot\Mail\Gateway\Server\Event\ConnectEvent;
use PHPdot\Mail\Gateway\Server\Event\MailFromEvent;
use PHPdot\Mail\Gateway\Server\Event\MessageEvent;
use PHPdot\Mail\Gateway\Server\Event\RcptToEvent;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EventTest extends TestCase
{
    #[Test]
    public function eventStartsUnhandled(): void
    {
        $event = new ConnectEvent('127.0.0.1');
        self::assertFalse($event->isHandled());
        self::assertFalse($event->isAccepted());
    }

    #[Test]
    public function acceptMarksHandledAndAccepted(): void
    {
        $event = new ConnectEvent('127.0.0.1');
        $event->accept();

        self::assertTrue($event->isHandled());
        self::assertTrue($event->isAccepted());
    }

    #[Test]
    public function acceptWithData(): void
    {
        $event = new ConnectEvent('127.0.0.1');
        $event->accept(['extra' => 'data']);

        self::assertSame(['extra' => 'data'], $event->getResult());
    }

    #[Test]
    public function rejectMarksHandledButNotAccepted(): void
    {
        $event = new ConnectEvent('127.0.0.1');
        $event->reject('Not allowed');

        self::assertTrue($event->isHandled());
        self::assertFalse($event->isAccepted());
        self::assertSame('Not allowed', $event->getRejectText());
        self::assertSame(ReplyCode::TransactionFailed, $event->getRejectCode());
    }

    #[Test]
    public function rejectWithCustomCode(): void
    {
        $event = new ConnectEvent('127.0.0.1');
        $event->reject('Mailbox full', ReplyCode::InsufficientStorage);

        self::assertSame(ReplyCode::InsufficientStorage, $event->getRejectCode());
    }

    #[Test]
    public function doubleAcceptThrows(): void
    {
        $event = new ConnectEvent('127.0.0.1');
        $event->accept();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Event already handled');
        $event->accept();
    }

    #[Test]
    public function doubleRejectThrows(): void
    {
        $event = new ConnectEvent('127.0.0.1');
        $event->reject('reason');

        $this->expectException(RuntimeException::class);
        $event->reject('again');
    }

    #[Test]
    public function acceptThenRejectThrows(): void
    {
        $event = new ConnectEvent('127.0.0.1');
        $event->accept();

        $this->expectException(RuntimeException::class);
        $event->reject('nope');
    }

    #[Test]
    public function connectEventHasClientIp(): void
    {
        $event = new ConnectEvent('192.168.1.1');
        self::assertSame('192.168.1.1', $event->clientIp);
    }

    #[Test]
    public function authEventExposesCredentials(): void
    {
        $creds = new AuthCredentials(AuthMechanism::Plain, 'user', 'pass');
        $event = new AuthEvent($creds);

        self::assertSame('user', $event->username());
        self::assertSame('pass', $event->password());
        self::assertSame('PLAIN', $event->mechanism());
        self::assertSame($creds, $event->credentials);
    }

    #[Test]
    public function mailFromEventProperties(): void
    {
        $event = new MailFromEvent('user@test.com', 1024, '8BITMIME');

        self::assertSame('user@test.com', $event->sender);
        self::assertSame(1024, $event->size);
        self::assertSame('8BITMIME', $event->bodyType);
    }

    #[Test]
    public function mailFromEventOptionalProperties(): void
    {
        $event = new MailFromEvent('user@test.com');

        self::assertNull($event->size);
        self::assertNull($event->bodyType);
    }

    #[Test]
    public function rcptToEventProperties(): void
    {
        $event = new RcptToEvent('rcpt@test.com');
        self::assertSame('rcpt@test.com', $event->recipient);
    }

    #[Test]
    public function messageEventProperties(): void
    {
        $event = new MessageEvent(
            id: 'msg123',
            sender: 'from@test.com',
            recipients: ['to1@test.com', 'to2@test.com'],
            message: 'Subject: Test',
            size: 512,
            authUser: 'omar',
            clientIp: '10.0.0.1',
        );

        self::assertSame('msg123', $event->id);
        self::assertSame('from@test.com', $event->sender);
        self::assertSame(['to1@test.com', 'to2@test.com'], $event->recipients);
        self::assertSame('Subject: Test', $event->message);
        self::assertSame(512, $event->size);
        self::assertSame('omar', $event->authUser);
        self::assertSame('10.0.0.1', $event->clientIp);
    }
}
