<?php

declare(strict_types=1);

/**
 * Server-side handler registration. Developer registers callbacks for each SMTP event.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Gateway;

use PHPdot\Mail\Gateway\Connection\ConnectionContext;
use PHPdot\Mail\Gateway\Server\Event\AuthEvent;
use PHPdot\Mail\Gateway\Server\Event\ConnectEvent;
use PHPdot\Mail\Gateway\Server\Event\MailFromEvent;
use PHPdot\Mail\Gateway\Server\Event\MessageEvent;
use PHPdot\Mail\Gateway\Server\Event\RcptToEvent;

/**
 * Register your SMTP server logic here.
 *
 * Each handler receives the typed event and the connection context.
 * Call $event->accept() or $event->reject() to control the SMTP response.
 *
 * Usage:
 *   $handler = new SmtpHandler();
 *   $handler->onAuth(function (AuthEvent $event, ConnectionContext $ctx): void {
 *       if ($event->credentials->username === 'omar') {
 *           $event->accept();
 *       } else {
 *           $event->reject('Invalid credentials');
 *       }
 *   });
 *   $handler->onMessage(function (MessageEvent $event, ConnectionContext $ctx): void {
 *       $storage->store($event->id, $event->message);
 *       $event->accept();
 *   });
 */
final class SmtpHandler
{
    /** @var array<string, callable> */
    private array $handlers = [];

    /**
     * Called on new connection.
     *
     * @param callable(ConnectEvent, ConnectionContext): void $handler
     */
    public function onConnect(callable $handler): void
    {
        $this->handlers['CONNECT'] = $handler;
    }

    /**
     * Called on AUTH command.
     *
     * @param callable(AuthEvent, ConnectionContext): void $handler
     */
    public function onAuth(callable $handler): void
    {
        $this->handlers['AUTH'] = $handler;
    }

    /**
     * Called on MAIL FROM.
     *
     * @param callable(MailFromEvent, ConnectionContext): void $handler
     */
    public function onMailFrom(callable $handler): void
    {
        $this->handlers['MAIL_FROM'] = $handler;
    }

    /**
     * Called on RCPT TO.
     *
     * @param callable(RcptToEvent, ConnectionContext): void $handler
     */
    public function onRcptTo(callable $handler): void
    {
        $this->handlers['RCPT_TO'] = $handler;
    }

    /**
     * Called when a complete message is received (DATA completes).
     *
     * @param callable(MessageEvent, ConnectionContext): void $handler
     */
    public function onMessage(callable $handler): void
    {
        $this->handlers['MESSAGE'] = $handler;
    }

    /**
     * Called after message is successfully accepted. Fire-and-forget.
     *
     * @param callable(MessageEvent, ConnectionContext): void $handler
     */
    public function onQueued(callable $handler): void
    {
        $this->handlers['QUEUED'] = $handler;
    }

    public function getHandler(string $name): callable|null
    {
        return $this->handlers[strtoupper($name)] ?? null;
    }

    public function hasHandler(string $name): bool
    {
        return isset($this->handlers[strtoupper($name)]);
    }
}
