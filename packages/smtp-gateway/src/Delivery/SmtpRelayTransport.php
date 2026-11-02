<?php

declare(strict_types=1);

/**
 * SMTP relay delivery transport — forwards via SMTP to another server.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Gateway\Delivery;

use PHPdot\Mail\Gateway\Exception\DeliveryException;
use PHPdot\Mail\Gateway\Exception\PermanentDeliveryException;

/**
 * Delivers messages by relaying via SMTP to an upstream server.
 *
 * Useful for Postmark, Mailgun, SendGrid, or any SMTP relay.
 * Uses PHP's stream_socket_client — no extensions required.
 */
final class SmtpRelayTransport implements TransportInterface
{
    public function __construct(
        private readonly string $host,
        private readonly int $port = 587,
        private readonly string $encryption = 'tls',
        private readonly string|null $username = null,
        private readonly string|null $password = null,
        private readonly int $timeout = 30,
        private readonly string|null $heloName = null,
    ) {}

    public function send(string $eml, string $sender, array $recipients): string
    {
        $this->assertPath($sender, true);
        foreach ($recipients as $rcpt) {
            $this->assertPath($rcpt, false);
        }

        $address = $this->buildAddress();
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $errno = 0;
        $errstr = '';
        $stream = @stream_socket_client($address, $errno, $errstr, $this->timeout, STREAM_CLIENT_CONNECT, $context);

        if ($stream === false) {
            throw new DeliveryException("SMTP relay connection failed: $errstr ($errno)");
        }

        try {
            stream_set_timeout($stream, $this->timeout);
            $this->expectCode($stream, 220, 'greeting');

            $host = gethostname();
            $helo = $this->heloName ?? ($host !== false && $host !== '' ? $host : 'localhost');

            $this->sendLine($stream, 'EHLO ' . $helo);
            $ehlo = $this->expectCode($stream, 250, 'EHLO');

            if ($this->encryption === 'tls') {
                if (preg_match('/^250[ -]STARTTLS\s*$/mi', $ehlo) !== 1) {
                    throw new DeliveryException('SMTP relay does not offer STARTTLS');
                }

                $this->sendLine($stream, 'STARTTLS');
                $this->expectCode($stream, 220, 'STARTTLS');

                $result = @stream_socket_enable_crypto(
                    $stream,
                    true,
                    STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT,
                );
                if ($result !== true) {
                    throw new DeliveryException('TLS upgrade failed for SMTP relay');
                }

                $this->sendLine($stream, 'EHLO ' . $helo);
                $this->expectCode($stream, 250, 'EHLO after TLS');
            }

            if ($this->username !== null && $this->password !== null) {
                $token = base64_encode("\0" . $this->username . "\0" . $this->password);
                $this->sendLine($stream, 'AUTH PLAIN ' . $token);
                $this->expectCode($stream, 235, 'AUTH');
            }

            $this->sendLine($stream, 'MAIL FROM:<' . $sender . '>');
            $this->expectCode($stream, 250, 'MAIL FROM');

            foreach ($recipients as $rcpt) {
                $this->sendLine($stream, 'RCPT TO:<' . $rcpt . '>');
                $this->expectCode($stream, [250, 251], 'RCPT TO');
            }

            $this->sendLine($stream, 'DATA');
            $this->expectCode($stream, 354, 'DATA');

            $lines = explode("\r\n", preg_replace('/\r\n|\r|\n/', "\r\n", $eml) ?? $eml);

            if ($lines[array_key_last($lines)] === '') {
                array_pop($lines);
            }
            foreach ($lines as $line) {
                if (str_starts_with($line, '.')) {
                    $line = '.' . $line;
                }
                $this->sendLine($stream, $line);
            }

            $this->sendLine($stream, '.');
            $reply = $this->expectCode($stream, 250, 'DATA termination');

            $this->sendLine($stream, 'QUIT');

            return $this->extractMessageId($reply);
        } finally {
            @fclose($stream);
        }
    }

    public function name(): string
    {
        return 'smtp-relay';
    }

    /**
     * Refuse an envelope address that could carry another command: a control character, space or angle bracket.
     */
    private function assertPath(string $address, bool $mayBeEmpty): void
    {
        if (($address === '' && !$mayBeEmpty) || preg_match('/[\x00-\x20\x7F<>]/', $address) === 1) {
            throw new PermanentDeliveryException('SMTP relay refused an invalid envelope address');
        }
    }

    private function buildAddress(): string
    {
        $protocol = match ($this->encryption) {
            'ssl' => 'ssl',
            default => 'tcp',
        };

        return $protocol . '://' . $this->host . ':' . $this->port;
    }

    /**
     * @param resource $stream
     */
    private function sendLine($stream, string $line): void
    {
        $data = $line . "\r\n";
        $written = @fwrite($stream, $data);

        if ($written === false || $written < strlen($data)) {
            throw new DeliveryException('SMTP relay write failed');
        }
    }

    /**
     * Read one reply and require its code to be one expected; a 5xx reply is permanent and not worth retrying.
     *
     * @param resource $stream
     * @param int|list<int> $expected
     */
    private function expectCode($stream, int|array $expected, string $context): string
    {
        $response = '';
        $maxLines = 1000;
        $i = 0;

        for (; $i < $maxLines; $i++) {
            $line = @fgets($stream, 1024);
            if ($line === false) {
                throw new DeliveryException("SMTP relay read failed during $context");
            }

            $response .= $line;

            if (!isset($line[3])) {
                break;
            }

            if ($line[3] === ' ') {
                break;
            }
            if ($line[3] === '-') {
                continue;
            }
            break;
        }

        if ($i >= $maxLines) {
            throw new DeliveryException("SMTP relay response too many lines during $context");
        }

        $code = (int) substr($response, 0, 3);
        $accepted = is_int($expected) ? [$expected] : $expected;

        if (!in_array($code, $accepted, true)) {
            $message = "SMTP relay unexpected response during $context: " . trim($response)
                . ' (expected ' . implode(' or ', $accepted) . ')';

            throw $code >= 500 && $code < 600 ? new PermanentDeliveryException($message) : new DeliveryException($message);
        }

        return $response;
    }

    private function extractMessageId(string $reply): string
    {

        if (preg_match('/\bid=([^\s]+)/i', $reply, $m) === 1) {
            return $m[1];
        }

        return bin2hex(random_bytes(16));
    }
}
