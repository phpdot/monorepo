<?php

/**
 * End-to-end tests for the implicit-TLS (SMTPS) listener.
 *
 * Boots the real StreamServer in a child process with a self-signed
 * certificate and talks to it over ssl:// — verifying the deployment shape
 * production uses: encrypted from the first byte, no plaintext mode.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

declare(strict_types=1);

namespace PHPdot\Mail\Gateway\Tests\Unit\Server;

use PHPdot\Mail\Gateway\Connection\ServerConnection;
use PHPdot\Mail\Gateway\Server\Event\AuthEvent;
use PHPdot\Mail\Gateway\SmtpHandler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Implicit TLS (SMTPS) behavior.
 */
final class StreamServerImplicitTlsTest extends TestCase
{
    private static string $certFile = '';

    private static string $keyFile = '';

    public static function setUpBeforeClass(): void
    {
        self::$certFile = __DIR__ . '/../../fixtures/localhost-cert.pem';
        self::$keyFile = __DIR__ . '/../../fixtures/localhost-key.pem';
    }

    #[Test]
    public function greetingArrivesOverImplicitTls(): void
    {
        $this->withServer(function (int $port): void {
            $client = $this->connectTls($port);

            $greeting = $this->readReply($client);
            self::assertStringStartsWith('220', $greeting);

            fwrite($client, "QUIT\r\n");
            fclose($client);
        });
    }

    #[Test]
    public function starttlsIsNotAdvertisedAndAuthWorksImmediately(): void
    {
        $this->withServer(function (int $port): void {
            $client = $this->connectTls($port);
            $this->readReply($client); // greeting

            fwrite($client, "EHLO client.example\r\n");
            $ehlo = $this->readReply($client);

            self::assertStringStartsWith('250', $ehlo);
            self::assertStringContainsString('AUTH', $ehlo);
            self::assertStringNotContainsString('STARTTLS', $ehlo);

            // AUTH is available immediately — the session is TLS-active from birth.
            fwrite($client, 'AUTH PLAIN ' . base64_encode("\0user\0pass") . "\r\n");
            self::assertStringStartsWith('235', $this->readReply($client));

            // STARTTLS on an encrypted session is a protocol violation.
            fwrite($client, "STARTTLS\r\n");
            self::assertStringStartsWith('503', $this->readReply($client));

            // A full submission transaction still works.
            fwrite($client, "MAIL FROM:<sender@example.com>\r\n");
            self::assertStringStartsWith('250', $this->readReply($client));

            fwrite($client, "RCPT TO:<rcpt@example.net>\r\n");
            self::assertStringStartsWith('250', $this->readReply($client));

            fwrite($client, "DATA\r\n");
            self::assertStringStartsWith('354', $this->readReply($client));

            fwrite($client, "Subject: tls test\r\n\r\nBody over implicit TLS.\r\n.\r\n");
            $accepted = $this->readReply($client);
            self::assertStringStartsWith('250', $accepted);

            fwrite($client, "QUIT\r\n");
            self::assertStringStartsWith('221', $this->readReply($client));
            fclose($client);
        });
    }

    #[Test]
    public function plaintextClientNeverReceivesAGreeting(): void
    {
        $this->withServer(function (int $port): void {
            $client = $this->connectPlain($port);

            stream_set_timeout($client, 2);
            fwrite($client, "EHLO attacker.example\r\n");

            $data = (string) fread($client, 1024);
            self::assertStringStartsNotWith('220', $data, 'server must not speak SMTP in plaintext');
            fclose($client);
        });
    }

    #[Test]
    public function protocolMarksSessionTlsActiveFromBirth(): void
    {
        $handler = new SmtpHandler();
        $handler->onAuth(static function (AuthEvent $event): void {
            if ($event->credentials->username === 'user') {
                $event->accept();
                return;
            }
            $event->reject('no');
        });

        $conn = new ServerConnection(
            $handler,
            tlsRequired: true,
            authRequired: true,
            implicitTls: true,
        );
        $conn->greeting();

        $responses = $conn->onData("EHLO client.example\r\n");
        $ehlo = implode('', $responses);
        self::assertStringContainsString('AUTH', $ehlo);
        self::assertStringNotContainsString('STARTTLS', $ehlo);
        self::assertFalse($conn->needsTlsUpgrade());

        $responses = $conn->onData('AUTH PLAIN ' . base64_encode("\0user\0pass") . "\r\n");
        self::assertStringStartsWith('235', implode('', $responses));

        $responses = $conn->onData("STARTTLS\r\n");
        self::assertStringStartsWith('503', implode('', $responses));
    }

    /**
     * Boot the fixture server on a random port, run the client, then kill it.
     *
     * @param callable(int): void $client
     */
    private function withServer(callable $client): void
    {
        $port = random_int(25000, 49999);
        $stdout = tempnam(sys_get_temp_dir(), 'smtps-out-') ?: throw new RuntimeException('temp file failed');
        $stderr = tempnam(sys_get_temp_dir(), 'smtps-err-') ?: throw new RuntimeException('temp file failed');

        $proc = proc_open(
            [PHP_BINARY, __DIR__ . '/../../fixtures/implicit_tls_smtp_server.php', (string) $port, self::$certFile, self::$keyFile],
            [
                0 => ['pipe', 'r'],
                1 => ['file', $stdout, 'w'],
                2 => ['file', $stderr, 'w'],
            ],
            $pipes,
        );
        self::assertIsResource($proc, 'failed to spawn server process');

        try {
            $client($port);
        } catch (Throwable $failure) {
            $serverLog = (string) file_get_contents($stderr);
            throw new RuntimeException(
                $failure->getMessage() . "\n--- fixture stderr ---\n" . $serverLog,
                0,
                $failure,
            );
        } finally {
            proc_terminate($proc);
            proc_close($proc);
            @unlink($stdout);
            @unlink($stderr);
        }
    }

    /**
     * Connect over TLS, retrying until the child server has bound its port.
     *
     * @return resource
     */
    private function connectTls(int $port)
    {
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ]);

        $errstr = '';
        for ($i = 0; $i < 50; $i++) {
            $client = @stream_socket_client("ssl://127.0.0.1:{$port}", $errno, $errstr, 5.0, STREAM_CLIENT_CONNECT, $context);
            if ($client !== false) {
                stream_set_blocking($client, true);
                stream_set_timeout($client, 10);

                return $client;
            }
            usleep(100_000);
        }

        throw new RuntimeException("implicit-TLS SMTP server never came up on port {$port}: {$errstr}");
    }

    /**
     * Connect over plain TCP, retrying until the child server has bound its port.
     *
     * @return resource
     */
    private function connectPlain(int $port)
    {
        $errstr = '';
        for ($i = 0; $i < 50; $i++) {
            $client = @stream_socket_client("tcp://127.0.0.1:{$port}", $errno, $errstr, 2.0);
            if ($client !== false) {
                return $client;
            }
            usleep(100_000);
        }

        throw new RuntimeException("implicit-TLS SMTP server never came up on port {$port}: {$errstr}");
    }

    /**
     * Read one full SMTP reply (single- or multi-line).
     *
     * @param resource $stream
     */
    private function readReply($stream): string
    {
        $reply = '';
        while (($line = fgets($stream)) !== false) {
            $reply .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                return $reply;
            }
        }

        return $reply;
    }

}
