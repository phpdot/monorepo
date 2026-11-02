<?php

declare(strict_types=1);

namespace PHPdot\Mail\Gateway\Tests\Unit\Delivery;

use PHPdot\Mail\Gateway\Delivery\SmtpRelayTransport;
use PHPdot\Mail\Gateway\Exception\DeliveryException;
use PHPdot\Mail\Gateway\Exception\PermanentDeliveryException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The relay transport against a recording upstream run in a child process.
 */
final class SmtpRelayTransportTest extends TestCase
{
    private string $log = '';

    protected function setUp(): void
    {
        $this->log = sys_get_temp_dir() . '/relay_upstream_' . bin2hex(random_bytes(8)) . '.log';
    }

    protected function tearDown(): void
    {
        @unlink($this->log);
    }

    #[Test]
    public function credentialsAreNeverSentWhenTheUpstreamOffersNoStartTls(): void
    {
        $failure = $this->relay(offerStartTls: false, encryption: 'tls', username: 'relayuser', password: 'relaypass');

        self::assertInstanceOf(DeliveryException::class, $failure);
        self::assertStringContainsString('does not offer STARTTLS', $failure->getMessage());
        self::assertSame([], $this->linesStartingWith('AUTH'));
    }

    #[Test]
    public function startTlsIsAskedForOnAnyPortBeforeCredentials(): void
    {
        $failure = $this->relay(offerStartTls: true, encryption: 'tls', username: 'relayuser', password: 'relaypass');

        self::assertInstanceOf(DeliveryException::class, $failure);
        self::assertSame(["STARTTLS\r\n"], $this->linesStartingWith('STARTTLS'));
        self::assertSame([], $this->linesStartingWith('AUTH'));
    }

    #[Test]
    public function bareLineEndingsAreSentAsCrlfAndDotStuffed(): void
    {
        $failure = $this->relay(eml: "Subject: a\r\n\r\nhello\n.\r\nMAIL FROM:<x@y.test>\r\n");

        self::assertNull($failure);
        $lines = $this->lines();
        $data = array_slice($lines, (int) array_search("DATA\r\n", $lines, true) + 1, 5);
        self::assertSame(["Subject: a\r\n", "\r\n", "hello\r\n", "..\r\n", "MAIL FROM:<x@y.test>\r\n"], $data);
    }

    #[Test]
    public function recipientCarryingALineBreakIsRefusedBeforeConnecting(): void
    {
        $transport = new SmtpRelayTransport('127.0.0.1', 1, 'none');

        $this->expectException(PermanentDeliveryException::class);
        $transport->send("x\r\n", 'a@b.test', ["bob@y.test\nRCPT TO:<hidden@evil.test>"]);
    }

    #[Test]
    public function forwardedRecipientIsAccepted(): void
    {
        self::assertNull($this->relay(rcptCode: '251'));
        self::assertSame(["EHLO relay.example\r\n"], $this->linesStartingWith('EHLO'));
    }

    #[Test]
    public function refusedRecipientIsPermanent(): void
    {
        self::assertInstanceOf(PermanentDeliveryException::class, $this->relay(rcptCode: '550'));
    }

    private function relay(
        bool $offerStartTls = false,
        string $encryption = 'none',
        string|null $username = null,
        string|null $password = null,
        string $rcptCode = '250',
        string $eml = "Subject: a\r\n\r\nhi\r\n",
    ): DeliveryException|null {
        $port = random_int(30000, 49999);
        $process = proc_open(
            [PHP_BINARY, __DIR__ . '/../../fixtures/recording_smtp_upstream.php', (string) $port, $this->log, $offerStartTls ? '1' : '0', $rcptCode],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );

        if (!is_resource($process) || fgets($pipes[1]) !== "ready\n") {
            throw new RuntimeException('recording upstream did not start');
        }

        $transport = new SmtpRelayTransport('127.0.0.1', $port, $encryption, $username, $password, 5, 'relay.example');

        try {
            $transport->send($eml, 'a@b.test', ['c@d.test']);
            return null;
        } catch (DeliveryException $e) {
            return $e;
        } finally {
            proc_terminate($process);
            proc_close($process);
        }
    }

    /**
     * @return list<string>
     */
    private function lines(): array
    {
        $lines = [];

        foreach (file($this->log, FILE_IGNORE_NEW_LINES) ?: [] as $encoded) {
            $line = json_decode($encoded);
            if (is_string($line)) {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function linesStartingWith(string $verb): array
    {
        return array_values(array_filter($this->lines(), static fn(string $line): bool => str_starts_with($line, $verb)));
    }
}
