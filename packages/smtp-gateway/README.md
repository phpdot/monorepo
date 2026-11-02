# phpdot/smtp-gateway

SMTP proxy server for PHP. Accept emails via SMTP, store them, deliver via AWS SES or any provider.

## Requirements

| Requirement | Constraint |
|---|---|
| PHP | `>=8.5` |
| `ext-mbstring` | `*` |

Development only:  `friendsofphp/php-cs-fixer` `^3.94`,  `phpstan/phpstan` `^2.0`,  `phpstan/phpstan-strict-rules` `^2.0`,  `phpunit/phpunit` `^13.0`.

## Install

```
composer require phpdot/smtp-gateway
```

Requires PHP 8.2+. Zero runtime dependencies.

## Quick Start

```php
use PHPdot\Mail\Gateway\SmtpHandler;
use PHPdot\Mail\Gateway\Server\StreamServer;
use PHPdot\Mail\Gateway\Server\Event\AuthEvent;
use PHPdot\Mail\Gateway\Server\Event\MessageEvent;

$handler = new SmtpHandler();

$handler->onAuth(function (AuthEvent $event, $ctx): void {
    if ($event->credentials->username === 'api' && $event->credentials->password === 'secret') {
        $event->accept();
    } else {
        $event->reject('Invalid credentials');
    }
});

$handler->onMessage(function (MessageEvent $event, $ctx): void {
    file_put_contents("/var/mail/queue/{$event->id}.eml", $event->message);
    $event->accept();
});

$server = new StreamServer($handler, port: 2525);
$server->start();
```

## Server

The library ships with `StreamServer` — a concurrent TCP server using `stream_select()`. No extensions needed.

```php
$server = new StreamServer(
    handler: $handler,
    host: '0.0.0.0',
    port: 2525,
    hostname: 'mail.example.com',
    tlsRequired: true,
    authRequired: true,
);

// STARTTLS: AUTH is offered only inside TLS while tlsRequired is true (the default)
$server->enableTls('/path/to/cert.pem', '/path/to/key.pem');

$server->start();
```

For Swoole, Workerman, or ReactPHP — implement `ServerInterface` or use `ServerConnection` directly:

```php
use PHPdot\Mail\Gateway\Connection\ServerConnection;

// On each TCP connection:
$conn = new ServerConnection($handler, hostname: 'mail.example.com');
$greeting = $conn->greeting();       // send to client
$responses = $conn->onData($bytes);  // feed client bytes, get SMTP responses
$conn->needsTlsUpgrade();            // check if TLS upgrade needed
$conn->tlsUpgraded();                // notify TLS complete
$conn->isClosing();                  // check if QUIT received
```

## Handlers

Every handler receives a typed event and the connection context. Call `$event->accept()` or `$event->reject()` to control the SMTP response.

```php
use PHPdot\Mail\Gateway\Server\Event\ConnectEvent;
use PHPdot\Mail\Gateway\Server\Event\AuthEvent;
use PHPdot\Mail\Gateway\Server\Event\MailFromEvent;
use PHPdot\Mail\Gateway\Server\Event\RcptToEvent;
use PHPdot\Mail\Gateway\Server\Event\MessageEvent;

$handler->onConnect(function (ConnectEvent $event, $ctx): void {
    // $event->clientIp
    $event->accept();
});

$handler->onAuth(function (AuthEvent $event, $ctx): void {
    // $event->credentials->username
    // $event->credentials->password
    // $event->credentials->mechanism (PLAIN, LOGIN, XOAUTH2)
    $event->accept();
});

$handler->onMailFrom(function (MailFromEvent $event, $ctx): void {
    // $event->sender
    // $event->size
    // $event->bodyType
    $event->accept();
});

$handler->onRcptTo(function (RcptToEvent $event, $ctx): void {
    // $event->recipient
    $event->accept();
});

$handler->onMessage(function (MessageEvent $event, $ctx): void {
    // $event->id
    // $event->sender
    // $event->recipients
    // $event->message (raw .eml)
    // $event->size
    $event->accept();
});

$handler->onQueued(function (MessageEvent $event, $ctx): void {
    // Fire-and-forget after message is accepted
});
```

## Storage

```php
use PHPdot\Mail\Gateway\Storage\FilesystemStorage;
use PHPdot\Mail\Gateway\DataType\DTO\MessageEnvelope;
use PHPdot\Mail\Gateway\DataType\Enum\QueueState;

$storage = new FilesystemStorage('/var/mail/outbox');

$handler->onMessage(function (MessageEvent $event, $ctx) use ($storage): void {
    $envelope = new MessageEnvelope(
        id: $event->id,
        sender: $event->sender,
        recipients: $event->recipients,
        size: $event->size,
        state: QueueState::Pending,
        authUser: $ctx->user() ?? '',
        clientIp: $ctx->clientIp,
        receivedAt: new \DateTimeImmutable(),
    );

    $storage->store($envelope, $event->message);
    $event->accept();
});
```

For S3, Redis, or database storage — implement `StorageInterface`.

## Delivery

```php
use PHPdot\Mail\Gateway\Delivery\DeliveryWorker;
use PHPdot\Mail\Gateway\Delivery\SesTransport;

$transport = new SesTransport($yourSesClient);
$worker = new DeliveryWorker($storage, $transport);

$worker->onDelivered(function (string $id, string $providerMsgId, string $transport): void {
    echo "Delivered $id via $transport ($providerMsgId)\n";
});

$worker->onFailed(function (string $id, string $error, int $attempts): void {
    echo "Failed $id after $attempts attempts: $error\n";
});

// Process a batch
$worker->processBatch(10);

// Or run continuously
$worker->run();
```

### SMTP relay transport

```php
use PHPdot\Mail\Gateway\Delivery\SmtpRelayTransport;

$transport = new SmtpRelayTransport(
    host: 'smtp.postmarkapp.com',
    port: 587,
    encryption: 'tls',
    username: 'your-api-token',
    password: 'your-api-token',
);

$worker = new DeliveryWorker($storage, $transport);
$worker->run();
```

## What's Covered

### SMTP Server (RFC 5321)
* EHLO / HELO handshake
* STARTTLS upgrade (RFC 3207)
* AUTH PLAIN, LOGIN, XOAUTH2 (RFC 4954, RFC 7628) — pick which to offer with `authMechanisms`; an unoffered mechanism gets 504
* MAIL FROM with SIZE check (RFC 1870)
* RCPT TO with multiple recipients
* DATA with dot-stuffing; lines end at CRLF alone, a bare CR or LF in a message is written as CRLF
* An oversize message is read to its final dot, then refused with 552
* RSET, NOOP, QUIT
* PIPELINING support (RFC 2920)
* 8BITMIME support (RFC 6152)

### Security
* Require TLS before AUTH; commands pipelined behind STARTTLS are discarded, never run in plaintext
* Require AUTH before sending
* Envelope addresses with a control character, space or missing brackets are refused
* Commands with a bare CR or LF, or longer than 512 bytes (AUTH lines: 12 KB), are refused whole
* Max message size enforcement
* Max recipients per message
* Max bad commands before disconnect: unknown, out-of-sequence and failed-AUTH replies all count, and the limit closes
* Configurable: TLS optional, AUTH optional

### Storage
* Pluggable storage interface
* Filesystem storage (works out of the box)
* Queue states: pending → sending → sent / failed
* Retry tracking with metadata
* Files written whole and renamed in, owner-only; a claim is an atomic rename, so two workers never send one message

### Delivery
* Pluggable transport interface
* AWS SES transport (via SesClientInterface)
* SMTP relay transport (any SMTP provider): `encryption: 'tls'` requires STARTTLS on any port before credentials
* Exponential backoff retry (30s → 2h); a 5xx refusal (`PermanentDeliveryException`) fails at once
* Stuck message recovery, counted from the moment the message was claimed
* A message recorded as sent is never re-sent, even when `onDelivered` throws
* Event hooks: onDelivered, onFailed, onRetry

## License

MIT
