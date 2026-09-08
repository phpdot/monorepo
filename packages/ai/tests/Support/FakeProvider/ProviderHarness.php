<?php

declare(strict_types=1);

namespace PHPdot\Ai\Tests\Support\FakeProvider;

use PHPUnit\Framework\TestCase;

/**
 * Base for wire tests that boot the fake provider in a separate process and
 * drive it with the package's own StreamingHttp — so the exact curl behavior on
 * the wire (incremental frames, status capture, abandonment) is what gets
 * asserted.
 *
 * Lifted from the mcp package's SwooleHarness, which lifted it from the server
 * package's ServerTestCase — the lessons in it (array-form proc_open so the pid
 * is the PHP master; descriptors to files, never unread pipes; killing the
 * whole tree because macOS has no PDEATHSIG) are load-bearing, not stylistic.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
abstract class ProviderHarness extends TestCase
{
    /** @var resource|null */
    protected $process = null;

    protected int $port = 0;

    protected string $logFile = '';

    protected string $requestsPath = '';

    protected function setUp(): void
    {
        $this->serve($this->scenario());
    }

    /**
     * (Re)boot the fake provider serving one scenario — a test may switch
     * scenarios mid-run; each boot is a fresh process on a fresh port.
     *
     * @param string $scenario Which scenario the provider serves
     *
     * @return void
     */
    protected function serve(string $scenario): void
    {
        $this->stop();

        $this->port = $this->findFreePort();
        $this->logFile = sys_get_temp_dir() . '/phpdot_ai_provider_' . getmypid() . '_' . $this->port . '.log';
        $this->requestsPath = sys_get_temp_dir() . '/phpdot_ai_requests_' . getmypid() . '_' . $this->port . '.jsonl';
        @unlink($this->requestsPath);

        $cmd = [
            PHP_BINARY,
            __DIR__ . '/provider_runner.php',
            (string) $this->port,
            $scenario,
            $this->requestsPath,
        ];

        $descriptors = [
            0 => ['file', '/dev/null', 'r'],
            1 => ['file', $this->logFile, 'w'],
            2 => ['file', $this->logFile, 'w'],
        ];

        $process = proc_open($cmd, $descriptors, $pipes);
        self::assertIsResource($process, 'failed to launch the fake provider');
        $this->process = $process;

        $this->waitForProvider();
    }

    protected function tearDown(): void
    {
        $this->stop();

        foreach ([$this->logFile, $this->requestsPath] as $file) {
            if ($file !== '' && is_file($file)) {
                @unlink($file);
            }
        }
    }

    /**
     * Stop the running provider, if one is running.
     *
     * @return void
     */
    private function stop(): void
    {
        if (is_resource($this->process)) {
            proc_terminate($this->process, SIGTERM);

            $deadline = microtime(true) + 2.0;
            while (microtime(true) < $deadline) {
                if (proc_get_status($this->process)['running'] === false) {
                    break;
                }
                usleep(50_000);
            }

            if (proc_get_status($this->process)['running'] === true) {
                $this->killProcessTree((int) proc_get_status($this->process)['pid']);
            }

            proc_close($this->process);
            $this->process = null;
        }
    }

    /**
     * Which scenario the provider serves this test.
     *
     * @return string
     */
    abstract protected function scenario(): string;

    /**
     * Where the provider root for this test's turns is.
     *
     * @return string
     */
    protected function baseUrl(): string
    {
        return 'http://127.0.0.1:' . $this->port;
    }

    /**
     * The requests the provider received, decoded.
     *
     * @return list<array<string, mixed>>
     */
    protected function requests(): array
    {
        $raw = (string) @file_get_contents($this->requestsPath);

        $requests = [];

        foreach (array_filter(explode("\n", trim($raw))) as $line) {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($line, true);

            if (is_array($decoded)) {
                $requests[] = $decoded;
            }
        }

        return $requests;
    }

    /**
     * @return int A port the kernel confirms free right now
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

    /**
     * @return void
     */
    private function waitForProvider(): void
    {
        $deadline = microtime(true) + 5.0;

        while (microtime(true) < $deadline) {
            $fp = @fsockopen('127.0.0.1', $this->port, $errno, $errstr, 0.2);

            if (is_resource($fp)) {
                fclose($fp);

                return;
            }

            if (is_resource($this->process) && proc_get_status($this->process)['running'] === false) {
                self::fail("the fake provider exited before becoming ready:\n" . (string) @file_get_contents($this->logFile));
            }

            usleep(50_000);
        }

        self::fail("the fake provider did not become ready in time:\n" . (string) @file_get_contents($this->logFile));
    }

    /**
     * SIGKILL a process and its descendants, parent-first.
     *
     * @param int $pid Where the tree starts
     *
     * @return void
     */
    private function killProcessTree(int $pid): void
    {
        if ($pid <= 0) {
            return;
        }

        foreach ($this->processTree($pid) as $target) {
            @posix_kill($target, SIGKILL);
        }
    }

    /**
     * @param int $pid Where the tree starts
     *
     * @return list<int>
     */
    private function processTree(int $pid): array
    {
        $pids = [$pid];
        $children = (string) shell_exec('pgrep -P ' . $pid . ' 2>/dev/null');

        foreach (array_filter(array_map('intval', explode("\n", trim($children)))) as $child) {
            foreach ($this->processTree($child) as $descendant) {
                $pids[] = $descendant;
            }
        }

        return $pids;
    }
}
