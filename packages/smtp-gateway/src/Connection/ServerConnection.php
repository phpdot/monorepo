<?php

declare(strict_types=1);

/**
 * Per-connection server handler. Wraps the protocol engine with the developer's SmtpHandler.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Gateway\Connection;

use PHPdot\Mail\Gateway\DataType\Enum\SessionState;
use PHPdot\Mail\Gateway\Protocol\StateMachine;
use PHPdot\Mail\Gateway\Server\Event\AuthEvent;
use PHPdot\Mail\Gateway\Server\Event\ConnectEvent;
use PHPdot\Mail\Gateway\Server\Event\MailFromEvent;
use PHPdot\Mail\Gateway\Server\Event\MessageEvent;
use PHPdot\Mail\Gateway\Server\Event\RcptToEvent;
use PHPdot\Mail\Gateway\Server\ServerProtocol;
use PHPdot\Mail\Gateway\SmtpHandler;

/**
 * Each client connection gets its own ServerConnection instance.
 * The transport (Swoole, Workerman, etc.) creates one per connection.
 *
 * Usage:
 *   $conn = new ServerConnection($handler);
 *   $greeting = $conn->greeting();          // send to client
 *   $responses = $conn->onData($bytes);     // feed client data, get responses
 *   $conn->needsTlsUpgrade();               // check if TLS upgrade needed
 *   $conn->tlsUpgraded();                   // notify TLS complete
 */
final class ServerConnection
{
    private readonly ServerProtocol $protocol;
    private readonly ConnectionContext $context;

    /**
     * @param list<\PHPdot\Mail\Gateway\DataType\Enum\AuthMechanism>|null $authMechanisms Mechanisms to offer; null offers all
     */
    public function __construct(
        private readonly SmtpHandler $handler,
        string $hostname = 'localhost',
        int $maxMessageSize = 26214400,
        int $maxRecipients = 100,
        int $maxBadCommands = 20,
        bool $tlsRequired = true,
        bool $authRequired = true,
        string|null $remoteAddress = null,
        bool $implicitTls = false,
        array|null $authMechanisms = null,
    ) {

        $effectiveTlsRequired = $implicitTls ? false : $tlsRequired;

        $stateMachine = new StateMachine(
            tlsRequired: $effectiveTlsRequired,
            authRequired: $authRequired,
        );

        $this->context = new ConnectionContext(
            clientIp: $remoteAddress ?? '127.0.0.1',
            connectionId: bin2hex(random_bytes(8)),
            stateMachine: $stateMachine,
        );

        $this->protocol = new ServerProtocol(
            hostname: $hostname,
            maxMessageSize: $maxMessageSize,
            maxRecipients: $maxRecipients,
            maxBadCommands: $maxBadCommands,
            tlsRequired: $effectiveTlsRequired,
            authRequired: $authRequired,
            stateMachine: $stateMachine,
            implicitTls: $implicitTls,
            authMechanisms: $authMechanisms,
            clientIp: $this->context->clientIp,
        );

        $this->registerHandlers();
    }

    /**
     * Generate the server greeting. Send this to the client on connect.
     */
    public function greeting(string $text = ''): string
    {
        $connectHandler = $this->handler->getHandler('CONNECT');
        if ($connectHandler !== null) {
            $event = new ConnectEvent($this->context->clientIp);
            try {
                $connectHandler($event, $this->context);
                if ($event->isHandled() && !$event->isAccepted()) {
                    return "421 {$this->context->clientIp} Connection refused\r\n";
                }
            } catch (\Throwable) {
                return "421 {$this->context->clientIp} Connection refused\r\n";
            }
        }

        return $this->protocol->greeting($text);
    }

    /**
     * Feed incoming client data. Returns response bytes to send back.
     *
     * @return list<string>
     */
    public function onData(string $bytes): array
    {
        return $this->protocol->onData($bytes);
    }

    /**
     * Whether the transport should upgrade to TLS now.
     */
    public function needsTlsUpgrade(): bool
    {
        return $this->protocol->needsTlsUpgrade();
    }

    /**
     * Notify that TLS upgrade completed successfully.
     */
    public function tlsUpgraded(): void
    {
        $this->protocol->tlsUpgraded();
    }

    /**
     * Whether the client has QUIT.
     */
    public function isClosing(): bool
    {
        return $this->protocol->state() === SessionState::Closing;
    }

    public function context(): ConnectionContext
    {
        return $this->context;
    }

    public function isAuthenticated(): bool
    {
        return $this->context->isAuthenticated();
    }

    public function state(): SessionState
    {
        return $this->protocol->state();
    }

    private function registerHandlers(): void
    {

        $this->protocol->on(AuthEvent::class, function (AuthEvent $event): void {
            $authHandler = $this->handler->getHandler('AUTH');
            if ($authHandler === null) {
                $event->reject('Authentication not configured');
                return;
            }

            try {
                $authHandler($event, $this->context);
            } catch (\Throwable $e) {
                if (!$event->isHandled()) {
                    $event->reject('Internal error');
                }
                return;
            }

            if (!$event->isHandled()) {
                $event->reject('Authentication not handled');
                return;
            }

            if ($event->isAccepted()) {
                $this->context->setUser($event->credentials->username);
            }
        });

        $this->protocol->on(MailFromEvent::class, function (MailFromEvent $event): void {
            $mailHandler = $this->handler->getHandler('MAIL_FROM');
            if ($mailHandler !== null) {
                try {
                    $mailHandler($event, $this->context);
                } catch (\Throwable $e) {
                    if (!$event->isHandled()) {
                        $event->reject('Internal error');
                    }
                    return;
                }

                if ($event->isHandled() && !$event->isAccepted()) {
                    return;
                }
            }

            if (!$event->isHandled()) {
                $event->accept();
            }

            $this->context->setSender($event->sender);
        });

        $this->protocol->on(RcptToEvent::class, function (RcptToEvent $event): void {
            $rcptHandler = $this->handler->getHandler('RCPT_TO');
            if ($rcptHandler !== null) {
                try {
                    $rcptHandler($event, $this->context);
                } catch (\Throwable $e) {
                    if (!$event->isHandled()) {
                        $event->reject('Internal error');
                    }
                    return;
                }

                if ($event->isHandled() && !$event->isAccepted()) {
                    return;
                }
            }

            if (!$event->isHandled()) {
                $event->accept();
            }

            $this->context->addRecipient($event->recipient);
        });

        $this->protocol->on(MessageEvent::class, function (MessageEvent $event): void {
            $messageHandler = $this->handler->getHandler('MESSAGE');
            if ($messageHandler === null) {
                $event->accept();
                return;
            }

            try {
                $messageHandler($event, $this->context);
            } catch (\Throwable $e) {
                if (!$event->isHandled()) {
                    $event->reject('Internal error');
                }
                return;
            }

            if (!$event->isHandled()) {
                $event->accept();
            }

            if ($event->isAccepted()) {
                $this->context->incrementMessageCount();
                $this->context->resetTransaction();

                $queuedHandler = $this->handler->getHandler('QUEUED');
                if ($queuedHandler !== null) {
                    try {
                        $queuedHandler($event, $this->context);
                    } catch (\Throwable) {

                    }
                }
            }
        });
    }
}
