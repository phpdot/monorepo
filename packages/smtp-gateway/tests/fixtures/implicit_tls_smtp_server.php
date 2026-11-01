<?php

/**
 * Child-process fixture: boots a StreamServer with implicit TLS (SMTPS).
 *
 * Usage: php implicit_tls_smtp_server.php <port> <cert.pem> <key.pem>
 *
 * Accepts exactly one credential pair (user/pass) and accepts every message.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

declare(strict_types=1);

$autoload = __DIR__ . '/../../vendor/autoload.php';
if (!is_file($autoload)) {
    $autoload = __DIR__ . '/../../../../vendor/autoload.php';
}
require $autoload;

use PHPdot\Mail\Gateway\Server\Event\AuthEvent;
use PHPdot\Mail\Gateway\Server\Event\MessageEvent;
use PHPdot\Mail\Gateway\SmtpHandler;

$rawArgv = $_SERVER['argv'] ?? [];
$argv = [];
if (is_array($rawArgv)) {
    foreach ($rawArgv as $arg) {
        if (is_string($arg)) {
            $argv[] = $arg;
        }
    }
}

$port = (int) ($argv[1] ?? 0);
$certFile = $argv[2] ?? '';
$keyFile = $argv[3] ?? '';

if ($port === 0 || $certFile === '' || $keyFile === '') {
    fwrite(STDERR, "usage: php implicit_tls_smtp_server.php <port> <cert.pem> <key.pem>\n");
    exit(1);
}

$handler = new SmtpHandler();

$handler->onAuth(static function (AuthEvent $event): void {
    if ($event->credentials->username === 'user' && $event->credentials->password === 'pass') {
        $event->accept();
        return;
    }
    $event->reject('Invalid credentials');
});

$handler->onMessage(static function (MessageEvent $event): void {
    $event->accept();
});

$server = new PHPdot\Mail\Gateway\Server\StreamServer(
    $handler,
    '127.0.0.1',
    (int) $port,
    tlsRequired: true,
    authRequired: true,
);
$server->enableImplicitTls($certFile, $keyFile);
$server->start();
