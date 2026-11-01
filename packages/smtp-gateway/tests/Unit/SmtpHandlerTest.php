<?php

declare(strict_types=1);

namespace PHPdot\Mail\Gateway\Tests\Unit;

use PHPdot\Mail\Gateway\Connection\ConnectionContext;
use PHPdot\Mail\Gateway\Server\Event\AuthEvent;
use PHPdot\Mail\Gateway\Server\Event\ConnectEvent;
use PHPdot\Mail\Gateway\Server\Event\MailFromEvent;
use PHPdot\Mail\Gateway\Server\Event\MessageEvent;
use PHPdot\Mail\Gateway\Server\Event\RcptToEvent;
use PHPdot\Mail\Gateway\SmtpHandler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SmtpHandlerTest extends TestCase
{
    #[Test]
    public function noHandlersRegisteredByDefault(): void
    {
        $handler = new SmtpHandler();

        self::assertFalse($handler->hasHandler('AUTH'));
        self::assertFalse($handler->hasHandler('CONNECT'));
        self::assertFalse($handler->hasHandler('MAIL_FROM'));
        self::assertFalse($handler->hasHandler('RCPT_TO'));
        self::assertFalse($handler->hasHandler('MESSAGE'));
        self::assertFalse($handler->hasHandler('QUEUED'));
        self::assertNull($handler->getHandler('AUTH'));
    }

    #[Test]
    public function onAuthRegistersHandler(): void
    {
        $handler = new SmtpHandler();
        $handler->onAuth(fn(AuthEvent $e, ConnectionContext $ctx) => $e->accept());

        self::assertTrue($handler->hasHandler('AUTH'));
        self::assertNotNull($handler->getHandler('AUTH'));
    }

    #[Test]
    public function onConnectRegistersHandler(): void
    {
        $handler = new SmtpHandler();
        $handler->onConnect(fn(ConnectEvent $e, ConnectionContext $ctx) => $e->accept());

        self::assertTrue($handler->hasHandler('CONNECT'));
    }

    #[Test]
    public function onMailFromRegistersHandler(): void
    {
        $handler = new SmtpHandler();
        $handler->onMailFrom(fn(MailFromEvent $e, ConnectionContext $ctx) => $e->accept());

        self::assertTrue($handler->hasHandler('MAIL_FROM'));
    }

    #[Test]
    public function onRcptToRegistersHandler(): void
    {
        $handler = new SmtpHandler();
        $handler->onRcptTo(fn(RcptToEvent $e, ConnectionContext $ctx) => $e->accept());

        self::assertTrue($handler->hasHandler('RCPT_TO'));
    }

    #[Test]
    public function onMessageRegistersHandler(): void
    {
        $handler = new SmtpHandler();
        $handler->onMessage(fn(MessageEvent $e, ConnectionContext $ctx) => $e->accept());

        self::assertTrue($handler->hasHandler('MESSAGE'));
    }

    #[Test]
    public function onQueuedRegistersHandler(): void
    {
        $handler = new SmtpHandler();
        $handler->onQueued(fn(MessageEvent $e, ConnectionContext $ctx) => null);

        self::assertTrue($handler->hasHandler('QUEUED'));
    }

    #[Test]
    public function getHandlerIsCaseInsensitive(): void
    {
        $handler = new SmtpHandler();
        $handler->onAuth(fn(AuthEvent $e, ConnectionContext $ctx) => $e->accept());

        self::assertNotNull($handler->getHandler('auth'));
        self::assertNotNull($handler->getHandler('Auth'));
        self::assertNotNull($handler->getHandler('AUTH'));
    }

    #[Test]
    public function hasHandlerIsCaseInsensitive(): void
    {
        $handler = new SmtpHandler();
        $handler->onAuth(fn(AuthEvent $e, ConnectionContext $ctx) => $e->accept());

        self::assertTrue($handler->hasHandler('auth'));
        self::assertTrue($handler->hasHandler('Auth'));
    }

    #[Test]
    public function handlerCanBeReplaced(): void
    {
        $handler = new SmtpHandler();

        $first = fn(AuthEvent $e, ConnectionContext $ctx) => $e->accept();
        $second = fn(AuthEvent $e, ConnectionContext $ctx) => $e->reject('no');

        $handler->onAuth($first);
        $handler->onAuth($second);

        self::assertSame($second, $handler->getHandler('AUTH'));
    }

    #[Test]
    public function getHandlerReturnsNullForUnknown(): void
    {
        $handler = new SmtpHandler();
        self::assertNull($handler->getHandler('UNKNOWN'));
    }
}
