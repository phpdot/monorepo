<?php

declare(strict_types=1);

namespace PHPdot\Ai\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The coroutine law, measured: the client yields under the production hook
 * flags — four simultaneous turns against one 600ms hold complete in roughly
 * one hold, not four. If the hook law ever regresses (blocking curl), this is
 * the gate that says so.
 */
final class CoroutineConcurrencyTest extends TestCase
{
    #[Test]
    public function fourTurnsTakeOneHoldNotFour(): void
    {
        if (!extension_loaded('swoole')) {
            self::markTestSkipped('ext-swoole is not loaded.');
        }

        $port = $this->findFreePort();

        $logFile = sys_get_temp_dir() . '/phpdot_ai_concurrency_' . $port . '.log';
        $cmd = [PHP_BINARY, __DIR__ . '/../Support/FakeProvider/concurrency_runner.php', (string) $port];
        $descriptors = [
            0 => ['file', '/dev/null', 'r'],
            1 => ['file', $logFile, 'w'],
            2 => ['file', $logFile, 'w'],
        ];

        $process = proc_open($cmd, $descriptors, $pipes);
        self::assertIsResource($process, 'failed to launch the concurrency runner');

        $deadline = microtime(true) + 15.0;

        while (microtime(true) < $deadline) {
            if (proc_get_status($process)['running'] === false) {
                break;
            }

            usleep(100_000);
        }

        $output = (string) @file_get_contents($logFile);
        proc_close($process);
        @unlink($logFile);

        self::assertStringContainsString('elapsedMs', $output, "the runner said nothing:\n" . $output);

        $elapsedMs = (int) (json_decode(trim((string) strstr($output, '{"elapsedMs')), true)['elapsedMs'] ?? 0);

        self::assertGreaterThan(0, $elapsedMs);
        self::assertLessThan(
            1_600,
            $elapsedMs,
            "four 600ms turns took {$elapsedMs}ms — curl blocked the worker instead of yielding",
        );
    }

    /**
     * @return int
     */
    private function findFreePort(): int
    {
        $sock = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        self::assertIsResource($sock, "could not allocate a free port: {$errstr}");
        $name = stream_socket_get_name($sock, false);
        self::assertIsString($name);
        $port = (int) substr($name, (int) strrpos($name, ':') + 1);
        fclose($sock);

        return $port;
    }
}
