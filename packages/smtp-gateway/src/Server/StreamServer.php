<?php

declare(strict_types=1);

/**
 * Default SMTP server using stream_select() for concurrent connections.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Gateway\Server;

use PHPdot\Mail\Gateway\Connection\ServerConnection;
use PHPdot\Mail\Gateway\Exception\RuntimeException;
use PHPdot\Mail\Gateway\SmtpHandler;

/**
 * Zero-dependency TCP server using stream_socket_server + stream_select.
 *
 * Handles multiple concurrent connections without extensions.
 *
 * Usage:
 *   $handler = new SmtpHandler();
 *   $handler->onAuth(fn($user, $pass, $mech, $ctx) => $user === 'api' && $pass === 'secret');
 *   $handler->onMessage(fn($id, $sender, $rcpts, $eml, $ctx) => store($id, $eml));
 *
 *   $server = new StreamServer($handler, '0.0.0.0', 2525);
 *   $server->start();
 *
 * Implicit TLS (SMTPS, port 465) — production deployments should use this
 * exclusively; there is no plaintext mode on an implicit-TLS listener:
 *
 *   $server = new StreamServer($handler, '0.0.0.0', 465, tlsRequired: true);
 *   $server->enableImplicitTls('cert.pem', 'key.pem');
 *   $server->start();
 */
final class StreamServer implements ServerInterface
{
    /** @var resource|null */
    private $socket = null;

    /** @var array<int, resource> Client streams keyed by (int) resource id */
    private array $clients = [];

    /** @var array<int, ServerConnection> Protocol handlers keyed by (int) resource id */
    private array $connections = [];

    /** @var array<int, float> Last activity timestamp per client */
    private array $lastActivity = [];

    private bool $running = false;

    private string|null $tlsCertFile = null;
    private string|null $tlsKeyFile = null;

    /** Implicit TLS (SMTPS): listener binds ssl://, sessions are encrypted from the first byte. */
    private bool $implicitTls = false;

    /** @var array<int, resource> Streams mid-TLS-handshake keyed by (int) resource id (implicit TLS mode). */
    private array $handshaking = [];

    /** @var list<\Closure(): void> Run once, after the listener is bound and before the loop. */
    private array $listeningCallbacks = [];

    /**
     * @param list<\PHPdot\Mail\Gateway\DataType\Enum\AuthMechanism>|null $authMechanisms Mechanisms offered in EHLO and accepted by AUTH; null offers all
     */
    public function __construct(
        private readonly SmtpHandler $handler,
        private readonly string $host = '0.0.0.0',
        private readonly int $port = 2525,
        private readonly string $hostname = 'localhost',
        private readonly int $maxMessageSize = 26214400,
        private readonly int $maxRecipients = 100,
        private readonly int $maxBadCommands = 20,
        private readonly bool $tlsRequired = false,
        private readonly bool $authRequired = true,
        private readonly int $connectionTimeout = 300,
        private readonly \Closure|null $wireLogger = null,
        private readonly array|null $authMechanisms = null,
    ) {}

    /**
     * Enable STARTTLS support with a certificate.
     */
    public function enableTls(string $certFile, string $keyFile): void
    {
        $this->tlsCertFile = $certFile;
        $this->tlsKeyFile = $keyFile;
    }

    /**
     * Enable implicit TLS (SMTPS, port 465): the listener binds ssl:// and the
     * TLS handshake runs before the SMTP greeting is sent. STARTTLS is never
     * advertised and the session is TLS-active from birth.
     */
    public function enableImplicitTls(string $certFile, string $keyFile): void
    {
        $this->tlsCertFile = $certFile;
        $this->tlsKeyFile = $keyFile;
        $this->implicitTls = true;
    }

    public function start(): void
    {
        if ($this->implicitTls && ($this->tlsCertFile === null || $this->tlsKeyFile === null)) {
            throw new RuntimeException('Implicit TLS requires a certificate and key.');
        }

        $scheme = $this->implicitTls ? 'ssl' : 'tcp';
        $address = "{$scheme}://{$this->host}:{$this->port}";
        $errno = 0;
        $errstr = '';

        $context = stream_context_create();
        if ($this->tlsCertFile !== null && $this->tlsKeyFile !== null) {
            $context = stream_context_create([
                'ssl' => [
                    'local_cert' => $this->tlsCertFile,
                    'local_pk' => $this->tlsKeyFile,
                    'allow_self_signed' => true,
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ]);
        }

        $socket = @stream_socket_server($address, $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);
        if ($socket === false) {
            throw new RuntimeException("Failed to bind {$address}: {$errstr} ({$errno})");
        }
        $this->socket = $socket;

        stream_set_blocking($this->socket, false);
        $this->running = true;

        foreach ($this->listeningCallbacks as $callback) {
            $callback();
        }

        $this->loop();
    }

    /**
     * Run a callback once the listener is bound, before any client is
     * served — the place to drop root privileges after binding a low port.
     */
    public function onListening(\Closure $callback): void
    {
        $this->listeningCallbacks[] = $callback;
    }

    public function shutdown(): void
    {
        $this->running = false;
    }

    private function loop(): void
    {
        while ($this->running) {
            if ($this->socket === null) {
                break;
            }

            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }

            $read = $this->clients;
            $read[] = $this->socket;
            $write = null;
            $except = null;

            $changed = @stream_select($read, $write, $except, 1);
            if ($changed === false) {

                continue;
            }
            if ($changed === 0) {
                $this->reapIdle();
                continue;
            }

            foreach ($read as $stream) {
                if ($stream === $this->socket) {
                    $this->acceptClient();
                } else {
                    $this->readClient($stream);
                }
            }

            $this->reapIdle();
        }

        $this->cleanup();
    }

    private function acceptClient(): void
    {
        if ($this->socket === null) {
            return;
        }

        $client = @stream_socket_accept($this->socket, 0, $peerName);
        if ($client === false) {
            return;
        }

        stream_set_blocking($client, false);
        stream_set_timeout($client, $this->connectionTimeout);

        $id = (int) $client;
        $this->clients[$id] = $client;
        $this->lastActivity[$id] = microtime(true);

        if ($this->implicitTls) {
            $this->handshaking[$id] = $client;
            return;
        }

        $this->initializeConnection($client, is_string($peerName) ? $peerName : null);
    }

    /**
     * Create the protocol connection and send the greeting.
     *
     * @param resource $client
     */
    private function initializeConnection($client, string|null $peerName): void
    {
        $id = (int) $client;

        if ($peerName === null) {
            $name = @stream_socket_get_name($client, true);
            $peerName = is_string($name) ? $name : '127.0.0.1';
        }

        $remoteIp = $this->extractIp($peerName);

        $conn = new ServerConnection(
            handler: $this->handler,
            hostname: $this->hostname,
            maxMessageSize: $this->maxMessageSize,
            maxRecipients: $this->maxRecipients,
            maxBadCommands: $this->maxBadCommands,
            tlsRequired: $this->implicitTls ? false : $this->tlsRequired,
            authRequired: $this->authRequired,
            remoteAddress: $remoteIp,
            implicitTls: $this->implicitTls,
            authMechanisms: $this->authMechanisms,
        );

        $this->connections[$id] = $conn;

        $greeting = $conn->greeting();

        if (str_starts_with($greeting, '421')) {
            @fwrite($client, $greeting);
            $this->removeClient($id);
            return;
        }

        @fwrite($client, $greeting);
    }

    /**
     * Drive the TLS handshake of an implicitly-encrypted connection.
     *
     * @param resource $stream
     */
    private function driveTlsHandshake($stream, int $id): void
    {
        $result = @stream_socket_enable_crypto(
            $stream,
            true,
            STREAM_CRYPTO_METHOD_TLSv1_2_SERVER | STREAM_CRYPTO_METHOD_TLSv1_3_SERVER,
        );

        if ($result === true) {
            unset($this->handshaking[$id]);
            $this->initializeConnection($stream, null);
            return;
        }

        if ($result === 0) {
            return;
        }

        $this->removeClient($id);
    }

    /**
     * @param resource $stream
     */
    private function readClient($stream): void
    {
        $id = (int) $stream;

        if (isset($this->handshaking[$id])) {
            $this->driveTlsHandshake($stream, $id);
            return;
        }

        $conn = $this->connections[$id] ?? null;

        if ($conn === null) {
            $this->removeClient($id);
            return;
        }

        $data = @fread($stream, 65535);

        if ($data === false || ($data === '' && feof($stream))) {
            $this->removeClient($id);
            return;
        }
        if ($data === '') {
            return;
        }

        $this->logWire($id, 'C>', $data);
        $this->lastActivity[$id] = microtime(true);

        $responses = $conn->onData($data);

        foreach ($responses as $response) {
            $this->logWire($id, 'S>', $response);
            $written = @fwrite($stream, $response);
            if ($written === false) {
                $this->removeClient($id);
                return;
            }
        }

        if ($conn->needsTlsUpgrade()) {
            if ($this->tlsCertFile === null || $this->tlsKeyFile === null) {
                @fwrite($stream, "454 TLS not configured\r\n");
                $this->removeClient($id);
                return;
            }

            $result = @stream_socket_enable_crypto(
                $stream,
                true,
                STREAM_CRYPTO_METHOD_TLSv1_2_SERVER | STREAM_CRYPTO_METHOD_TLSv1_3_SERVER,
            );

            if ($result !== true) {
                $this->removeClient($id);
                return;
            }

            $conn->tlsUpgraded();
        }

        if ($conn->isClosing()) {
            $this->removeClient($id);
        }
    }

    private function reapIdle(): void
    {
        if ($this->connectionTimeout <= 0) {
            return;
        }

        $now = microtime(true);

        foreach ($this->lastActivity as $id => $lastTime) {
            if (($now - $lastTime) > $this->connectionTimeout) {

                if (isset($this->clients[$id], $this->connections[$id])) {
                    @fwrite($this->clients[$id], "421 Connection timed out\r\n");
                }
                $this->removeClient($id);
            }
        }
    }

    private function removeClient(int $id): void
    {
        if (isset($this->clients[$id])) {
            @fclose($this->clients[$id]);
        }

        unset($this->clients[$id], $this->connections[$id], $this->lastActivity[$id], $this->handshaking[$id]);
    }

    private function cleanup(): void
    {
        foreach (array_keys($this->clients) as $id) {
            $this->removeClient($id);
        }

        $this->handshaking = [];

        if ($this->socket !== null) {
            @fclose($this->socket);
            $this->socket = null;
        }
    }

    private function extractIp(string $peerName): string
    {

        $pos = strrpos($peerName, ':');
        if ($pos === false) {
            return $peerName;
        }

        return substr($peerName, 0, $pos);
    }

    /**
     * Forward wire bytes to the injected logger, if any.
     */
    private function logWire(int $id, string $direction, string $bytes): void
    {
        if ($this->wireLogger !== null) {
            ($this->wireLogger)($id, $direction, $bytes);
        }
    }
}
