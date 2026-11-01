<?php

declare(strict_types=1);

namespace PHPdot\Mail\Gateway\Tests\Unit\Server;

use PHPdot\Mail\Gateway\Server\Event\ConnectEvent;
use PHPdot\Mail\Gateway\Server\Event\MailFromEvent;
use PHPdot\Mail\Gateway\Server\EventEmitter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EventEmitterTest extends TestCase
{
    #[Test]
    public function emitCallsHandler(): void
    {
        $emitter = new EventEmitter();
        $called = false;

        $emitter->on(ConnectEvent::class, function (ConnectEvent $e) use (&$called): void {
            $called = true;
            $e->accept();
        });

        $event = new ConnectEvent('127.0.0.1');
        $emitter->emit($event);

        self::assertTrue($called);
        self::assertTrue($event->isAccepted());
    }

    #[Test]
    public function emitStopsAfterFirstHandledEvent(): void
    {
        $emitter = new EventEmitter();
        $calls = 0;

        $emitter->on(ConnectEvent::class, function (ConnectEvent $e) use (&$calls): void {
            $calls++;
            $e->accept();
        });

        $emitter->on(ConnectEvent::class, function (ConnectEvent $e) use (&$calls): void {
            $calls++;
            $e->accept();
        });

        $emitter->emit(new ConnectEvent('127.0.0.1'));
        self::assertSame(1, $calls);
    }

    #[Test]
    public function emitCallsMultipleHandlersUntilHandled(): void
    {
        $emitter = new EventEmitter();
        $calls = [];

        $emitter->on(ConnectEvent::class, function (ConnectEvent $e) use (&$calls): void {
            $calls[] = 'first';
            // Don't handle — let next handler run
        });

        $emitter->on(ConnectEvent::class, function (ConnectEvent $e) use (&$calls): void {
            $calls[] = 'second';
            $e->accept();
        });

        $emitter->emit(new ConnectEvent('127.0.0.1'));
        self::assertSame(['first', 'second'], $calls);
    }

    #[Test]
    public function emitDoesNothingForUnregisteredEvents(): void
    {
        $emitter = new EventEmitter();
        $event = new ConnectEvent('127.0.0.1');
        $emitter->emit($event);

        self::assertFalse($event->isHandled());
    }

    #[Test]
    public function offRemovesHandlers(): void
    {
        $emitter = new EventEmitter();
        $called = false;

        $emitter->on(ConnectEvent::class, function (ConnectEvent $e) use (&$called): void {
            $called = true;
        });

        $emitter->off(ConnectEvent::class);

        $emitter->emit(new ConnectEvent('127.0.0.1'));
        self::assertFalse($called);
    }

    #[Test]
    public function removeAllClearsAllHandlers(): void
    {
        $emitter = new EventEmitter();

        $emitter->on(ConnectEvent::class, function (ConnectEvent $e): void {
            $e->accept();
        });
        $emitter->on(MailFromEvent::class, function (MailFromEvent $e): void {
            $e->accept();
        });

        $emitter->removeAll();

        $event1 = new ConnectEvent('127.0.0.1');
        $event2 = new MailFromEvent('a@b.com');
        $emitter->emit($event1);
        $emitter->emit($event2);

        self::assertFalse($event1->isHandled());
        self::assertFalse($event2->isHandled());
    }

    #[Test]
    public function differentEventTypesAreIndependent(): void
    {
        $emitter = new EventEmitter();
        $connectCalled = false;
        $mailCalled = false;

        $emitter->on(ConnectEvent::class, function (ConnectEvent $e) use (&$connectCalled): void {
            $connectCalled = true;
            $e->accept();
        });

        $emitter->on(MailFromEvent::class, function (MailFromEvent $e) use (&$mailCalled): void {
            $mailCalled = true;
            $e->accept();
        });

        $emitter->emit(new ConnectEvent('127.0.0.1'));
        self::assertTrue($connectCalled);
        self::assertFalse($mailCalled);
    }
}
