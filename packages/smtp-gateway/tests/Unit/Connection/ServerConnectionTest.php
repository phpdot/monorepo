<?php

declare(strict_types=1);

namespace PHPdot\Mail\Gateway\Tests\Unit\Connection;

use PHPdot\Mail\Gateway\Connection\ServerConnection;
use PHPdot\Mail\Gateway\DataType\Enum\SessionState;
use PHPdot\Mail\Gateway\Server\Event\AuthEvent;
use PHPdot\Mail\Gateway\Server\Event\ConnectEvent;
use PHPdot\Mail\Gateway\Server\Event\MessageEvent;
use PHPdot\Mail\Gateway\SmtpHandler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ServerConnectionTest extends TestCase
{
    #[Test]
    public function greetingReturns220(): void
    {
        $handler = new SmtpHandler();
        $conn = new ServerConnection($handler, hostname: 'mail.test.com', tlsRequired: false, authRequired: false);

        $greeting = $conn->greeting();
        self::assertStringStartsWith('220 ', $greeting);
        self::assertStringContainsString('mail.test.com', $greeting);
    }

    #[Test]
    public function connectHandlerCanRejectConnection(): void
    {
        $handler = new SmtpHandler();
        $handler->onConnect(fn(ConnectEvent $e, $ctx) => $e->reject('blocked'));

        $conn = new ServerConnection($handler, tlsRequired: false, authRequired: false, remoteAddress: '192.168.1.1');
        $greeting = $conn->greeting();

        self::assertStringStartsWith('421 ', $greeting);
    }

    #[Test]
    public function connectHandlerExceptionReturns421(): void
    {
        $handler = new SmtpHandler();
        $handler->onConnect(function (): void {
            throw new \RuntimeException('handler crashed');
        });

        $conn = new ServerConnection($handler, tlsRequired: false, authRequired: false);
        $greeting = $conn->greeting();

        self::assertStringStartsWith('421 ', $greeting);
    }

    #[Test]
    public function authHandlerIntegration(): void
    {
        $handler = new SmtpHandler();
        $handler->onAuth(function (AuthEvent $event, $ctx): void {
            if ($event->credentials->username === 'omar' && $event->credentials->password === 'secret') {
                $event->accept();
            } else {
                $event->reject('Invalid');
            }
        });

        $conn = new ServerConnection($handler, tlsRequired: false, authRequired: true);
        $conn->greeting();
        $conn->onData("EHLO test\r\n");

        $token = base64_encode("\0omar\0secret");
        $responses = $conn->onData("AUTH PLAIN $token\r\n");
        self::assertStringStartsWith('235 ', $responses[0]);
        self::assertTrue($conn->isAuthenticated());
    }

    #[Test]
    public function contextTracksRemoteAddress(): void
    {
        $handler = new SmtpHandler();
        $conn = new ServerConnection($handler, remoteAddress: '10.0.0.5');

        self::assertSame('10.0.0.5', $conn->context()->clientIp);
    }

    #[Test]
    public function defaultRemoteAddress(): void
    {
        $handler = new SmtpHandler();
        $conn = new ServerConnection($handler);

        self::assertSame('127.0.0.1', $conn->context()->clientIp);
    }

    #[Test]
    public function stateStartsConnected(): void
    {
        $handler = new SmtpHandler();
        $conn = new ServerConnection($handler);

        self::assertSame(SessionState::Connected, $conn->state());
    }

    #[Test]
    public function isClosingAfterQuit(): void
    {
        $handler = new SmtpHandler();
        $conn = new ServerConnection($handler, tlsRequired: false, authRequired: false);
        $conn->greeting();

        self::assertFalse($conn->isClosing());

        $conn->onData("QUIT\r\n");
        self::assertTrue($conn->isClosing());
    }

    #[Test]
    public function needsTlsUpgradeAfterStartTls(): void
    {
        $handler = new SmtpHandler();
        $conn = new ServerConnection($handler, tlsRequired: true, authRequired: false);
        $conn->greeting();

        self::assertFalse($conn->needsTlsUpgrade());

        $conn->onData("EHLO test\r\n");
        $conn->onData("STARTTLS\r\n");

        self::assertTrue($conn->needsTlsUpgrade());

        $conn->tlsUpgraded();
        self::assertFalse($conn->needsTlsUpgrade());
    }

    #[Test]
    public function messageHandlerReceivesEvent(): void
    {
        $receivedEvents = [];

        $handler = new SmtpHandler();
        $handler->onAuth(fn(AuthEvent $e, $ctx) => $e->accept());
        $handler->onMessage(function (MessageEvent $event, $ctx) use (&$receivedEvents): void {
            $receivedEvents[] = $event;
            $event->accept();
        });

        $conn = new ServerConnection($handler, tlsRequired: false, authRequired: true);
        $conn->greeting();

        $conn->onData("EHLO test\r\n");
        $token = base64_encode("\0user\0pass");
        $conn->onData("AUTH PLAIN $token\r\n");
        $conn->onData("MAIL FROM:<sender@test.com>\r\n");
        $conn->onData("RCPT TO:<rcpt@test.com>\r\n");
        $conn->onData("DATA\r\n");
        $conn->onData("Subject: Hello\r\n\r\nBody\r\n.\r\n");

        self::assertCount(1, $receivedEvents);
        self::assertSame('sender@test.com', $receivedEvents[0]->sender);
        self::assertSame(['rcpt@test.com'], $receivedEvents[0]->recipients);
        self::assertStringContainsString('Subject: Hello', $receivedEvents[0]->message);
    }

    #[Test]
    public function messageHandlerCanReject(): void
    {
        $handler = new SmtpHandler();
        $handler->onAuth(fn(AuthEvent $e, $ctx) => $e->accept());
        $handler->onMessage(fn(MessageEvent $e, $ctx) => $e->reject('Spam detected'));

        $conn = new ServerConnection($handler, tlsRequired: false, authRequired: true);
        $conn->greeting();

        $conn->onData("EHLO test\r\n");
        $token = base64_encode("\0u\0p");
        $conn->onData("AUTH PLAIN $token\r\n");
        $conn->onData("MAIL FROM:<a@b.com>\r\n");
        $conn->onData("RCPT TO:<c@d.com>\r\n");
        $conn->onData("DATA\r\n");
        $responses = $conn->onData("Subject: Test\r\n\r\n.\r\n");

        self::assertStringStartsWith('554 ', $responses[0]);
    }

    #[Test]
    public function queuedCallbackReceivesEvent(): void
    {
        $queuedEvents = [];

        $handler = new SmtpHandler();
        $handler->onAuth(fn(AuthEvent $e, $ctx) => $e->accept());
        $handler->onMessage(fn(MessageEvent $e, $ctx) => $e->accept());
        $handler->onQueued(function (MessageEvent $event, $ctx) use (&$queuedEvents): void {
            $queuedEvents[] = $event;
        });

        $conn = new ServerConnection($handler, tlsRequired: false, authRequired: true);
        $conn->greeting();

        $conn->onData("EHLO test\r\n");
        $token = base64_encode("\0u\0p");
        $conn->onData("AUTH PLAIN $token\r\n");
        $conn->onData("MAIL FROM:<a@b.com>\r\n");
        $conn->onData("RCPT TO:<c@d.com>\r\n");
        $conn->onData("DATA\r\n");
        $conn->onData("Subject: Test\r\n\r\n.\r\n");

        self::assertCount(1, $queuedEvents);
        self::assertSame('a@b.com', $queuedEvents[0]->sender);
    }

    #[Test]
    public function contextTracksMessageCount(): void
    {
        $handler = new SmtpHandler();
        $handler->onAuth(fn(AuthEvent $e, $ctx) => $e->accept());
        $handler->onMessage(fn(MessageEvent $e, $ctx) => $e->accept());

        $conn = new ServerConnection($handler, tlsRequired: false, authRequired: true);
        $conn->greeting();

        $conn->onData("EHLO test\r\n");
        $token = base64_encode("\0u\0p");
        $conn->onData("AUTH PLAIN $token\r\n");

        $conn->onData("MAIL FROM:<a@b.com>\r\n");
        $conn->onData("RCPT TO:<c@d.com>\r\n");
        $conn->onData("DATA\r\n");
        $conn->onData("Subject: First\r\n\r\n.\r\n");

        $conn->onData("MAIL FROM:<a@b.com>\r\n");
        $conn->onData("RCPT TO:<c@d.com>\r\n");
        $conn->onData("DATA\r\n");
        $conn->onData("Subject: Second\r\n\r\n.\r\n");

        self::assertSame(2, $conn->context()->messageCount());
    }

    #[Test]
    public function authSetsUserOnContext(): void
    {
        $handler = new SmtpHandler();
        $handler->onAuth(fn(AuthEvent $e, $ctx) => $e->accept());

        $conn = new ServerConnection($handler, tlsRequired: false, authRequired: true);
        $conn->greeting();
        $conn->onData("EHLO test\r\n");

        self::assertNull($conn->context()->user());

        $token = base64_encode("\0myuser\0mypass");
        $conn->onData("AUTH PLAIN $token\r\n");

        self::assertSame('myuser', $conn->context()->user());
    }

    #[Test]
    public function unhandledAuthRejects(): void
    {
        $handler = new SmtpHandler();
        // No auth handler registered

        $conn = new ServerConnection($handler, tlsRequired: false, authRequired: true);
        $conn->greeting();
        $conn->onData("EHLO test\r\n");

        $token = base64_encode("\0u\0p");
        $responses = $conn->onData("AUTH PLAIN $token\r\n");

        self::assertStringStartsWith('535 ', $responses[0]);
    }
}
