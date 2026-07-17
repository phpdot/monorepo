<?php

declare(strict_types=1);

/**
 * TraceLog Writer Test
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\TraceLog\Tests\Writer;

use PHPdot\Contracts\Logs\EncryptorInterface;
use PHPdot\TraceLog\Log\Channel\ChannelManager;
use PHPdot\TraceLog\Writer\TraceLogWriter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TraceLogWriterTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/tlsink_' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            unlink($file);
        }

        @rmdir($this->dir);
    }

    private function writer(?EncryptorInterface $encryptor = null): TraceLogWriter
    {
        return new TraceLogWriter(new ChannelManager($this->dir), $encryptor);
    }

    private function read(string $channel): string
    {
        return (string) @file_get_contents($this->dir . '/' . $channel . '.log');
    }

    #[Test]
    public function recordsWithoutAChannelDefaultToApp(): void
    {
        $writer = $this->writer();

        $writer->write([
            'type' => 'log', 'level' => 'info', 'message' => 'hello',
            'trace_id' => 't', 'span_id' => 's', 'timestamp' => microtime(true), 'context' => ['k' => 'v'],
        ]);
        $writer->write([
            'type' => 'span', 'name' => 'db.query', 'kind' => 'client',
            'trace_id' => 't', 'span_id' => 'c', 'parent_span_id' => 's',
            'started_at' => 1.0, 'ended_at' => 2.0, 'duration_ms' => 1000.0, 'status' => 'ok',
            'status_message' => '', 'attributes' => [], 'events' => [],
        ]);

        self::assertStringContainsString('hello', $this->read('app'));
        self::assertStringContainsString('db.query', $this->read('app'));
    }

    #[Test]
    public function routesEachRecordToItsChannelFileCorrelatedByTraceId(): void
    {
        $writer = $this->writer();

        $writer->write([
            'type' => 'log', 'level' => 'info', 'message' => 'GET /users', 'channel' => 'http',
            'trace_id' => 'tracewxyz9', 'span_id' => 's1', 'timestamp' => microtime(true), 'context' => [],
        ]);
        $writer->write([
            'type' => 'log', 'level' => 'warning', 'message' => 'login failed', 'channel' => 'auth',
            'trace_id' => 'tracewxyz9', 'span_id' => 's2', 'timestamp' => microtime(true), 'context' => [],
        ]);

        // Separate files per channel...
        self::assertStringContainsString('GET /users', $this->read('http'));
        self::assertStringContainsString('login failed', $this->read('auth'));
        self::assertStringNotContainsString('login failed', $this->read('http'));
        // ...tied together by one trace_id.
        self::assertStringContainsString('tracewxyz9', $this->read('http'));
        self::assertStringContainsString('tracewxyz9', $this->read('auth'));
    }

    #[Test]
    public function storesEveryRecordWithoutSampling(): void
    {
        $writer = $this->writer();

        for ($i = 0; $i < 50; $i++) {
            $writer->write([
                'type' => 'log', 'level' => 'info', 'message' => "m{$i}",
                'trace_id' => 't', 'span_id' => 's', 'timestamp' => microtime(true), 'context' => [],
            ]);
        }

        $lines = array_filter(explode("\n", trim($this->read('app'))));
        self::assertCount(50, $lines);
    }

    #[Test]
    public function sensitiveRecordWithoutEncryptorIsDroppedNeverPlaintext(): void
    {
        $writer = $this->writer(null);

        $writer->write([
            'type' => 'log', 'level' => 'error', 'message' => 'SSN 123-45-6789', 'secure' => true,
            'trace_id' => 't', 'span_id' => 's', 'timestamp' => microtime(true), 'context' => ['card' => '4111111111111111'],
        ]);

        $app = $this->read('app');
        self::assertStringNotContainsString('123-45-6789', $app);
        self::assertStringNotContainsString('4111111111111111', $app);
        self::assertSame('', trim($app), 'fail-closed: the record is dropped, not written');
    }

    #[Test]
    public function sensitiveRecordWithFailingEncryptorIsDroppedAndNeverThrows(): void
    {
        $throwing = new class implements EncryptorInterface {
            public function encrypt(string $plaintext): string
            {
                throw new \RuntimeException('encryptor unavailable');
            }

            public function decrypt(string $ciphertext): string
            {
                return '';
            }
        };

        $writer = $this->writer($throwing);

        // Must not throw — logging may never crash the caller.
        $writer->write([
            'type' => 'log', 'level' => 'error', 'message' => 'SSN 123-45-6789', 'secure' => true,
            'trace_id' => 't', 'span_id' => 's', 'timestamp' => microtime(true), 'context' => [],
        ]);

        self::assertStringNotContainsString('123-45-6789', $this->read('app'));
        self::assertSame('', trim($this->read('app')));
    }

    #[Test]
    public function sensitiveRecordWithEncryptorIsWrittenEncryptedNotPlaintext(): void
    {
        $encryptor = new class implements EncryptorInterface {
            public function encrypt(string $plaintext): string
            {
                return 'ENC[' . base64_encode($plaintext) . ']';
            }

            public function decrypt(string $ciphertext): string
            {
                return '';
            }
        };

        $writer = $this->writer($encryptor);

        $writer->write([
            'type' => 'log', 'level' => 'error', 'message' => 'SSN 123-45-6789', 'secure' => true,
            'trace_id' => 'tracewxyz9', 'span_id' => 's', 'timestamp' => microtime(true), 'context' => ['card' => '4111111111111111'],
        ]);

        $app = $this->read('app');
        self::assertStringNotContainsString('123-45-6789', $app);
        self::assertStringNotContainsString('4111111111111111', $app);
        self::assertStringContainsString('ENC[', $app, 'message is encrypted');
        self::assertStringContainsString('tracewxyz9', $app, 'trace_id stays plaintext for queryability');
    }
}
