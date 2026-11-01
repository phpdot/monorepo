#!/usr/bin/env php
<?php
/**
 * Example SMTP server using the built-in StreamServer.
 *
 * Usage:
 *   php examples/server.php                # listen on 2525
 *   php examples/server.php 8025           # custom port
 *
 * Test with:
 *   swaks --to user@example.com --from sender@test.com --server 127.0.0.1:2525 --auth-user omar --auth-password secret
 */

declare(strict_types=1);

$autoload = __DIR__ . '/../vendor/autoload.php';
if (!is_file($autoload)) {
    $autoload = __DIR__ . '/../../../vendor/autoload.php';
}
require $autoload;

use PHPdot\Mail\Gateway\DataType\DTO\MessageEnvelope;
use PHPdot\Mail\Gateway\DataType\Enum\QueueState;
use PHPdot\Mail\Gateway\Server\Event\AuthEvent;
use PHPdot\Mail\Gateway\Server\Event\ConnectEvent;
use PHPdot\Mail\Gateway\Server\Event\MessageEvent;
use PHPdot\Mail\Gateway\Server\StreamServer;
use PHPdot\Mail\Gateway\SmtpHandler;
use PHPdot\Mail\Gateway\Storage\FilesystemStorage;

$port    = (int) ($argv[1] ?? 2525);
$mailDir = sys_get_temp_dir() . '/outbox_' . getmypid();

$storage = new FilesystemStorage($mailDir);
$handler = new SmtpHandler();

$handler->onConnect(function (ConnectEvent $event, $ctx): void {
    echo "[connect] {$event->clientIp}\n";
    $event->accept();
});

$handler->onAuth(function (AuthEvent $event, $ctx): void {
    echo "[auth] {$event->credentials->username} via {$event->credentials->mechanism->value}\n";
    if ($event->credentials->username === 'omar' && $event->credentials->password === 'secret') {
        $event->accept();
    } else {
        $event->reject('Invalid credentials');
    }
});

$handler->onMessage(function (MessageEvent $event, $ctx) use ($storage): void {
    $storage->store(
        new MessageEnvelope(
            id: $event->id,
            sender: $event->sender,
            recipients: $event->recipients,
            size: $event->size,
            state: QueueState::Pending,
            authUser: $ctx->user() ?? '',
            clientIp: $ctx->clientIp,
            receivedAt: new DateTimeImmutable(),
        ),
        $event->message,
    );
    echo "[queued] {$event->id} ({$event->size} bytes) → " . implode(', ', $event->recipients) . "\n";
    $event->accept();
});

$server = new StreamServer(
    handler: $handler,
    port: $port,
    authRequired: true,
    tlsRequired: false,
);

echo "SMTP listening on 0.0.0.0:$port (auth: omar/secret)\n";
echo "Queue: $mailDir\n";

if (function_exists('pcntl_signal')) {
    pcntl_signal(SIGINT, function () use ($server): void {
        echo "\nShutting down...\n";
        $server->shutdown();
    });
}

$server->start();
