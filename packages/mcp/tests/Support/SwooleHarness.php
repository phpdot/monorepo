<?php

declare(strict_types=1);

namespace PHPdot\Mcp\Tests\Support;

use PHPUnit\Framework\TestCase;

/**
 * Base for end-to-end tests that boot a real phpdot Server in a separate process
 * and drive the MCP endpoint over raw TCP, so the exact JSON-RPC traffic on the
 * wire is what gets asserted.
 *
 * Lifted from the server package's ServerTestCase — its tests namespace is not
 * autoloadable from here, and the lessons in it (array-form proc_open so the pid
 * is the PHP master; descriptors to files; killing the whole tree because macOS
 * has no PDEATHSIG) are load-bearing, not stylistic.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
abstract class SwooleHarness extends TestCase
{
    /** @var resource|null */
    protected $process = null;

    protected int $port = 0;

    protected string $logFile = '';

    /**
     * Absolute path to the server runner script this test boots.
     *
     * @return string
     */
    abstract protected function runnerScript(): string;

    protected function setUp(): void
    {
        $this->port = $this->findFreePort();
        $this->logFile = sys_get_temp_dir() . '/phpdot_mcp_it_' . getmypid() . '_' . $this->port . '.log';

        // Array form (no `/bin/sh -c` wrapper): proc_open's pid is then the PHP
        // master itself. A string command is run via the shell, and on Linux the
        // shell stays resident as the child while PHP is forked underneath it, so
        // signals aimed at "the master" would hit the shell and never reach Swoole.
        $cmd = [PHP_BINARY, $this->runnerScript(), (string) $this->port];

        // Descriptors go to FILES, never to unread pipes (a full pipe buffer would hang the server).
        $descriptors = [
            0 => ['file', '/dev/null', 'r'],
            1 => ['file', $this->logFile, 'w'],
            2 => ['file', $this->logFile, 'w'],
        ];

        $process = proc_open($cmd, $descriptors, $pipes);
        self::assertIsResource($process, 'failed to launch server runner');
        $this->process = $process;

        $this->waitForServer();
    }

    protected function tearDown(): void
    {
        if (is_resource($this->process)) {
            proc_terminate($this->process, SIGTERM);

            $deadline = microtime(true) + 3.0;
            while (microtime(true) < $deadline) {
                if (proc_get_status($this->process)['running'] === false) {
                    break;
                }
                usleep(50_000);
            }

            // Backstop: SIGKILL the WHOLE process tree — killing only the master
            // would orphan the manager/workers (macOS has no PR_SET_PDEATHSIG).
            if (proc_get_status($this->process)['running'] === true) {
                $this->killProcessTree((int) proc_get_status($this->process)['pid']);
            }

            proc_close($this->process);
            $this->process = null;
        }

        if ($this->logFile !== '' && is_file($this->logFile)) {
            @unlink($this->logFile);
        }
    }

    /**
     * One HTTP request over a fresh socket, read to its end.
     *
     * @param string $method The verb
     * @param string $path The path
     * @param string $body The body, if any
     * @param array<string, string> $headers Additional headers
     * @param float $timeout Socket timeout in seconds
     *
     * @return string The raw response
     */
    protected function http(
        string $method,
        string $path,
        string $body = '',
        array $headers = [],
        float $timeout = 5.0,
    ): string {
        $request = strtoupper($method) . ' ' . $path . " HTTP/1.1\r\n";
        $request .= "Host: 127.0.0.1\r\n";

        foreach ($headers as $name => $value) {
            $request .= $name . ': ' . $value . "\r\n";
        }

        if ($body !== '') {
            $request .= 'Content-Length: ' . strlen($body) . "\r\n";
        }

        $request .= "Connection: close\r\n\r\n" . $body;

        return $this->rawRequest($request, $timeout);
    }

    /**
     * The first line of a raw response.
     *
     * @param string $response The raw response
     *
     * @return string
     */
    protected function statusLine(string $response): string
    {
        $pos = strpos($response, "\r\n");

        return $pos === false ? $response : substr($response, 0, $pos);
    }

    /**
     * One header's value off a raw response, or '' when absent.
     *
     * @param string $response The raw response
     * @param string $name The header to read
     *
     * @return string
     */
    protected function headerLine(string $response, string $name): string
    {
        $found = '';

        foreach (explode("\r\n", $response) as $line) {
            if (stripos($line, $name . ':') === 0) {
                $found = trim((string) substr($line, strlen($name) + 1));
            }
        }

        return $found;
    }

    /**
     * A response's body, split from its headers.
     *
     * @param string $response The raw response
     *
     * @return string
     */
    protected function bodyOf(string $response): string
    {
        $parts = explode("\r\n\r\n", $response, 2);

        return $parts[1] ?? '';
    }

    /**
     * A JSON-RPC body decoded — or the last SSE `data:` frame, when the server
     * answered a stream.
     *
     * @param string $response The raw response
     *
     * @return array<string, mixed>
     */
    protected function jsonOf(string $response): array
    {
        $body = $this->bodyOf($response);

        if (str_contains($this->headerLine($response, 'Content-Type'), 'text/event-stream')) {
            preg_match_all('/^data: (.+)$/m', $body, $matches);

            $body = (string) end($matches[1]);
        }

        self::assertNotSame('', $body, 'the server answered an empty body: ' . $response);

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($body, true, 32, JSON_THROW_ON_ERROR);

        return $decoded;
    }

    /**
     * SIGKILL a process and its entire descendant tree (master → manager → workers),
     * parent-first so the manager cannot respawn a worker.
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
     * @return list<int> $pid then every descendant, parent-first
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
    private function waitForServer(): void
    {
        $deadline = microtime(true) + 5.0;

        while (microtime(true) < $deadline) {
            $fp = @fsockopen('127.0.0.1', $this->port, $errno, $errstr, 0.2);

            if (is_resource($fp)) {
                fclose($fp);

                return;
            }

            if (is_resource($this->process) && proc_get_status($this->process)['running'] === false) {
                self::fail("server exited before becoming ready:\n" . (string) @file_get_contents($this->logFile));
            }

            usleep(100_000);
        }

        self::fail("server did not become ready in time:\n" . (string) @file_get_contents($this->logFile));
    }

    /**
     * Write one raw request and read everything the socket gives back.
     *
     * @param string $raw The raw request bytes
     * @param float $timeout Socket timeout in seconds
     *
     * @return string
     */
    private function rawRequest(string $raw, float $timeout = 5.0): string
    {
        $fp = fsockopen('127.0.0.1', $this->port, $errno, $errstr, 5.0);
        self::assertIsResource($fp, "connect failed: {$errstr}");
        stream_set_timeout($fp, (int) $timeout);

        fwrite($fp, $raw);

        $response = '';

        while (feof($fp) === false) {
            $chunk = fread($fp, 8192);

            if ($chunk === false || $chunk === '') {
                break;
            }

            $response .= $chunk;

            if (stream_get_meta_data($fp)['timed_out'] === true) {
                break;
            }
        }

        fclose($fp);

        return $response;
    }
}
