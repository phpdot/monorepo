<?php

declare(strict_types=1);

/**
 * End-to-end test of STARTTLS on the StreamServer, run in a child process with a self-signed certificate.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Gateway\Tests\Unit\Server;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class StreamServerStartTlsTest extends TestCase
{
    #[Test]
    public function startTlsUpgradesAndAuthenticationFollowsInsideTls(): void
    {
        $fixtures = __DIR__ . '/../../fixtures';
        $port = random_int(25000, 49999);
        $process = proc_open(
            [PHP_BINARY, $fixtures . '/implicit_tls_smtp_server.php', (string) $port, $fixtures . '/localhost-cert.pem', $fixtures . '/localhost-key.pem', 'starttls'],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        self::assertIsResource($process);

        try {
            $client = $this->connect($port);
            self::assertStringStartsWith('220', $this->readReply($client));

            fwrite($client, "EHLO client.example\r\n");
            $ehlo = $this->readReply($client);
            self::assertStringContainsString('STARTTLS', $ehlo);
            self::assertStringNotContainsString('AUTH', $ehlo);

            fwrite($client, "STARTTLS\r\n");
            self::assertStringStartsWith('220', $this->readReply($client));
            self::assertTrue(stream_socket_enable_crypto($client, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT));

            fwrite($client, "EHLO client.example\r\n");
            self::assertStringContainsString('AUTH', $this->readReply($client));

            fwrite($client, 'AUTH PLAIN ' . base64_encode("\0user\0pass") . "\r\n");
            self::assertStringStartsWith('235', $this->readReply($client));

            fwrite($client, "QUIT\r\n");
            fclose($client);
        } finally {
            proc_terminate($process);
            proc_close($process);
        }
    }

    /**
     * @return resource
     */
    private function connect(int $port)
    {
        $context = stream_context_create([
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true],
        ]);

        for ($i = 0; $i < 50; $i++) {
            $client = @stream_socket_client("tcp://127.0.0.1:{$port}", $errno, $errstr, 2.0, STREAM_CLIENT_CONNECT, $context);
            if ($client !== false) {
                stream_set_timeout($client, 10);
                return $client;
            }
            usleep(100_000);
        }

        throw new RuntimeException("SMTP server never came up on port {$port}");
    }

    /**
     * @param resource $stream
     */
    private function readReply($stream): string
    {
        $reply = '';

        while (($line = fgets($stream)) !== false) {
            $reply .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }

        return $reply;
    }
}
